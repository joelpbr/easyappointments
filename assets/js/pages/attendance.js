document.addEventListener('DOMContentLoaded', () => {
    const $id = (id) => document.getElementById(id);
    const admin = vars('attendance_admin');
    const employees = vars('attendance_employees');
    let data = null,
        busy = false,
        loadVersion = 0;
    const modal = new bootstrap.Modal($id('att-modal'));
    const duration = (seconds) => {
        seconds = Math.max(0, Math.floor(seconds));
        return `${Math.floor(seconds / 3600)}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    };
    const local = (value) => (value ? moment.utc(value).tz('Europe/Madrid').format('DD/MM/YYYY HH:mm:ss Z') : '—');
    const element = (tag, text, cls = '') => {
        const el = document.createElement(tag);
        el.textContent = text ?? '';
        el.className = cls;
        return el;
    };
    const button = (label, handler, cls = 'btn btn-sm btn-outline-primary') => {
        const el = element('button', label, cls);
        el.type = 'button';
        el.addEventListener('click', handler);
        return el;
    };
    const feedback = (text, error = false) => {
        const box = $id('att-feedback');
        box.textContent = text;
        box.className = `alert alert-${error ? 'danger' : 'success'}`;
        box.hidden = !text;
    };
    const employee = () => Number($id('att-employee').value);
    const month = () => $id('att-month').value;
    const url = (path, params = {}) =>
        App.Utils.Url.siteUrl('attendance/' + path) +
        '?' +
        new URLSearchParams({employee_id: employee(), month: month(), ...params});

    async function api(path, payload) {
        const response = await fetch(
            url(path),
            payload
                ? {
                      method: 'POST',
                      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                      body: new URLSearchParams({csrf_token: vars('csrf_token'), ...payload}),
                  }
                : {},
        );
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error(lang('att_failed'));
        }
        if (!response.ok || result.success === false) throw new Error(result.message || lang('att_failed'));
        return result;
    }
    async function change(operation, payload) {
        if (busy) return false;
        busy = true;
        $id('att-modal-save').disabled = true;
        try {
            await api('change', {operation, payload: JSON.stringify(payload)});
            modal.hide();
            feedback(lang('att_saved'));
            await load();
            return true;
        } catch (error) {
            feedback(error.message, true);
            $id('att-modal-error')?.replaceChildren(element('p', error.message, 'text-danger'));
            return false;
        } finally {
            busy = false;
            $id('att-modal-save').disabled = false;
        }
    }
    async function load() {
        if (!employee() || !month()) return;
        const version = ++loadVersion;
        try {
            const result = await api('data');
            if (version !== loadVersion) return;
            data = result;
            render();
        } catch (error) {
            feedback(error.message, true);
            $id('att-clock-actions')?.replaceChildren();
        }
    }

    function openModal(title, save) {
        $id('att-modal-title').textContent = title;
        $id('att-modal-body').replaceChildren();
        const error = element('div');
        error.id = 'att-modal-error';
        error.setAttribute('role', 'alert');
        $id('att-modal-body').append(error);
        $id('att-modal-save').hidden = !save;
        $id('att-modal-save').onclick = save || null;
        modal.show();
    }
    function field(label, type = 'text', value = '') {
        const wrapper = element('label', '', 'd-block mb-3');
        wrapper.append(element('span', label, 'form-label'));
        const input = document.createElement(type === 'textarea' ? 'textarea' : 'input');
        if (type !== 'textarea') input.type = type;
        input.className = 'form-control';
        input.value = value;
        wrapper.append(input);
        $id('att-modal-body').append(wrapper);
        return input;
    }
    async function clock(action) {
        if (busy) return;
        busy = true;
        document.querySelectorAll('#att-clock-actions button').forEach((b) => (b.disabled = true));
        try {
            await api('clock', {
                action,
                revision: data.current?.revision || 0,
                entry_id: data.current?.id || 0,
                request_key: crypto.randomUUID(),
            });
            feedback(lang('att_clock_saved'));
        } catch (error) {
            feedback(error.message, true);
        } finally {
            busy = false;
            await load();
        }
    }
    function render() {
        const current = data.current;
        if (!admin) {
            const paused = current?.segments.at(-1).kind === 'break';
            $id('att-state').textContent = lang(!current ? 'att_off' : paused ? 'att_paused' : 'att_working');
            $id('att-since').textContent = current
                ? lang('att_start') + ': ' + local(current.start_at)
                : lang('att_ready');
            const actions = $id('att-clock-actions');
            actions.replaceChildren();
            const choices = !current
                ? [['in', 'att_in']]
                : paused
                  ? [
                        ['resume', 'att_resume'],
                        ['out', 'att_out'],
                    ]
                  : [
                        ['pause', 'att_pause'],
                        ['out', 'att_out'],
                    ];
            choices.forEach(([action, label], index) =>
                actions.append(
                    button(
                        lang(label),
                        () => clock(action),
                        'btn btn-lg ' + (index ? 'btn-outline-primary' : 'btn-primary'),
                    ),
                ),
            );
            $id('att-open-warning').hidden =
                !current || moment.utc(data.server_now).diff(moment.utc(current.start_at), 'hours') < 16;
        } else {
            $id('att-team').replaceChildren();
            data.team.forEach((person) => {
                const col = element('div', '', 'col-md-4');
                const card = button(
                    '',
                    () => {
                        $id('att-employee').value = person.id;
                        load();
                    },
                    'card card-body text-start w-100',
                );
                card.append(
                    element('strong', person.name),
                    element(
                        'span',
                        lang(
                            person.current
                                ? person.current.segments.at(-1).kind === 'break'
                                    ? 'att_paused'
                                    : 'att_working'
                                : 'att_off',
                        ),
                    ),
                    element('small', `${lang('att_requests')}: ${person.pending}`),
                );
                col.append(card);
                $id('att-team').append(col);
            });
        }
        $id('att-summary').replaceChildren();
        for (const [key, value] of [
            ['counted', data.summary.counted_seconds],
            ['paid', data.summary.totals.paid],
            ['unpaid', data.summary.totals.unpaid],
            ['unknown', data.summary.totals.unknown],
        ]) {
            const col = element('div', '', 'col-6 col-lg-3');
            const card = element('div', '', 'card card-body');
            card.append(element('small', lang('att_' + key), 'text-muted'), element('strong', duration(value), 'fs-4'));
            col.append(card);
            $id('att-summary').append(col);
        }
        $id('att-provisional').hidden = !data.summary.provisional;
        $id('att-review').hidden = !data.review_flags.length;
        $id('att-review').replaceChildren(element('strong', lang('att_review_hint')));
        data.review_flags.forEach((flag) =>
            $id('att-review').append(element('div', `${flag.date} · ${lang('att_flag_' + flag.type)}`)),
        );
        $id('att-entries').replaceChildren();
        data.entries.forEach((entry) => {
            const totals = {work: 0, break: 0};
            entry.segments.forEach((s) => {
                totals[s.kind] += Math.max(
                    0,
                    moment.utc(s.end || data.server_now).diff(moment.utc(s.start), 'seconds'),
                );
            });
            const tr = element('tr');
            [
                local(entry.start_at),
                local(entry.end_at),
                duration(totals.work),
                duration(totals.break),
                lang(entry.voided ? 'att_voided' : entry.end_at ? 'att_closed' : 'att_open') +
                    (entry.revision > 1 ? ` · v${entry.revision}` : ''),
            ].forEach((v) => tr.append(element('td', v)));
            const actions = element('td', '', 'text-nowrap');
            actions.append(button(lang('att_history'), () => history(entry.id)));
            if (admin)
                actions.append(button(lang('att_correct'), () => edit(entry), 'btn btn-sm btn-outline-primary ms-1'));
            tr.append(actions);
            $id('att-entries').append(tr);
        });
        if (!data.entries.length) {
            const tr = element('tr');
            const td = element('td', lang('att_empty'), 'text-muted py-4');
            td.colSpan = 6;
            tr.append(td);
            $id('att-entries').append(tr);
        }
        $id('att-days').replaceChildren();
        data.summary.days.forEach((day) => {
            const tr = element('tr');
            [day.date, ...['work', 'paid', 'unpaid', 'unknown'].map((k) => duration(day[k]))].forEach((v) =>
                tr.append(element('td', v)),
            );
            $id('att-days').append(tr);
        });
        $id('att-requests').replaceChildren();
        data.requests.forEach((request) => {
            const card = element('div', '', 'border rounded p-3 mb-2');
            card.append(
                element('strong', `${request.work_date} · ${lang('att_' + request.status)}`),
                element('p', request.message, 'mb-1'),
            );
            if (request.resolution) card.append(element('p', request.resolution, 'text-muted mb-0'));
            if (admin && request.status === 'pending')
                card.append(
                    button(lang('att_resolve'), () => {
                        let resolution;
                        openModal(lang('att_resolve'), () =>
                            change('resolve', {id: request.id, resolution: resolution.value}),
                        );
                        $id('att-modal-body').append(element('p', lang('att_resolve_hint')));
                        resolution = field(lang('att_resolution'), 'textarea');
                        resolution.maxLength = 2000;
                    }),
                );
            $id('att-requests').append(card);
        });
        if (!data.requests.length) $id('att-requests').append(element('p', lang('att_none'), 'text-muted'));
        $id('att-reports').replaceChildren();
        data.reports.forEach((report) => {
            const row = element('div', '', 'border rounded p-3 mb-2 d-flex flex-wrap gap-2 align-items-center');
            const a = element('a', `#${report.id} · ${local(report.issued_at)}`, 'btn btn-outline-primary');
            a.href = url('report', {id: report.id});
            a.target = '_blank';
            a.rel = 'noopener';
            row.append(a);
            row.append(
                element(
                    'span',
                    report.delivered_at
                        ? lang('att_delivered') + ' · ' + local(report.delivered_at)
                        : lang('att_not_delivered'),
                ),
            );
            if (admin && !report.delivered_at)
                row.append(
                    button(lang('att_mark_delivered'), () => {
                        let note;
                        openModal(lang('att_mark_delivered'), () =>
                            change('deliver', {id: report.id, note: note.value}),
                        );
                        note = field(lang('att_delivery_note'), 'textarea');
                        note.maxLength = 2000;
                    }),
                );
            $id('att-reports').append(row);
        });
        if (!data.reports.length) $id('att-reports').append(element('p', lang('att_none'), 'text-muted'));
        for (const [id, format] of [
            ['att-csv', 'csv'],
            ['att-print', 'print'],
            ['att-json', 'json'],
        ])
            $id(id).href = url('export', {format});
        if (admin) {
            $id('att-contract').value = data.employee.contract_type;
            $id('att-weekly').value = data.employee.weekly_minutes ?? '';
            $id('att-agreement').value = data.employee.agreement;
            $id('att-pause-policy').value = data.employee.pause_policy;
            $id('att-ordinary').value = duration(data.summary.counted_seconds);
            $id('att-complementary').value = '0:00:00';
            $id('att-overtime').value = '0:00:00';
        }
    }

    async function history(id) {
        try {
            const response = await fetch(url('history', {entry_id: id}));
            const events = await response.json();
            if (!response.ok || events.success === false) throw new Error(events.message || lang('att_failed'));
            showHistory(events);
        } catch (error) {
            feedback(error.message, true);
        }
    }
    function showHistory(events) {
        openModal(lang('att_history'), null);
        events.forEach((event) => {
            const card = element('div', '', 'border rounded p-3 mb-3');
            card.append(
                element(
                    'strong',
                    `${local(event.created_at)} · ${event.actor_name} · ${lang('att_event_' + event.action)}`,
                ),
                element('p', event.reason),
            );
            for (const [label, snapshot] of [
                ['att_before', event.before],
                ['att_after', event.after],
            ]) {
                if (!snapshot?.segments) continue;
                card.append(element('h6', lang(label)));
                snapshot.segments.forEach((s) =>
                    card.append(
                        element(
                            'div',
                            `${lang('att_' + s.kind)} · ${local(s.start)} → ${local(s.end)} · ${lang('att_' + s.policy)}`,
                        ),
                    ),
                );
                if (snapshot.voided) card.append(element('strong', lang('att_voided'), 'text-danger'));
            }
            $id('att-modal-body').append(card);
        });
    }
    function edit(entry) {
        let reason, voided;
        const rows = [];
        openModal(lang('att_correct'), () => {
            const segments = rows
                .filter((r) => r.wrapper.isConnected)
                .map((r) => ({
                    kind: r.kind.value === 'work' ? 'work' : 'break',
                    policy: r.kind.value,
                    start: r.start.value(),
                    end: r.end.value(),
                }));
            change('correct', {
                id: entry?.id || 0,
                revision: entry?.revision || 0,
                reason: reason.value,
                voided: voided.checked,
                segments,
            });
        });
        $id('att-modal-body').append(element('p', lang('att_correction_hint'), 'text-muted'));
        const container = element('div');
        $id('att-modal-body').append(container);
        function timeInput(parent, label, value) {
            const box = element('label', '', 'd-block mb-2');
            box.append(element('span', label, 'form-label'));
            const flex = element('div', '', 'd-flex gap-2');
            const input = document.createElement('input');
            input.type = 'datetime-local';
            input.step = '1';
            input.className = 'form-control';
            input.value = value ? moment.utc(value).tz('Europe/Madrid').format('YYYY-MM-DDTHH:mm:ss') : '';
            const offset = document.createElement('select');
            offset.className = 'form-select';
            offset.style.maxWidth = '105px';
            offset.setAttribute('aria-label', lang('att_offset'));
            ['+01:00', '+02:00'].forEach((v) => offset.append(new Option(v, v)));
            offset.value = value
                ? moment.utc(value).tz('Europe/Madrid').format('Z')
                : moment.tz('Europe/Madrid').format('Z');
            input.addEventListener('change', () => {
                if (input.value) offset.value = moment.tz(input.value, 'Europe/Madrid').format('Z');
            });
            flex.append(input, offset);
            box.append(flex);
            parent.append(box);
            return {
                value: () =>
                    input.value ? input.value + (input.value.length === 16 ? ':00' : '') + offset.value : null,
            };
        }
        function add(segment = {kind: 'work', policy: 'work', start: null, end: null}) {
            const wrapper = element('div', '', 'border rounded p-3 mb-2');
            const kind = document.createElement('select');
            kind.className = 'form-select mb-2';
            kind.setAttribute('aria-label', lang('att_kind'));
            ['work', 'paid', 'unpaid', 'unknown'].forEach((k) => kind.append(new Option(lang('att_' + k), k)));
            kind.value = segment.policy;
            wrapper.append(kind);
            const start = timeInput(wrapper, lang('att_start'), segment.start),
                end = timeInput(wrapper, lang('att_end_optional'), segment.end);
            wrapper.append(button(lang('att_remove_segment'), () => wrapper.remove(), 'btn btn-sm btn-outline-danger'));
            container.append(wrapper);
            rows.push({wrapper, kind, start, end});
        }
        (entry?.segments || [{kind: 'work', policy: 'work', start: null, end: null}]).forEach(add);
        $id('att-modal-body').append(button(lang('att_add_segment'), () => add()));
        const label = element('label', '', 'd-block my-3');
        voided = document.createElement('input');
        voided.type = 'checkbox';
        voided.className = 'form-check-input me-2';
        voided.checked = !!entry?.voided;
        label.append(voided, document.createTextNode(lang('att_void_label')));
        $id('att-modal-body').append(label);
        reason = field(lang('att_reason'), 'textarea');
        reason.maxLength = 2000;
    }

    employees.forEach((person) =>
        $id('att-employee').append(
            new Option(person.name + (person.current ? '' : ' · ' + lang('inactive')), person.id),
        ),
    );
    $id('att-employee').disabled = !admin;
    $id('att-month').value = vars('attendance_month');
    $id('att-employee').addEventListener('change', load);
    $id('att-month').addEventListener('change', load);
    $id('att-refresh').addEventListener('click', load);
    $id('att-add')?.addEventListener('click', () => edit(null));
    $id('att-request')?.addEventListener('click', () => {
        let date, message;
        openModal(lang('att_request'), () => change('request', {date: date.value, message: message.value}));
        date = field(lang('date'), 'date', moment.tz('Europe/Madrid').format('YYYY-MM-DD'));
        message = field(lang('att_request_message'), 'textarea');
        message.maxLength = 2000;
    });
    $id('att-profile-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        change('profile', {
            contract_type: $id('att-contract').value,
            weekly_minutes: $id('att-weekly').value,
            agreement: $id('att-agreement').value,
            pause_policy: $id('att-pause-policy').value,
        });
    });
    $id('att-issue-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        change('issue', {
            month: month(),
            ordinary: $id('att-ordinary').value,
            complementary: $id('att-complementary').value,
            overtime: $id('att-overtime').value,
        });
    });
    document.addEventListener('visibilitychange', () => {
        if (!admin && !document.hidden && !busy && !$id('att-modal').classList.contains('show')) load();
    });
    if (!admin)
        setInterval(() => {
            if (!document.hidden && !busy && !$id('att-modal').classList.contains('show')) load();
        }, 30000);
    if (!employees.length) {
        feedback(lang('att_no_staff'));
        return;
    }
    load();
});
