import test from 'node:test';
import assert from 'node:assert/strict';
import { canonicalCardUid, createKioskClient } from '../../resources/js/personnel.js';

test('USB HID UID canonicalizer preserves leading zeroes and rejects raw tokens', () => {
    assert.equal(canonicalCardUid(' 04:00-aa-bb-11 '), '0400AABB11');
    assert.equal(canonicalCardUid('00010203'), '00010203');
    assert.equal(canonicalCardUid('hello'), null);
    assert.equal(canonicalCardUid('ABC'), null);
});

test('uncertain scan retry retains the original UUID and payload', async () => {
    const bodies = []; let attempt = 0; let retry;
    const client = createKioskClient({ scanUrl: '/scan', csrfToken: 'x', fetchImpl: async (_url, options) => { bodies.push(options.body); attempt++; if (attempt === 1) throw new Error('offline'); return { ok: true, json: async () => ({ request_id: 'same-id', result: 'accepted' }) }; }, onError: (_error, again) => { retry = again; } });
    await client.send({ request_id: 'same-id', card_uid: '0400AABB', action: 'auto' });
    await retry();
    assert.equal(bodies[0], bodies[1]);
    assert.equal(client.hasRetry(), false);
});

test('an unresolved request cannot be replaced by a different card or UUID', async () => {
    let calls = 0;
    const client = createKioskClient({ scanUrl: '/scan', fetchImpl: async () => { calls++; throw new Error('offline'); } });
    await client.send({ request_id:'first', card_uid:'0400AABB' });
    await client.send({ request_id:'second', card_uid:'11223344' });
    assert.equal(calls, 1);
    assert.equal(client.hasRetry(), true);
});

test('server lock contention is retryable and cannot generate a fresh scan', async () => {
    let definite;
    const client = createKioskClient({scanUrl:'/scan',fetchImpl:async()=>({ok:false,status:503,json:async()=>({})}),onError:(_e,_retry,value)=>{definite=value;}});
    await client.send({request_id:'lock',card_uid:'0400AABB'});
    assert.equal(definite,false);
    assert.equal(client.hasRetry(),true);
});

test('session persistence restores only the same pending payload for retry', async () => {
    const payload={request_id:'restored',card_uid:'00010203',action:'auto'};
    const memory=new Map([['key',JSON.stringify(payload)]]);
    const storage={getItem:k=>memory.get(k),setItem:(k,v)=>memory.set(k,v),removeItem:k=>memory.delete(k)};
    let sent;
    const client=createKioskClient({scanUrl:'/scan',storage,storageKey:'key',fetchImpl:async(_url,options)=>{sent=JSON.parse(options.body);return {ok:true,json:async()=>({request_id:'restored',result:'duplicate'})};}});
    assert.equal(client.hasRetry(),true);
    await client.retry();
    assert.deepEqual(sent,payload);
    assert.equal(client.hasRetry(),false);
    assert.equal(memory.has('key'),false);
});

test('invalid response and transient HTTP failure keep the only request locked for retry', async () => {
    const memory = new Map(); const storage = { getItem: (k) => memory.get(k) ?? null, setItem: (k, v) => memory.set(k, v), removeItem: (k) => memory.delete(k) };
    let retry; let calls = 0;
    const client = createKioskClient({ scanUrl: '/scan', csrfToken: 'x', storage, fetchImpl: async () => { calls++; return calls === 1 ? { ok: true, json: async () => ({ request_id: 'wrong', result: 'accepted' }) } : { ok: true, json: async () => ({ request_id: 'kept', result: 'accepted' }) }; }, onError: (_error, again) => { retry = again; } });
    await client.send({ request_id: 'kept', card_uid: '0400AABB', action: 'auto' });
    assert.equal(client.hasRetry(), true);
    await retry();
    assert.equal(client.hasRetry(), false);
});

test('definite auth failure clears retry state', async () => {
    let definite = false;
    const client = createKioskClient({ scanUrl: '/scan', csrfToken: 'x', fetchImpl: async () => ({ ok: false, status: 401, json: async () => ({ message: 'Yetkisiz' }) }), onError: (_error, _retry, isDefinite) => { definite = isDefinite; } });
    await client.send({ request_id: 'a', card_uid: '0400AABB', action: 'auto' });
    assert.equal(definite, true); assert.equal(client.hasRetry(), false);
});
