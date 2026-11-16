const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/utils/calendar_table_view.js'), 'utf8');
function functionSource(name) {
    const start = source.indexOf(`    function ${name}(`);
    assert(start >= 0);
    return source.slice(start, source.indexOf('\n    }', start) + 6);
}

function columns(role, shared, selected = ['4', '5', '6']) {
    const rendered = [];
    const values = {
        role_slug: role, shared_clinic_access: shared, user_id: 4,
        secretary_providers: [5],
        available_providers: [4, 5, 6].map(id => ({id, services: [1]})),
    };
    const element = {appendTo() {return this;}, data() {return this;}};
    const context = {
        vars: key => values[key], $: () => element,
        $filterProvider: {val: () => selected}, $filterService: {val: () => ''},
        App: {Layouts: {Backend: {DB_SLUG_ADMIN: 'admin', DB_SLUG_PROVIDER: 'provider', DB_SLUG_SECRETARY: 'secretary'}}, Utils: {Date: {format: () => ''}}},
        createProviderColumn: (_column, _date, provider) => rendered.push(provider.id),
    };
    vm.runInNewContext(`${functionSource('getAvailableProviders')}\n${functionSource('createDateColumn')}\ncreateDateColumn({}, new Date(), {});`, context);
    return rendered;
}

for (const role of ['admin', 'provider', 'secretary']) {
    test(`${role} renders all three calendar columns with shared access`, () => {
        assert.deepEqual(columns(role, true), [4, 5, 6]);
    });
    test(`${role} respects the selected provider when rendering shared calendars`, () => {
        assert.deepEqual(columns(role, true, ['6']), [6]);
    });
}
test('legacy scopes still restrict calendar columns when shared access is disabled', () => {
    assert.deepEqual(columns('provider', false), [4]);
    assert.deepEqual(columns('secretary', false), [5]);
});
