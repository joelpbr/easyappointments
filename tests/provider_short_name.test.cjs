const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/utils/ui.js', 'utf8');
const start = source.indexOf('    function getProviderDisplayName(');
const end = source.indexOf('\n    }', start) + '\n    }'.length;
const context = {};
vm.runInNewContext(source.slice(start, end), context);

const simpleSource = fs.readFileSync('assets/js/pages/simple.js', 'utf8');
const simpleContext = {
    vars: () => [],
    localStorage: {getItem: () => null},
    document: {addEventListener() {}},
    App: {Utils: {UI: context}},
};
vm.runInNewContext(
    simpleSource.replace(
        "document.addEventListener('DOMContentLoaded', initialize);",
        'globalThis.displayProvider = providerName; globalThis.displayPatient = fullName;',
    ),
    simpleContext,
);

test('simplified agenda uses short professional names while retaining full patient names', () => {
    const person = {first_name: 'Maria', last_name: 'Garcia', short_name: 'Mari'};
    assert.equal(simpleContext.displayProvider(person), 'Mari');
    assert.equal(simpleContext.displayPatient(person), 'Maria Garcia');
    assert.equal(simpleContext.displayProvider({...person, short_name: ''}), 'Maria Garcia');
    assert.equal(simpleContext.displayProvider(null), '');
});

test('professional pickers prefer trimmed short names without changing the professional record', () => {
    const provider = {first_name: 'Maria', last_name: 'Garcia Lopez', short_name: ' Mari '};
    assert.equal(context.getProviderDisplayName(provider), 'Mari');
    assert.equal(provider.first_name, 'Maria');
    assert.equal(provider.last_name, 'Garcia Lopez');
});

test('professional pickers fall back for older records and cleared short names', () => {
    for (const short_name of [undefined, null, '', '   ']) {
        assert.equal(
            context.getProviderDisplayName({first_name: 'Maria', last_name: 'Garcia', short_name}),
            'Maria Garcia',
        );
    }
    assert.equal(context.getProviderDisplayName({first_name: 'Maria', last_name: null}), 'Maria');
});
