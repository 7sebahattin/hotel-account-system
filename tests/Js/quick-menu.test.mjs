import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

test('floating menu toggles, closes outside and restores focus on Escape', () => {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = source.indexOf('function initQuickActions() {');
    const end = source.indexOf("document.addEventListener('DOMContentLoaded', initQuickActions);", start);
    assert.ok(start >= 0 && end > start);

    const documentEvents = new Map();
    const toggleEvents = new Map();
    const iconClasses = new Set();
    let focusCount = 0;
    let linkFocusCount = 0;
    const icon = { classList: { add: (name) => iconClasses.add(name), remove: (name) => iconClasses.delete(name) } };
    const toggle = {
        attributes: new Map(),
        addEventListener: (name, listener) => toggleEvents.set(name, listener),
        setAttribute(name, value) { this.attributes.set(name, value); },
        querySelector: () => icon,
        focus: () => { focusCount += 1; },
    };
    const panel = { hidden: true, querySelector: () => ({ focus: () => { linkFocusCount += 1; } }) };
    const root = {
        querySelector: (selector) => selector === '[data-quick-action-toggle]' ? toggle : panel,
        contains: (target) => target === toggle || target === panel,
    };
    const document = {
        querySelectorAll: () => [root],
        addEventListener: (name, listener) => documentEvents.set(name, listener),
    };

    vm.runInNewContext(`${source.slice(start, end)}\ninitQuickActions();`, { document });

    toggleEvents.get('click')();
    assert.equal(panel.hidden, false);
    assert.equal(toggle.attributes.get('aria-expanded'), 'true');
    assert.equal(linkFocusCount, 1);
    assert.equal(iconClasses.has('rotate-45'), true);

    documentEvents.get('click')({ target: {} });
    assert.equal(panel.hidden, true);
    assert.equal(toggle.attributes.get('aria-expanded'), 'false');

    toggleEvents.get('click')();
    documentEvents.get('keydown')({ key: 'Escape' });
    assert.equal(panel.hidden, true);
    assert.equal(focusCount, 1);
    assert.equal(iconClasses.has('rotate-45'), false);
});
