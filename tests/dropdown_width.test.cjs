const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/utils/ui.js', 'utf8');
const start = source.indexOf('    function initializeDropdownWidth(');
const end = source.indexOf('\n    /**', start);

function dropdown(options, visible = true) {
    const widths = [];
    const select = {options};
    const button = {isConnected: true, offsetParent: visible ? {} : null};
    const style = {fontFamily: 'Arial', fontSize: '13px', letterSpacing: 'normal'};
    const callbacks = [];
    const context = {
        selectDropdowns: callbacks,
        getComputedStyle: () => style,
        document: {
            createElement: () => ({
                getContext: () => ({
                    measureText: (label) => ({
                        width: [...label].reduce((sum, letter) => sum + (letter === 'W' ? 12 : 4), 0),
                    }),
                }),
            }),
        },
    };
    vm.runInNewContext(source.slice(start, end), context);
    context.initializeDropdownWidth(select, button, {style: {setProperty: (name, width) => widths.push(width)}});
    return {select, button, widths, style, sync: () => callbacks[0].sync()};
}

test('width follows the widest rendered option and stays stable across selection changes', () => {
    const field = dropdown([{text: 'iiiiii'}, {text: 'WWW'}]);
    assert.equal(field.widths.at(-1), '96px');
    field.select.selectedIndex = 1;
    field.sync();
    assert.equal(field.widths.length, 1);
});

test('dynamic options resize the dropdown while hidden options do not enlarge it', () => {
    const field = dropdown([{text: 'ii'}, {text: 'WWWWWW', hidden: true}]);
    assert.equal(field.widths.at(-1), '68px');
    field.select.options.push({text: 'WWWW'});
    field.sync();
    assert.equal(field.widths.at(-1), '108px');
    field.select.options.pop();
    field.sync();
    assert.equal(field.widths.at(-1), '68px');
});

test('hidden modal fields are sized when shown and recomputed when typography changes', () => {
    const field = dropdown([{text: 'WWW'}], false);
    assert.equal(field.widths.length, 0);
    field.button.offsetParent = {};
    field.sync();
    assert.equal(field.widths.at(-1), '96px');
    field.style.letterSpacing = '2px';
    field.sync();
    assert.equal(field.widths.at(-1), '100px');
});
