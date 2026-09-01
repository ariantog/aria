'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const appBlade = fs.readFileSync(
    path.join(__dirname, '../../resources/views/layouts/app.blade.php'),
    'utf8',
);
const cashBlade = fs.readFileSync(
    path.join(__dirname, '../../resources/views/transactions/cash.blade.php'),
    'utf8',
);

function extractFunction(source, name) {
    const start = source.indexOf(`function ${name}(`);
    if (start === -1) {
        throw new Error(`missing function ${name}`);
    }
    const brace = source.indexOf('{', start);
    let depth = 0;
    for (let i = brace; i < source.length; i++) {
        if (source[i] === '{') depth++;
        else if (source[i] === '}') {
            depth--;
            if (depth === 0) {
                return source.slice(start, i + 1);
            }
        }
    }
    throw new Error(`unclosed function ${name}`);
}

global.window = { _suppressFieldNavUntil: 0 };

const helperNames = [
    'normalizeNavigationKey',
    'isImePlaceholderKey',
    'isConfirmedEnterKey',
    'suppressFieldNavigation',
    'isFieldNavigationSuppressed',
    'claimEnterFieldNavigation',
];

eval(
    helperNames.map((name) => extractFunction(appBlade, name)).join('\n\n')
    + '\n'
    + helperNames.map((name) => `global.${name} = ${name};`).join('\n'),
);

function resetNavClock() {
    global.window._suppressFieldNavUntil = 0;
}

function makeCashNav(total = null) {
    const items = [{ total }];
    const dests = [];
    let handled = false;

    function rowTotalFilled(row) {
        if (!row) return false;
        const raw = row.total;
        if (raw === null || raw === '' || raw === undefined) return false;
        return Number(raw) >= 0.01;
    }

    function process(idx, field, e, fromKeyup) {
        const isEnter = fromKeyup
            ? normalizeNavigationKey(e) === 'Enter'
            : isConfirmedEnterKey(e);
        if (!isEnter) return false;
        if (e.repeat) return true;
        if (!claimEnterFieldNavigation()) return true;

        let dest = null;
        if (field === 'invoice') dest = 'note';
        else if (field === 'note') dest = 'total';
        else if (field === 'total') {
            if (!rowTotalFilled(items[idx])) return true;
            dest = 'next';
        } else {
            return false;
        }
        dests.push(dest);
        return true;
    }

    function keydown(idx, field, e) {
        if (e.repeat && (isConfirmedEnterKey(e) || normalizeNavigationKey(e) === 'Enter')) {
            handled = true;
            return;
        }
        if (isFieldNavigationSuppressed()) {
            if (isImePlaceholderKey(e) || isConfirmedEnterKey(e) || normalizeNavigationKey(e) === 'Enter') {
                handled = true;
            }
            return;
        }
        if (process(idx, field, e, false)) handled = true;
    }

    function keyup(idx, field, e) {
        if (handled) {
            handled = false;
            return;
        }
        if (isFieldNavigationSuppressed()) return;
        if (process(idx, field, e, true)) handled = true;
    }

    return { items, dests, keydown, keyup, rowTotalFilled };
}

const imeEnterDown = { keyCode: 229, which: 229, key: 'Unidentified', code: 'Enter', repeat: false };
const realEnter = { keyCode: 13, which: 13, key: 'Enter', code: 'Enter', repeat: false };

test('IME 229 with e.code Enter is not a confirmed keydown Enter', () => {
    assert.equal(isImePlaceholderKey(imeEnterDown), true);
    assert.equal(isConfirmedEnterKey(imeEnterDown), false);
    assert.equal(normalizeNavigationKey(imeEnterDown), 'Enter');
    assert.equal(isConfirmedEnterKey(realEnter), true);
});

test('one Android Chrome Enter on invoice advances only to note', () => {
    resetNavClock();
    const nav = makeCashNav();
    nav.keydown(0, 'invoice', imeEnterDown);
    nav.keydown(0, 'invoice', realEnter);
    nav.keyup(0, 'note', realEnter);
    assert.deepEqual(nav.dests, ['note']);
});

test('duplicate Enter after focus moves does not skip note', () => {
    resetNavClock();
    const nav = makeCashNav();
    nav.keydown(0, 'invoice', realEnter);
    nav.keyup(0, 'invoice', realEnter);
    nav.keydown(0, 'note', realEnter);
    nav.keyup(0, 'note', realEnter);
    assert.deepEqual(nav.dests, ['note']);
});

test('empty total Enter does not add a row', () => {
    resetNavClock();
    const nav = makeCashNav(null);
    nav.keydown(0, 'total', realEnter);
    nav.keyup(0, 'total', realEnter);
    assert.deepEqual(nav.dests, []);
    assert.equal(nav.rowTotalFilled(nav.items[0]), false);
});

test('zero total Enter does not add a row', () => {
    resetNavClock();
    const nav = makeCashNav(0);
    nav.keydown(0, 'total', realEnter);
    nav.keyup(0, 'total', realEnter);
    assert.deepEqual(nav.dests, []);
});

test('filled total Enter advances to the next row', () => {
    resetNavClock();
    const nav = makeCashNav(15000);
    nav.keydown(0, 'total', realEnter);
    nav.keyup(0, 'total', realEnter);
    assert.deepEqual(nav.dests, ['next']);
});

test('cash form keeps the Android Enter guards in source', () => {
    assert.match(cashBlade, /isConfirmedEnterKey\(e\)/);
    assert.match(cashBlade, /claimEnterFieldNavigation\(\)/);
    assert.match(cashBlade, /rowTotalFilled/);
    assert.match(cashBlade, /_queueFieldFocus/);
    assert.match(cashBlade, /Do not reset _fieldKeyHandled on every keydown/);
    assert.doesNotMatch(
        cashBlade,
        /fieldKeydown\(idx, field, e\) \{\s*if \(isFieldNavigationSuppressed\(\)\) \{\s*e\.preventDefault\(\);\s*return;\s*\}\s*this\._fieldKeyHandled = false;/,
    );
});
