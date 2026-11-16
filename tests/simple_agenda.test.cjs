const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/pages/simple.js', 'utf8');

function rememberedProvider(initial, {writeBlocked = false, readBlocked = false} = {}) {
    const storage = new Map(Object.entries(initial));
    const context = {
        vars: (key) => ({providers: [{id: 4}, {id: 5}], appointment_statuses: []})[key],
        document: {addEventListener() {}},
        localStorage: {
            getItem(key) {
                if (readBlocked) throw new Error('Storage unavailable');
                return storage.get(key) ?? null;
            },
            setItem(key, value) {
                if (writeBlocked) throw new Error('Storage is read-only');
                storage.set(key, value);
            },
            removeItem(key) {
                if (writeBlocked) throw new Error('Storage is read-only');
                storage.delete(key);
            },
        },
    };
    // Execute the actual page, recording its restored filter before DOM initialization.
    vm.runInNewContext(
        source.replace(
            'let providerId = readProvider();',
            'let providerId = readProvider(); globalThis.selectedProvider = providerId;',
        ),
        context,
    );
    return {storage, selected: context.selectedProvider};
}

test('the remembered provider survives the rename and moves to the new storage key', () => {
    const result = rememberedProvider({'ea-mobile-provider': '4'});
    assert.equal(result.selected, 4);
    assert.equal(result.storage.get('ea-simple-provider'), '4');
    assert.equal(result.storage.has('ea-mobile-provider'), false);
});

test('the current filter takes precedence over an old stored value', () => {
    const result = rememberedProvider({'ea-simple-provider': '5', 'ea-mobile-provider': '4'});
    assert.equal(result.selected, 5);
});

test('a removed or inaccessible provider is not restored from old preferences', () => {
    assert.equal(rememberedProvider({'ea-mobile-provider': '999'}).selected, null);
});

test('restricted storage does not prevent the agenda from opening or restoring a readable preference', () => {
    assert.equal(rememberedProvider({'ea-mobile-provider': '4'}, {writeBlocked: true}).selected, 4);
    assert.equal(rememberedProvider({}, {readBlocked: true}).selected, null);
});

test('renamed DOM targets, including call and WhatsApp actions, exist in the view or generated detail sheet', () => {
    const view = fs.readFileSync('application/views/pages/simple.php', 'utf8');
    const ids = new Set([...`${view}\n${source}`.matchAll(/\bid="([\w-]+)"/g)].map((match) => match[1]));
    const targets = [
        ...[...source.matchAll(/\$\('([^']+)'\)/g)].map((match) => match[1]),
        ...[...source.matchAll(/querySelector\('#([^']+)'\)/g)].map((match) => match[1]),
    ];
    for (const id of targets) assert.ok(ids.has(id), `Missing DOM target: ${id}`);
    assert.ok(targets.includes('simple-call'));
    assert.ok(targets.includes('simple-whatsapp'));
});

test('all languages include every renamed agenda label', () => {
    const view = fs.readFileSync('application/views/pages/simple.php', 'utf8');
    const keys = new Set([...`${view}\n${source}`.matchAll(/lang\('(simple_[^']+)'\)/g)].map((match) => match[1]));
    keys.add('simple_version');
    for (const language of fs
        .readdirSync('application/language', {withFileTypes: true})
        .filter((item) => item.isDirectory())) {
        const file = `application/language/${language.name}/translations_lang.php`;
        if (!fs.existsSync(file)) continue;
        const translations = fs.readFileSync(file, 'utf8');
        for (const key of keys)
            assert.ok(translations.includes(`['${key}']`), `Missing ${language.name} label: ${key}`);
        assert.ok(!translations.includes("['mobile_"), `Old agenda translation keys in ${language.name}`);
    }
});

function visitMenu(clinicalAccess, appointment) {
    class Element {
        constructor(tag = 'div') {
            this.tag = tag;
            this.children = [];
            this.events = {};
            this.textContent = '';
            this.queries = new Map();
        }
        append(...children) {
            this.children.push(...children);
        }
        setAttribute() {}
        addEventListener(event, handler) {
            this.events[event] = handler;
        }
        showModal() {
            this.open = true;
        }
        close() {
            this.open = false;
        }
        querySelector(selector) {
            if (!this.queries.has(selector)) this.queries.set(selector, new Element('a'));
            return this.queries.get(selector);
        }
    }
    const elements = new Map(
        ['simple-sheet-content', 'simple-day-date', 'simple-sheet'].map((id) => [id, new Element()]),
    );
    let opened;
    const context = {
        vars: (key) => ({providers: [], appointment_statuses: [], clinical_records: clinicalAccess})[key],
        lang: (key) => key,
        localStorage: {getItem: () => null},
        document: {
            addEventListener() {},
            getElementById: (id) => elements.get(id),
            createElement: (tag) => new Element(tag),
        },
        App: {
            Components: {
                ClinicalRecordModal: {
                    open(id, options) {
                        opened = {id, options, sheetClosed: !elements.get('simple-sheet').open};
                    },
                },
            },
        },
    };
    vm.runInNewContext(
        source.replace(
            "document.addEventListener('DOMContentLoaded', initialize);",
            'globalThis.openVisit = openSheet;',
        ),
        context,
    );
    context.openVisit(appointment);
    const button = elements.get('simple-sheet-content').children.find((item) => item.tag === 'button');
    const contact = elements
        .get('simple-sheet-content')
        .children.find((item) => item.className === 'dropdown ea-simple-contact');
    return {button, contact, open: () => button.events.click(), result: () => opened};
}

const noPhoneVisit = {
    id: 51,
    id_users_customer: 42,
    customer: {id: 42},
    start_datetime: '2026-11-16 10:00:00',
    end_datetime: '2026-11-16 10:30:00',
};

test('Fitxa opens the selected visit without leaving the agenda, including patients without a phone', () => {
    const menu = visitMenu(true, noPhoneVisit);
    assert.ok(menu.button);
    menu.open();
    assert.equal(menu.result().id, 42);
    assert.equal(menu.result().options.appointmentId, 51);
    assert.equal(typeof menu.result().options.onChange, 'function');
    assert.equal(menu.result().sheetClosed, true);
});

test('clinical access is hidden for other roles and appointments without a patient', () => {
    assert.equal(visitMenu(false, noPhoneVisit).button, undefined);
    assert.equal(visitMenu(true, {...noPhoneVisit, id_users_customer: null, customer: {}}).button, undefined);
});

test('contact actions sit inside Contacta independently of clinical access and retain their confirmation handlers', () => {
    const menu = visitMenu(false, {...noPhoneVisit, customer: {id: 42, phone_number: '612345678'}});
    assert.equal(menu.button, undefined);
    assert.ok(menu.contact);
    assert.match(menu.contact.innerHTML, /data-bs-toggle="dropdown"/);
    assert.match(menu.contact.innerHTML, /class="dropdown-menu w-100"/);
    assert.equal(menu.contact.querySelector('#simple-contact').textContent, 'simple_contact');
    assert.equal(menu.contact.querySelector('#simple-call').href, 'tel:612345678');
    assert.equal(menu.contact.querySelector('#simple-whatsapp').href, 'https://wa.me/34612345678');
    assert.equal(typeof menu.contact.querySelector('#simple-call').events.click, 'function');
    assert.equal(typeof menu.contact.querySelector('#simple-whatsapp').events.click, 'function');
    assert.equal(visitMenu(true, noPhoneVisit).contact, undefined);
});
