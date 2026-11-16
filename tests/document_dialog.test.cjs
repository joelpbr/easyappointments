const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/components/document_dialog.js', 'utf8');

function labelIn(language) {
    const context = {App: {Components: {}}, vars: (key) => ({language})[key], window: {}, WeakMap};
    vm.runInNewContext(source, context);
    return context.App.Components.DocumentDialog.label;
}

test('a catalog label is shown in the language of the user', () => {
    assert.equal(labelIn('spanish')({catalan: 'Recepta', spanish: 'Receta'}), 'Receta');
});

test('a document pack without the language of the user shows its first language', () => {
    assert.equal(labelIn('catalan')({english: 'Prescription', french: 'Ordonnance'}), 'Prescription');
});

test('a missing label is an empty text, not an error', () => {
    assert.equal(labelIn('catalan')(undefined), '');
    assert.equal(labelIn('catalan')({}), '');
});
