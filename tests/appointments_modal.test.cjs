const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Execute the actual component and its save handler against a small form adapter.
// This reproduces a locked imported patient with unsupported language and missing contact details.
function form() {
    const fields = new Map();
    const patientIds = ['first-name', 'last-name', 'phone-number', 'customer-notes'];
    const requiredIds = [
        ...patientIds.filter((id) => id !== 'customer-notes'),
        'select-service',
        'select-provider',
        'start-datetime',
        'end-datetime',
        'appointment-duration',
    ];
    let saved;
    function field(id) {
        if (!fields.has(id)) fields.set(id, {id, value: '', props: {}, events: {}});
        return fields.get(id);
    }
    class Query {
        constructor(items) {
            this.items = items;
            this.length = items.length;
        }
        val(value) {
            if (arguments.length) {
                this.items.forEach((f) => (f.value = value));
                return this;
            }
            return this.items[0]?.value ?? null;
        }
        prop(key, value) {
            if (arguments.length > 1) {
                this.items.forEach((f) => (f.props[key] = value));
                return this;
            }
            return this.items[0]?.props[key];
        }
        find(selector) {
            if (selector === '.required') return new Query(requiredIds.map(field));
            if (selector === 'input:not([type=hidden]):not(.btn-check), select, textarea')
                return new Query(patientIds.map(field));
            if (selector === 'input[name="patient-mode"]:checked') return $(field('mode'));
            if (selector.startsWith('#')) return $(selector);
            return new Query([]);
        }
        closest() {
            return this;
        }
        filter() {
            return this;
        }
        is(item) {
            return this.items.includes(item);
        }
        each(fn) {
            this.items.forEach((f, i) => fn(i, f));
            return this;
        }
        on(event, ...args) {
            this.items.forEach((f) => (f.events[event] = args.at(-1)));
            return this;
        }
        text() {
            return this;
        }
        addClass() {
            return this;
        }
        removeClass() {
            return this;
        }
        toggleClass() {
            return this;
        }
        empty() {
            return this;
        }
        append() {
            return this;
        }
        scrollTop() {
            return this;
        }
        trigger() {
            return this;
        }
        modal() {
            return this;
        }
    }
    function $(value) {
        return new Query([typeof value === 'string' ? field(value.replace(/^#/, '')) : value]);
    }
    const App = {
        Components: {},
        Layouts: {Backend: {displayNotification() {}}},
        Utils: {
            UI: {getDateTimePickerValue: ($f) => $f.val()},
            Validation: {phone: (value) => /^\d+$/.test(value)},
        },
        Http: {
            Calendar: {
                checkProviderConflict: (_appointment, success) => success({conflict: false}),
                saveAppointmentWithConflictHandling: (appointment, customer) => (saved = {appointment, customer}),
            },
        },
    };
    const context = {
        App,
        $,
        window: {},
        lang: (key) => key,
        vars: (key) => ({default_language: 'catalan', available_providers: []})[key],
        document: {addEventListener() {}},
        moment: (value) => ({format: () => value}),
    };
    let source = fs.readFileSync('assets/js/components/appointments_modal.js', 'utf8');
    source = source.replace(
        '        resetModal,',
        '        fillCustomer, getCustomerData, addEventListeners, resetModal,',
    );
    context.window.moment = context.moment;
    App.Components.ColorSelection = {getColor: () => ''};
    vm.runInNewContext(source, context);
    const api = App.Components.AppointmentsModal;
    api.addEventListeners();
    $('#select-service').val('1');
    $('#select-provider').val('4');
    $('#mode').val('existing');
    $('#start-datetime').val('2027-01-04 10:00:00');
    $('#end-datetime').val('2027-01-04 10:30:00');
    $('#appointment-duration').val('30');
    return {api, $, save: () => field('save-appointment').events.click(), saved: () => saved};
}

test('booking a locked imported patient ignores missing patient fields and submits only their ID', () => {
    const f = form();
    f.api.fillCustomer({id: 42, first_name: 'Patient', last_name: 'Test', phone_number: '', language: 'english'});
    assert.equal(f.api.validateAppointmentForm(), true);
    f.save();
    assert.equal(f.saved().customer, null);
    assert.equal(f.saved().appointment.id_users_customer, 42);
    assert.equal(f.saved().appointment.notes, '');
});
test('appointment fields remain required even when the patient is locked', () => {
    const f = form();
    f.api.fillCustomer({id: 42, language: 'english'});
    f.$('#select-provider').val('');
    assert.equal(f.api.validateAppointmentForm(), false);
    f.save();
    assert.equal(f.saved(), undefined);
});
test('new or explicitly edited patients still require name, surname and telephone', () => {
    const f = form();
    assert.equal(f.api.validateAppointmentForm(), false);
    f.$('#first-name').val('New');
    f.$('#last-name').val('Patient');
    f.$('#phone-number').val('612345678');
    assert.equal(f.api.validateAppointmentForm(), true);
    const payload = f.api.getCustomerData();
    assert.equal(payload.language, 'catalan');
    assert.equal(payload.first_name, 'New');
});
test('editing basic patient data preserves the secondary data stored on the patient record', () => {
    const f = form();
    f.api.fillCustomer({
        id: 42,
        first_name: 'Patient',
        last_name: 'Test',
        phone_number: '612345678',
        language: 'spanish',
        email: 'patient@example.test',
        address: 'Original address',
        city: 'Original city',
        zip_code: '08201',
        notes: 'Original notes',
    });
    f.$('#first-name').prop('readonly', false).val('Changed');
    const payload = f.api.getCustomerData();
    assert.equal(payload.first_name, 'Changed');
    assert.equal(payload.id, 42);
    // Omitted columns are retained by the model's partial UPDATE, including when editing an existing visit.
    assert.equal(payload.notes, 'Original notes');
    for (const key of ['language', 'email', 'address', 'city', 'zip_code'])
        assert.equal(Object.hasOwn(payload, key), false);
});
test('appointment and patient comments are loaded and saved separately', () => {
    const f = form();
    f.api.fillCustomer({
        id: 42,
        first_name: 'Patient',
        last_name: 'Test',
        phone_number: '612345678',
        notes: 'Patient history',
    });
    assert.equal(f.$('#customer-notes').val(), 'Patient history');
    f.$('#first-name').prop('readonly', false);
    f.$('#customer-notes').val('Patient comment');
    f.$('#appointment-notes').val('Appointment comment');
    f.save();
    assert.equal(f.saved().appointment.notes, 'Appointment comment');
    assert.equal(f.saved().customer.notes, 'Patient comment');
    assert.equal(f.saved().customer.id, 42);
});
test('patient comments are optional and available when creating a patient', () => {
    const f = form();
    f.$('#first-name').val('New');
    f.$('#last-name').val('Patient');
    f.$('#phone-number').val('612345678');
    assert.equal(f.api.validateAppointmentForm(), true);
    assert.equal(f.api.getCustomerData().notes, '');
    f.$('#customer-notes').val('New patient comment');
    assert.equal(f.api.getCustomerData().notes, 'New patient comment');
});
test('the appointment form keeps both original comment fields without restoring language or address fields', () => {
    const view = fs.readFileSync('application/views/components/appointments_modal.php', 'utf8');
    for (const id of ['language', 'email', 'address', 'city', 'zip-code']) assert.ok(!view.includes(`id="${id}"`));
    assert.ok(view.includes('id="appointment-notes"'));
    assert.ok(view.includes('id="customer-notes"'));
    assert.ok(view.includes("lang('appointment_comment')"));
    assert.ok(view.includes("lang('administrative_notes')"));
});
