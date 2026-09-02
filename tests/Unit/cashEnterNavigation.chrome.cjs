'use strict';

/**
 * Runs cash Enter-nav cases inside system Google Chrome (desktop UA, real
 * KeyboardEvent). Used to catch claim-window / IME helper regressions that
 * Node's synthetic event objects would miss.
 */

const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const appBlade = fs.readFileSync(
    path.join(__dirname, '../../resources/views/layouts/app.blade.php'),
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

const helpers = [
    'normalizeNavigationKey',
    'isImePlaceholderKey',
    'isConfirmedEnterKey',
    'suppressFieldNavigation',
    'isFieldNavigationSuppressed',
    'enterFieldNavClaimMs',
    'claimEnterFieldNavigation',
].map((name) => extractFunction(appBlade, name)).join('\n\n');

const html = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>cash enter nav chrome</title></head>
<body>
<pre id="out">pending</pre>
<script>
${helpers}

function makeCashNav(total) {
    const items = [{ total: total }];
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
        } else return false;
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

    return { dests, keydown, keyup };
}

function desktopEnter() {
    return new KeyboardEvent('keydown', {
        key: 'Enter',
        code: 'Enter',
        keyCode: 13,
        which: 13,
        bubbles: true,
        cancelable: true,
    });
}

const results = [];
function check(name, ok, detail) {
    results.push({ name, ok: !!ok, detail: detail || null });
}

window._suppressFieldNavUntil = 0;
check('desktop claim ms is 0', enterFieldNavClaimMs() === 0, String(enterFieldNavClaimMs()));
check('confirmed Enter from Chrome KeyboardEvent', isConfirmedEnterKey(desktopEnter()) === true);

const one = makeCashNav(null);
one.keydown(0, 'invoice', desktopEnter());
one.keyup(0, 'invoice', desktopEnter());
check('one desktop Enter invoice → note only', JSON.stringify(one.dests) === JSON.stringify(['note']), JSON.stringify(one.dests));

window._suppressFieldNavUntil = 0;
const seq = makeCashNav(null);
seq.keydown(0, 'invoice', desktopEnter());
seq.keyup(0, 'invoice', desktopEnter());
seq.keydown(0, 'note', desktopEnter());
seq.keyup(0, 'note', desktopEnter());
check('sequential desktop Enters invoice → note → total', JSON.stringify(seq.dests) === JSON.stringify(['note', 'total']), JSON.stringify(seq.dests));

window._suppressFieldNavUntil = 0;
const empty = makeCashNav(null);
empty.keydown(0, 'total', desktopEnter());
empty.keyup(0, 'total', desktopEnter());
check('empty total does not add a row', JSON.stringify(empty.dests) === JSON.stringify([]), JSON.stringify(empty.dests));

window._suppressFieldNavUntil = 0;
const filled = makeCashNav(15000);
filled.keydown(0, 'total', desktopEnter());
filled.keyup(0, 'total', desktopEnter());
check('filled total advances next row', JSON.stringify(filled.dests) === JSON.stringify(['next']), JSON.stringify(filled.dests));

const failed = results.filter((r) => !r.ok);
document.getElementById('out').textContent = JSON.stringify({
    ua: navigator.userAgent,
    claimMs: enterFieldNavClaimMs(),
    passed: failed.length === 0,
    results: results,
}, null, 2);
</script>
</body>
</html>
`;

const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cash-enter-nav-'));
const file = path.join(dir, 'index.html');
fs.writeFileSync(file, html);

const chrome = process.env.CHROME_PATH
    || ['/usr/bin/google-chrome-stable', '/usr/bin/google-chrome', '/usr/local/bin/google-chrome']
        .find((bin) => fs.existsSync(bin));

if (!chrome) {
    console.error('google-chrome not found');
    process.exit(2);
}

const launched = spawnSync(chrome, [
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--allow-file-access-from-files',
    '--virtual-time-budget=4000',
    '--dump-dom',
    `file://${file}`,
], {
    encoding: 'utf8',
    timeout: 20000,
    maxBuffer: 4 * 1024 * 1024,
});

if (launched.status !== 0) {
    console.error(launched.stderr || launched.stdout);
    process.exit(launched.status || 1);
}

const match = launched.stdout.match(/<pre id="out">([\s\S]*?)<\/pre>/);
if (!match) {
    console.error('Chrome dump-dom did not include #out');
    console.error(launched.stdout.slice(0, 1500));
    process.exit(1);
}

const decoded = match[1]
    .replace(/&quot;/g, '"')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');

const payload = JSON.parse(decoded);
console.log(JSON.stringify(payload, null, 2));

if (!payload.passed) {
    process.exit(1);
}
