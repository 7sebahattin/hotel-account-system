import test from 'node:test';
import assert from 'node:assert/strict';
import { parseClipboardDate } from '../../resources/js/date-paste.js';

test('Türkçe ve ISO tarih biçimleri YYYY-MM-DD olarak ayrıştırılır', () => {
    assert.equal(parseClipboardDate('31.10.2026'), '2026-10-31');
    assert.equal(parseClipboardDate('1.2.2026'), '2026-02-01');
    assert.equal(parseClipboardDate('31/10/2026'), '2026-10-31');
    assert.equal(parseClipboardDate('31-10-2026'), '2026-10-31');
    assert.equal(parseClipboardDate('2026-10-31'), '2026-10-31');
    assert.equal(parseClipboardDate('  Cmt 31.10.2026 14:00  '), '2026-10-31');
});

test('geçersiz veya belirsiz metin tarihe çevrilmez', () => {
    for (const value of ['', 'merhaba', '31.02.2026', '32.01.2026', '00.01.2026', '31.13.2026', '31.10.26', '2026-13-01', '123456', null, undefined]) {
        assert.equal(parseClipboardDate(value), null, String(value));
    }
});
