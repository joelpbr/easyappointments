const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function mobileAgenda() {
    const source = fs.readFileSync('assets/js/pages/simple.js', 'utf8');
    const load = source.slice(source.indexOf('    async function load('), source.indexOf('    function goTo('));
    const state = {renders: 0, loading: 0, calls: 0, open: false, data: {appointments: [], blocked_periods: []}};
    const context = {
        URLSearchParams, AbortController, setTimeout, clearTimeout,
        window: {scrollTo() {}, EaLoading: {start: () => {state.loading++;return () => {};}}},
        vars: () => 'csrf',
        siteUrl: path => path,
        isoDate: () => '2026-11-17',
        parseDateTime: value => new Date(value),
        renderProviders() {},
        renderList: () => state.renders++,
        $: () => ({hasAttribute: () => state.open}),
        document: {querySelector: () => null, body: {classList: {add: () => state.loading++, remove() {}}}},
        fetch: async () => {state.calls++; return {ok: true, status: 200, json: async () => state.data};},
    };
    vm.runInNewContext(`let request=0, loading=false, lastRenderedSignature=null;
        let day=new Date('2026-11-17'), appointments=[], blockedPeriods=[];
        ${load}; globalThis.load=load;`, context);
    return {state, load: context.load};
}

test('mobile background refresh leaves unchanged content and loading state alone', async () => {
    const {state, load} = mobileAgenda();
    await load();
    assert.equal(state.renders, 1);
    assert.equal(state.loading, 1);
    await load(true);
    await load(true);
    assert.equal(state.renders, 1);
    assert.equal(state.loading, 1);
    state.data = {appointments: [{id: 1, notes: 'New appointment'}], blocked_periods: []};
    await load(true);
    assert.equal(state.renders, 2);
    state.data.appointments[0].notes = 'Changed details';
    await load(true);
    assert.equal(state.renders, 3);
    state.data.appointments = [];
    await load(true);
    assert.equal(state.renders, 4);
    assert.equal(state.loading, 1);
});

test('mobile polling pauses while appointment details are open', async () => {
    const {state, load} = mobileAgenda();
    state.open = true;
    await load(true);
    assert.equal(state.calls, 0);
});

test('full calendar skips unchanged polls and batches changed events into one redraw', () => {
    const source = fs.readFileSync('assets/js/utils/calendar_default_view.js', 'utf8');
    const refresh = source.slice(source.indexOf('    function refreshCalendarAppointments('), source.indexOf('    function createAppointmentEvents('));
    const state = {removed: 0, added: 0, batches: 0, response: {appointments: [{id: 1}], unavailabilities: [], blocked_periods: []}};
    const context = {
        $: () => ({length: 0, css() {}}),
        moment: value => ({format: () => value}),
        createAppointmentEvents: data => data,
        createUnavailabilityEvents: data => data,
        App: {Http: {Calendar: {getCalendarAppointments: () => ({done(callback) {callback(state.response);return this;}, always(callback) {callback();return this;}})}}, Utils: {CalendarBlockedPeriods: {events: data => data}, CalendarPastTime: {update() {}}}},
        document: {querySelectorAll: () => []},
        fullCalendar: {view: {type: 'dayGridMonth'}, getEventSources: () => [{remove: () => state.removed++}], addEventSource: () => state.added++, batchRendering: callback => {state.batches++;callback();}},
    };
    vm.runInNewContext(`let refreshPending=false, refreshRequest=0, lastEventsSignature=null; const FILTER_TYPE_PROVIDER='provider'; ${refresh}; globalThis.refresh=refreshCalendarAppointments;`, context);
    const run = silent => context.refresh(null, '1', 'provider', '2026-11-17', '2026-11-18', silent);
    run(false);
    run(true);
    assert.equal(state.added, 1);
    assert.equal(state.removed, 1);
    state.response.appointments[0].notes = 'Updated';
    run(true);
    assert.equal(state.added, 2);
    assert.equal(state.batches, 2);
    run(false);
    assert.equal(state.added, 3, 'Manual reload still restores the server state');
});
