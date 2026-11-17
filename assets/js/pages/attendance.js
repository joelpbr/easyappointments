document.addEventListener('DOMContentLoaded', () => {
    const $id = (id) => document.getElementById(id);
    const admin = vars('attendance_admin');
    const worker = vars('attendance_worker');
    const ZONE = vars('attendance_timezone');
    const employees = vars('attendance_employees');
    let data = null,
        busy = false,
        loadVersion = 0;
    const modal = new bootstrap.Modal($id('att-modal'));
    const duration = (seconds) => {
        seconds = Math.max(0, Math.floor(seconds));
        return `${Math.floor(seconds / 3600)}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    };
    const hours = (seconds) => {
        const minutes = Math.floor(Math.max(0, seconds) / 60);
        return `${Math.floor(minutes / 60)} h ${String(minutes % 60).padStart(2, '0')} min`;
    };
    const local = (value) => (value ? moment.utc(value).tz(ZONE).format('DD/MM/YYYY HH:mm:ss Z') : '—');
    const zoned = (value) => moment.utc(value).tz(ZONE);
    const dayFormat = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        timeZone: ZONE,
    });
    const day = (value) => dayFormat.format(value.toDate());
    const calendarDay = (date) => day(moment.tz(date, ZONE).hour(12));
    const segmentTotals = (entry) => {
        const totals = {work: 0, break: 0};
        entry.segments.forEach((s) => {
            totals[s.kind] += Math.max(0, moment.utc(s.end || data.server_now).diff(moment.utc(s.start), 'seconds'));
        });
        return totals;
    };
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
    let feedbackTimer,
        serverOffset = 0;
    const longDate = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: ZONE,
    });
    const tick = () => {
        if (!$id('att-time')) return;
        const now = moment(Date.now() + serverOffset).tz(ZONE);
        $id('att-time').textContent = now.format('HH:mm');
        const date = longDate.format(now.toDate());
        $id('att-date').textContent = date.charAt(0).toUpperCase() + date.slice(1);
    };
    const feedback = (text, error = false) => {
        const box = $id('att-feedback');
        box.textContent = text;
        box.className = `att-feedback alert alert-${error ? 'danger' : 'success'}`;
        box.hidden = !text;
        clearTimeout(feedbackTimer);
        if (!error) feedbackTimer = setTimeout(() => (box.hidden = true), 3000);
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
            serverOffset = moment.utc(data.server_now).valueOf() - Date.now();
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
        if (worker) {
            const paused = current?.segments.at(-1).kind === 'break';
            $id('att-state').textContent = lang(!current ? 'att_off' : paused ? 'att_paused' : 'att_working');
            $id('att-state-pill').dataset.state = !current ? 'off' : paused ? 'paused' : 'working';
            $id('att-since').textContent = current
                ? `${lang('att_since')} ${zoned(current.start_at).format('HH:mm')} · ${hours(segmentTotals(current).work)}`
                : lang('att_clock_first');
            const actions = $id('att-clock-actions');
            actions.replaceChildren();
            // A pause only exists in a continuous stretch long enough to give right to it; a split shift clocks out.
            const minutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3, 5));
            const blocks = Object.values(data.employee.schedule).flat();
            const pauses =
                !blocks.length ||
                blocks.some(([start, end]) => minutes(end) - minutes(start) > data.rules.break_after_minutes);
            const choices = !current
                ? [['in', 'att_in']]
                : paused
                  ? [
                        ['resume', 'att_resume'],
                        ['out', 'att_out'],
                    ]
                  : pauses
                    ? [
                          ['pause', 'att_pause'],
                          ['out', 'att_out'],
                      ]
                    : [['out', 'att_out']];
            const styles = {
                in: 'btn-primary',
                resume: 'btn-primary',
                pause: 'btn-outline-secondary',
                out: 'btn-outline-danger',
            };
            choices.forEach(([action, label]) =>
                actions.append(button(lang(label), () => clock(action), 'btn btn-lg ' + styles[action])),
            );
            $id('att-open-warning').hidden =
                !current ||
                moment.utc(data.server_now).diff(moment.utc(current.start_at), 'minutes') <
                    data.rules.open_alert_minutes;
        } else if (admin) {
            $id('att-team').replaceChildren();
            data.team.forEach((person) => {
                const col = element('div', '', 'col-sm-6 col-md-4');
                const state = person.current
                    ? person.current.segments.at(-1).kind === 'break'
                        ? ['att_paused', 'paused']
                        : ['att_working', 'working']
                    : ['att_off', 'off'];
                const card = button(
                    '',
                    () => {
                        $id('att-employee').value = person.id;
                        location.hash = 'registres';
                        load();
                    },
                    'card border-0 shadow-sm att-person card-body text-start w-100 d-flex flex-row align-items-center gap-3',
                );
                const info = element('div', '', 'me-auto');
                const status = element('div', '', 'small text-muted d-flex align-items-center gap-2');
                status.dataset.state = state[1];
                status.append(element('span', '', 'att-dot'), element('span', lang(state[0])));
                info.append(element('div', person.name, 'fw-semibold'), status);
                card.append(element('span', (person.name || '?').charAt(0).toUpperCase(), 'att-avatar'), info);
                if (person.pending) card.append(element('span', person.pending, 'badge rounded-pill text-bg-danger'));
                col.append(card);
                $id('att-team').append(col);
            });
        }
        $id('att-total').textContent = hours(data.summary.counted_seconds);
        $id('att-breakdown').replaceChildren(
            ...['paid', 'unpaid', 'unknown']
                .filter((key) => data.summary.totals[key] > 0)
                .map((key) => element('div', `${lang('att_' + key)}: ${hours(data.summary.totals[key])}`)),
        );
        $id('att-provisional').hidden = !data.summary.provisional;
        $id('att-review').hidden = !data.review_flags.length;
        $id('att-review').replaceChildren();
        data.review_flags.forEach((flag) =>
            $id('att-review').append(
                element('div', (flag.date ? `${calendarDay(flag.date)} · ` : '') + lang('att_flag_' + flag.type)),
            ),
        );
        $id('att-entries').replaceChildren();
        data.entries.forEach((entry) => {
            const totals = segmentTotals(entry);
            const start = zoned(entry.start_at);
            const end = entry.end_at ? zoned(entry.end_at) : null;
            const tr = element('tr', '', entry.voided ? 'text-decoration-line-through text-muted' : '');
            const cells = [
                [day(start), ''],
                [start.format('HH:mm'), local(entry.start_at)],
                [end ? end.format(end.isSame(start, 'day') ? 'HH:mm' : 'DD/MM HH:mm') : '', local(entry.end_at)],
                [hours(totals.work), ''],
                [totals.break ? hours(totals.break) : '—', ''],
            ];
            cells.forEach(([text, title]) => {
                const td = element('td', text);
                if (title) td.title = title;
                tr.append(td);
            });
            if (!end && !entry.voided)
                tr.children[2].append(element('span', lang('att_open'), 'badge text-bg-success'));
            if (entry.voided)
                tr.children[0].append(element('span', lang('att_voided'), 'badge text-bg-secondary ms-1'));
            const actions = element('td', '', 'text-nowrap text-end');
            actions.append(button(lang('att_history'), () => history(entry.id), 'btn btn-sm btn-outline-secondary'));
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
        data.summary.days.forEach((total) => {
            const tr = element('tr');
            [
                calendarDay(total.date),
                ...['planned', 'work', 'paid', 'unpaid', 'unknown'].map((k) => hours(total[k] || 0)),
            ].forEach((v) => tr.append(element('td', v)));
            $id('att-days').append(tr);
        });
        const pending = data.requests.filter((request) => request.status === 'pending').length;
        $id('att-pending-badge').textContent = pending;
        $id('att-pending-badge').hidden = !pending;
        $id('att-requests').replaceChildren();
        data.requests.forEach((request) => {
            const card = element('div', '', 'card border-0 shadow-sm att-card p-3 mb-2');
            const header = element('div', '', 'd-flex gap-2 align-items-center mb-1');
            header.append(
                element('strong', moment(request.work_date).format('DD/MM/YYYY')),
                element(
                    'span',
                    lang('att_' + request.status),
                    'badge rounded-pill ms-auto ' +
                        (request.status === 'pending' ? 'text-bg-warning' : 'text-bg-light'),
                ),
            );
            card.append(header, element('p', request.message, 'mb-1'));
            if (request.resolution) card.append(element('p', request.resolution, 'text-muted mb-0'));
            if (admin && request.status === 'pending')
                card.append(
                    button(
                        lang('att_resolve'),
                        () => {
                            let resolution;
                            openModal(lang('att_resolve'), () =>
                                change('resolve', {id: request.id, resolution: resolution.value}),
                            );
                            resolution = field(lang('att_resolution'), 'textarea');
                            resolution.maxLength = 2000;
                        },
                        'btn btn-sm btn-outline-primary align-self-end',
                    ),
                );
            $id('att-requests').append(card);
        });
        if (!data.requests.length)
            $id('att-requests').append(element('p', lang('att_none'), 'text-muted text-center py-4'));
        $id('att-reports').replaceChildren();
        data.reports.forEach((report) => {
            const row = element(
                'div',
                '',
                'card border-0 shadow-sm att-card p-3 mb-2 d-flex flex-row flex-wrap gap-2 align-items-center',
            );
            const a = element('a', `#${report.id} · ${zoned(report.issued_at).format('DD/MM/YYYY')}`, 'fw-semibold');
            a.href = url('report', {id: report.id});
            a.target = '_blank';
            a.rel = 'noopener';
            row.append(a);
            row.append(
                element(
                    'span',
                    report.delivered_at
                        ? lang('att_delivered') + ' · ' + zoned(report.delivered_at).format('DD/MM/YYYY')
                        : lang('att_not_delivered'),
                    'badge rounded-pill ms-auto ' + (report.delivered_at ? 'text-bg-light' : 'text-bg-warning'),
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
        if (!data.reports.length)
            $id('att-reports').append(element('p', lang('att_none'), 'text-muted text-center py-4'));
        for (const [id, format] of [
            ['att-csv', 'csv'],
            ['att-print', 'print'],
            ['att-json', 'json'],
        ])
            $id(id).href = url('export', {format});
        if (admin)
            ['ordinary', 'complementary', 'overtime'].forEach(
                (kind) => ($id('att-' + kind).value = duration(data.suggestion[kind])),
            );
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
            input.value = value ? moment.utc(value).tz(ZONE).format('YYYY-MM-DDTHH:mm:ss') : '';
            const offset = document.createElement('select');
            offset.className = 'form-select';
            offset.style.maxWidth = '105px';
            offset.setAttribute('aria-label', lang('att_offset'));
            // Winter and summer offsets of the clinic timezone, for the repeated hour of the DST change.
            const year = moment.tz(ZONE).year();
            [...new Set([`${year}-01-15`, `${year}-07-15`].map((date) => moment.tz(date, ZONE).format('Z')))]
                .sort()
                .forEach((v) => offset.append(new Option(v, v)));
            offset.value = value ? moment.utc(value).tz(ZONE).format('Z') : moment.tz(ZONE).format('Z');
            input.addEventListener('change', () => {
                if (input.value) offset.value = moment.tz(input.value, ZONE).format('Z');
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
            ['work', 'auto', 'paid', 'unpaid', 'unknown'].forEach((k) => kind.append(new Option(lang('att_' + k), k)));
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
    const showView = () => {
        // Former employees no longer clock in: they only see their records.
        const records = location.hash === '#registres' || (!admin && !worker);
        $id('att-view-clock').hidden = records;
        $id('att-view-records').hidden = !records;
    };
    window.addEventListener('hashchange', showView);
    showView();
    $id('att-month').value = vars('attendance_month');
    $id('att-employee').addEventListener('change', load);
    $id('att-month').addEventListener('change', load);
    $id('att-add')?.addEventListener('click', () => edit(null));
    $id('att-request')?.addEventListener('click', () => {
        let date, message;
        openModal(lang('att_request'), () => change('request', {date: date.value, message: message.value}));
        date = field(lang('date'), 'date', moment.tz(ZONE).format('YYYY-MM-DD'));
        message = field(lang('att_request_message'), 'textarea');
        message.maxLength = 2000;
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
        if (worker && !document.hidden && !busy && !$id('att-modal').classList.contains('show')) load();
    });
    if (worker)
        setInterval(() => {
            if (!document.hidden && !busy && !$id('att-modal').classList.contains('show')) load();
        }, 30000);
    tick();
    setInterval(tick, 1000);
    if (!employees.length) {
        feedback(lang('att_no_staff'), true);
        return;
    }
    load();
});
