import test from 'node:test';
import assert from 'node:assert/strict';
import { MAX_MONEY, parseMoney, parseRate, formatMoney, convertByRate, rateFromTotal, editMoney } from '../../resources/js/monthly-account.js';

test('Türkçe ve düz girdiler kuruş hassasiyetiyle ayrıştırılır', () => {
    assert.equal(parseMoney('3000'), 300000n);
    assert.equal(parseMoney('3.000,00'), 300000n);
    assert.equal(parseMoney('1.234.567,89'), 123456789n);
    assert.equal(parseMoney('3000.25'), 300025n);
    assert.equal(parseMoney('0,01'), 1n);
    assert.equal(formatMoney(MAX_MONEY), '9.999.999.999.999,99');
    assert.equal(formatMoney(-125050n), '-1.250,50');
});

test('belirsiz, hatalı ve negatif giriş sessizce tutara çevrilmez', () => {
    for (const value of ['3.000', '30.00,00', '-3000', '+3000', '3e3', '300 TL', '3 000', '1,234', '', 'NaN', '10000000000000']) {
        assert.equal(parseMoney(value), null, value);
    }
    for (const value of ['-3000', '300TL', '1.00,00']) {
        assert.ok(editMoney('1,00', 0, 4, 'insertFromPaste', value).error);
    }
});

test('art arda 3000 yazıldığında değer 3.000,00 ve imleç tam sayı sonunda kalır', () => {
    let state = { value: '', caret: 0 };
    for (const digit of '3000') state = editMoney(state.value, state.caret, state.caret, 'insertText', digit);
    assert.deepEqual(state, { value: '3.000,00', caret: 5 });
    state = editMoney(state.value, state.caret, state.caret, 'insertText', ',');
    assert.equal(state.caret, 6);
    state = editMoney(state.value, state.caret, state.caret, 'insertText', '2');
    state = editMoney(state.value, state.caret, state.caret, 'insertText', '5');
    assert.deepEqual(state, { value: '3.000,25', caret: 8 });
    assert.ok(editMoney(state.value, 8, 8, 'insertText', '1').error);
});

test('tam sayı ortasında yazma, silme ve küsurat değiştirme imleci korur', () => {
    assert.deepEqual(editMoney('1.234,00', 3, 3, 'insertText', '9'), { value: '12.934,00', caret: 4 });
    assert.deepEqual(editMoney('3.000,00', 5, 5, 'deleteContentBackward'), { value: '300,00', caret: 3 });
    assert.deepEqual(editMoney('3.000,25', 8, 8, 'deleteContentBackward'), { value: '3.000,20', caret: 7 });
    assert.deepEqual(editMoney('3.000,00', 0, 8, 'insertText', '5'), { value: '5,00', caret: 1 });
    assert.deepEqual(editMoney('3.000,00', 0, 8, 'deleteContentBackward'), { value: '', caret: 0 });
    assert.deepEqual(editMoney('3.000,00', 0, 8, 'insertFromPaste', '1250,50'), { value: '1.250,50', caret: 5 });
});

test('kur hesaplaması dört ondalık ve yarım yukarı kuruş yuvarlamasını korur', () => {
    assert.equal(parseRate('41,2345'), 412345n);
    assert.equal(parseRate('0'), null);
    assert.equal(parseRate('41,23456'), null);
    assert.equal(convertByRate(30000n, 412345n), 1237035n);
    assert.equal(convertByRate(1n, 15000n), 2n);
    assert.equal(convertByRate(MAX_MONEY, 9999999999n), null);
    assert.equal(convertByRate(0n, 10000n), null);
});

test('TL toplamından hesaplanan kur esas TL toplamını değiştirmez', () => {
    const amount = 300000n;
    const exactTotal = 100001n;
    const rate = rateFromTotal(amount, exactTotal);
    assert.equal(rate, 3333n);
    assert.equal(convertByRate(amount, rate), 99990n);
    assert.equal(exactTotal, 100001n);
    assert.equal(rateFromTotal(0n, exactTotal), null);
    assert.equal(rateFromTotal(1n, MAX_MONEY), null);
});
