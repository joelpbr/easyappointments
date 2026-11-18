const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function consent(fetch) {
    const button = {hidden: true, disabled: true};
    const context = {
        App: {Components: {}, Utils: {Url: {siteUrl: path => '/index.php/' + path}}},
        document: {getElementById: id => id === 'patient-consent-open' ? button : null, addEventListener() {}},
        URLSearchParams,
        fetch,
    };
    vm.runInNewContext(fs.readFileSync('assets/js/components/patient_consent.js', 'utf8'), context);
    return {api: context.App.Components.PatientConsent, button};
}

test('opening consent keeps an aged session valid for the concurrent visit request', async () => {
    // Model Session.php: non-AJAX requests rotate an aged session and delete the old ID.
    // Both requests leave the browser with the same cookie before either response arrives.
    const sessions = new Set(['eva-session']);
    const visitCookie = 'eva-session';
    const {api, button} = consent(async (url, options = {}) => {
        assert.match(url, /patient_consents\/form\?customer_id=23451$/);
        const ajax = options.headers?.['X-Requested-With']?.toLowerCase() === 'xmlhttprequest';
        if (!ajax) {
            sessions.delete(visitCookie);
            sessions.add('renewed-session');
        }
        return {ok: true, json: async () => ({current: null})};
    });
    const loading = api.setCustomer(23451);
    assert.ok(sessions.has(visitCookie), 'The concurrent visit must retain its authenticated session');
    await loading;
    assert.equal(button.hidden, false);
    assert.equal(button.disabled, false);
});

test('a previous patient response cannot replace the current consent status', async () => {
    const pending = [];
    const {api, button} = consent(() => new Promise(resolve => pending.push(resolve)));
    const first = api.setCustomer(1);
    const second = api.setCustomer(2);
    pending[1]({ok: true, json: async () => ({current: {id: 10, id_patient_documents: 12}})});
    await second;
    pending[0]({ok: true, json: async () => ({current: null})});
    await first;
    assert.equal(button.hidden, true);
    await api.setCustomer(null);
    assert.equal(button.hidden, true);
});

test('a denied consent request does not enable the signing button', async () => {
    const {api, button} = consent(async () => ({ok: false, status: 403}));
    await api.setCustomer(1);
    assert.equal(button.disabled, true);
    assert.equal(button.hidden, true);
});
