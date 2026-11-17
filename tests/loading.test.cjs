const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function setup() {
    const timers = new Map();
    let id = 0;
    const target = {
        tagName: 'OL',
        attributes: {},
        children: [],
        setAttribute(k, v) {
            this.attributes[k] = v;
        },
        removeAttribute(k) {
            delete this.attributes[k];
        },
        append(node) {
            this.children.push(node);
        },
    };
    const context = {
        window: {},
        setTimeout(fn) {
            timers.set(++id, fn);
            return id;
        },
        clearTimeout(id) {
            timers.delete(id);
        },
        document: {
            createElement(tag) {
                return {
                    tag,
                    setAttribute() {},
                    remove() {
                        target.children = target.children.filter((node) => node !== this);
                    },
                };
            },
        },
    };
    vm.runInNewContext(fs.readFileSync('assets/js/utils/loading.js', 'utf8'), context);
    return {
        target,
        start: context.window.EaLoading.start,
        flush() {
            for (const fn of timers.values()) fn();
            timers.clear();
        },
    };
}
test('fast requests never insert a skeleton and clear busy state', () => {
    const {target, start, flush} = setup();
    const finish = start(target, true);
    finish();
    flush();
    assert.equal(target.children.length, 0);
    assert.equal(target.attributes['aria-busy'], undefined);
});
test('slow empty loads use list-safe placeholders and remove them when complete', () => {
    const {target, start, flush} = setup();
    const finish = start(target, true);
    flush();
    assert.equal(target.children[0].tag, 'li');
    assert.equal(target.attributes['aria-busy'], 'true');
    finish();
    assert.equal(target.children.length, 0);
});
test('existing content gets no placeholder, and an old completion cannot end a newer load', () => {
    const {target, start, flush} = setup();
    const old = start(target, false);
    const current = start(target, false);
    old();
    flush();
    assert.equal(target.attributes['aria-busy'], 'true');
    assert.equal(target.children.length, 0);
    current();
    assert.equal(target.attributes['aria-busy'], undefined);
});
