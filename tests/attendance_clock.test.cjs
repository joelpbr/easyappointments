const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {webcrypto} = require('node:crypto');
const source = fs.readFileSync('assets/js/pages/attendance.js', 'utf8');
function functionSource(name) {
    const start = source.search(new RegExp('    (?:async )?function ' + name + '\\('));
    assert.ok(start >= 0);
    return source.slice(start, source.indexOf('\n    }', start) + 6);
}
function setup(fetch) {
    const messages = [];
    let reloads = 0;
    const button = {disabled: false};
    const context = {
        // HTTP exposes getRandomValues but not randomUUID.
        crypto: {getRandomValues: bytes => webcrypto.getRandomValues(bytes)},
        URLSearchParams, fetch, url: path => '/attendance/' + path,
        vars: () => 'csrf-value', lang: key => key,
        feedback: (message, error) => messages.push({message, error}),
        load: async () => {reloads++; button.disabled = false;},
        document: {querySelectorAll: () => [button]},
    };
    vm.runInNewContext(`let busy = false; const data = {current: {id: 12, revision: 3}};
        ${functionSource('api')}
        ${functionSource('createRequestKey')}
        ${functionSource('clock')}
        globalThis.clock = clock; globalThis.api = api;`, context);
    return {context, messages, button, reloads: () => reloads};
}

test('all four clock actions work on HTTP and submit distinct valid UUIDs', async () => {
    const requests = [];
    const {context, messages, reloads} = setup(async (url, options) => {
        requests.push({url, options});
        return {ok: true, json: async () => ({success: true})};
    });
    for (const action of ['in', 'pause', 'resume', 'out']) await context.clock(action);
    assert.equal(requests.length, 4);
    const keys = requests.map(({url, options}, index) => {
        assert.equal(url, '/attendance/clock');
        assert.equal(options.method, 'POST');
        assert.equal(options.headers['X-Requested-With'], 'XMLHttpRequest');
        assert.equal(options.body.get('action'), ['in', 'pause', 'resume', 'out'][index]);
        assert.equal(options.body.get('csrf_token'), 'csrf-value');
        assert.equal(options.body.get('entry_id'), '12');
        assert.equal(options.body.get('revision'), '3');
        const key = options.body.get('request_key');
        assert.match(key, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
        return key;
    });
    assert.equal(new Set(keys).size, 4);
    assert.ok(messages.every(m => !m.error));
    assert.equal(reloads(), 4);
});

test('a second tap while clocking does not submit another request', async () => {
    let release;
    let calls = 0;
    const {context, button} = setup(() => {calls++; return new Promise(resolve => {release = resolve;});});
    const first = context.clock('in');
    assert.equal(button.disabled, true);
    await context.clock('in');
    assert.equal(calls, 1);
    release({ok: true, json: async () => ({success: true})});
    await first;
    assert.equal(button.disabled, false);
});

test('a server rejection remains visible and permits another attempt', async () => {
    const {context, messages, reloads} = setup(async () => ({ok: false, json: async () => ({message: 'stale entry'})}));
    await context.clock('out');
    await context.clock('out');
    assert.equal(messages.length, 2);
    assert.ok(messages.every(m => m.error && m.message === 'stale entry'));
    assert.equal(reloads(), 2);
});

test('background attendance refresh is marked AJAX to keep concurrent sessions valid', async () => {
    let headers;
    const {context} = setup(async (_url, options) => {headers = options.headers; return {ok: true, json: async () => ({})};});
    await context.api('data');
    assert.equal(headers['X-Requested-With'], 'XMLHttpRequest');
});
