/**
 * Working time settings page: the rules of the law or the collective agreement and the contract of each employee.
 * Values are stored in minutes; the form shows hours where people think in hours.
 */
document.addEventListener('DOMContentLoaded', () => {
    const $id = (id) => document.getElementById(id);
    const limits = vars('attendance_limits');
    const agreements = vars('attendance_agreements');
    const statute = vars('attendance_statute');
    let rules = vars('attendance_rules');
    const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    const element = (tag, text = '', cls = '') => {
        const el = document.createElement(tag);
        el.textContent = text;
        el.className = cls;
        return el;
    };
    const notify = (message) => App.Layouts.Backend.displayNotification(message);
    const hours = (minutes) => String(Math.round((minutes / 60) * 100) / 100);

    async function post(path, data) {
        const response = await fetch(App.Utils.Url.siteUrl(path), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({csrf_token: vars('csrf_token'), ...data}),
        });
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error(lang('att_failed'));
        }
        if (!response.ok || result.success === false) throw new Error(result.message || lang('att_failed'));
        return result;
    }

    /* Rules: [key, label, unit]. Hours and minutes are converted to the minutes that are stored. */
    const sections = [
        [
            'att_section_agreement',
            [
                ['agreement', 'att_agreement', 'agreement'],
                ['full_time_minutes', 'att_full_time', 'h'],
                ['annual_max_minutes', 'att_annual_max', 'h'],
            ],
        ],
        [
            'att_section_rest',
            [
                ['max_daily_minutes', 'att_max_daily', 'h'],
                ['min_rest_minutes', 'att_min_rest', 'h'],
                ['weekly_rest_minutes', 'att_weekly_rest', 'h'],
                ['weekly_rest_days', 'att_weekly_rest_days', 'days'],
                ['part_time_max_interruptions', 'att_part_time_interruptions', 'count'],
            ],
        ],
        [
            'att_section_break',
            [
                ['break_after_minutes', 'att_break_after', 'h'],
                ['break_minutes', 'att_break_minutes', 'min'],
                ['break_paid', 'att_break_paid', 'switch'],
            ],
        ],
        [
            'att_section_extra',
            [
                ['overtime_max_minutes', 'att_overtime_max', 'h'],
                ['complementary_max_percent', 'att_complementary_max', '%'],
            ],
        ],
        [
            'att_section_other',
            [
                ['open_alert_minutes', 'att_open_alert', 'h'],
                ['retention_years', 'att_retention', 'years'],
            ],
        ],
    ];
    const units = {
        h: 'h',
        min: 'min',
        days: lang('att_days'),
        '%': '%',
        years: lang('att_years'),
        count: lang('att_per_day'),
    };
    const inputs = {};
    const display = (key, unit, value) => `${unit === 'h' ? hours(value) : value} ${units[unit]}`;
    /* The statute always applies: the agreement sets the values, never below these limits (art. 3 ET). */
    const legal = {
        full_time_minutes: 'max',
        max_daily_minutes: 'statute',
        part_time_max_interruptions: 'statute',
        min_rest_minutes: 'min',
        weekly_rest_minutes: 'min',
        weekly_rest_days: 'max',
        break_after_minutes: 'max',
        break_minutes: 'min',
        overtime_max_minutes: 'max',
        retention_years: 'min',
    };
    function hint(key, unit) {
        if (key === 'break_paid') return lang(statute.break_paid ? 'att_statute_paid' : 'att_statute_unpaid');
        if (key === 'complementary_max_percent')
            return lang('att_complementary_legal')
                .replace('%s', display(key, unit, statute[key]))
                .replace('%s', display(key, unit, limits[key][1]));
        const type = legal[key];
        if (!type) return '';
        const value = type === 'min' ? limits[key][0] : type === 'max' ? limits[key][1] : statute[key];
        return lang(type === 'statute' ? 'att_statute_value' : 'att_legal_' + type).replace(
            '%s',
            display(key, unit, value),
        );
    }

    function agreementField(col) {
        const known = Object.entries(agreements);
        const choice = document.createElement('select');
        choice.id = 'att-rule-agreement-choice';
        choice.className = 'form-select';
        known.forEach(([name, agreement]) => choice.append(new Option(agreement.label, name)));
        choice.append(new Option(lang('att_agreement_other'), 'other'));
        choice.append(new Option(lang('att_agreement_none'), 'none'));
        const label = element('label', lang('att_agreement'), 'form-label');
        label.htmlFor = choice.id;
        const name = document.createElement('input');
        name.className = 'form-control mt-2';
        name.maxLength = 512;
        name.placeholder = lang('att_agreement_name');
        name.setAttribute('aria-label', lang('att_agreement_name'));
        const sync = (value) => {
            const preset = known.find(([, agreement]) => agreement.values.agreement === value);
            choice.value = preset ? preset[0] : value ? 'other' : 'none';
            name.hidden = choice.value !== 'other';
            name.required = !name.hidden;
            name.value = value;
        };
        choice.addEventListener('change', () => {
            if (choice.value === 'none') fillRules(statute);
            else if (choice.value === 'other') {
                name.hidden = false;
                name.required = true;
                name.value = '';
                name.focus();
            } else fillRules(agreements[choice.value].values);
        });
        col.append(label, choice, name, element('div', lang('att_statute_note'), 'form-text'));
        inputs.agreement = {
            get value() {
                return choice.value === 'none'
                    ? ''
                    : choice.value === 'other'
                      ? name.value
                      : agreements[choice.value].values.agreement;
            },
            set value(value) {
                sync(value);
            },
        };
    }

    function renderRules() {
        const container = $id('att-rules');
        container.replaceChildren();
        sections.forEach(([title, fields]) => {
            const section = element('fieldset', '', 'mb-4');
            section.append(element('legend', lang(title), 'h6 fw-normal text-muted mb-3'));
            const row = element('div', '', 'row g-3');
            fields.forEach(([key, label, unit]) => {
                const col = element('div', '', unit === 'agreement' ? 'col-12' : 'col-md-6');
                const id = 'att-rule-' + key;
                if (unit === 'agreement') {
                    agreementField(col);
                } else if (unit === 'switch') {
                    const wrap = element('div', '', 'form-check form-switch mt-md-4');
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.id = id;
                    input.className = 'form-check-input';
                    const text = element('label', lang(label), 'form-check-label');
                    text.htmlFor = id;
                    wrap.append(input, text);
                    col.append(wrap, element('div', hint(key, unit), 'form-text'));
                    inputs[key] = input;
                } else {
                    const text = element('label', lang(label), 'form-label');
                    text.htmlFor = id;
                    const group = element('div', '', 'input-group');
                    const input = document.createElement('input');
                    input.id = id;
                    input.className = 'form-control';
                    const [min, max] = limits[key];
                    const factor = unit === 'h' ? 60 : 1;
                    input.type = 'number';
                    input.min = min / factor;
                    input.max = max / factor;
                    input.step = unit === 'h' ? '0.25' : '1';
                    input.required = true;
                    group.append(input, element('span', units[unit], 'input-group-text att-unit'));
                    col.append(text, group);
                    if (hint(key, unit)) col.append(element('div', hint(key, unit), 'form-text'));
                    inputs[key] = input;
                }
                row.append(col);
            });
            section.append(row);
            container.append(section);
        });
    }

    function fillRules(values) {
        sections.forEach(([, fields]) =>
            fields.forEach(([key, , unit]) => {
                const value = values[key];
                if (unit === 'switch') inputs[key].checked = Number(value) === 1;
                else if (unit === 'h') inputs[key].value = hours(value);
                else inputs[key].value = value;
            }),
        );
    }

    function readRules() {
        const values = {};
        sections.forEach(([, fields]) =>
            fields.forEach(([key, , unit]) => {
                const input = inputs[key];
                if (unit === 'switch') values[key] = input.checked ? '1' : '0';
                else if (unit === 'agreement') values[key] = input.value.trim();
                else if (unit === 'h') values[key] = String(Math.round(Number(input.value) * 60));
                else values[key] = String(Math.round(Number(input.value)));
            }),
        );
        return values;
    }

    $id('att-rules-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        form.classList.add('was-validated');
        if (!form.checkValidity()) {
            notify(lang('att_invalid_settings'));
            return;
        }
        try {
            const result = await post('attendance_settings/save', {rules: JSON.stringify(readRules())});
            rules = result.rules;
            fillRules(rules);
            form.classList.remove('was-validated');
            document
                .querySelectorAll('.att-contract-label')
                .forEach((label) => label.dispatchEvent(new Event('refresh')));
            notify(lang('settings_saved'));
        } catch (error) {
            notify(error.message);
        }
    });

    /* Employees */
    function intervalInput(value) {
        const input = document.createElement('input');
        input.type = 'time';
        input.className = 'form-control form-control-sm';
        input.value = value || '';
        return input;
    }

    function renderEmployee(person) {
        const profile = person.profile;
        const card = element('form', '', 'card border-0 shadow-sm att-employee p-4 mb-3');
        card.noValidate = true;
        const header = element('div', '', 'd-flex flex-wrap align-items-center gap-2 mb-3');
        header.append(element('h6', person.name, 'mb-0 me-auto'));
        const contractLabel = element('span', '', 'badge rounded-pill text-bg-light att-contract-label');
        header.append(contractLabel);
        card.append(header);

        const row = element('div', '', 'row g-3');
        const field = (label, input, cls = 'col-md-6') => {
            const col = element('div', '', cls);
            const text = element('label', lang(label), 'form-label');
            input.id = `att-${label}-${person.id}`;
            text.htmlFor = input.id;
            col.append(text, input);
            row.append(col);
            return input;
        };
        const select = (options, value) => {
            const input = document.createElement('select');
            input.className = 'form-select';
            options.forEach(([key, label]) => input.append(new Option(lang(label), key)));
            input.value = value;
            return input;
        };

        const category = document.createElement('input');
        category.className = 'form-control';
        category.maxLength = 255;
        category.value = profile.category;
        field('att_category', category);

        const duration = field(
            'att_duration',
            select(
                [
                    ['unknown', 'att_unknown'],
                    ['indefinite', 'att_indefinite'],
                    ['temporary', 'att_temporary'],
                ],
                profile.contract_duration,
            ),
        );

        const weeklyGroup = element('div', '', 'input-group');
        const weekly = document.createElement('input');
        weekly.type = 'number';
        weekly.className = 'form-control';
        weekly.min = '0.25';
        weekly.step = '0.25';
        weekly.value = profile.weekly_minutes ? hours(profile.weekly_minutes) : '';
        weeklyGroup.append(weekly, element('span', 'h', 'input-group-text att-unit'));
        const weeklyCol = element('div', '', 'col-md-6');
        const weeklyLabel = element('label', lang('att_weekly'), 'form-label');
        weekly.id = `att-weekly-${person.id}`;
        weeklyLabel.htmlFor = weekly.id;
        weeklyCol.append(weeklyLabel, weeklyGroup);
        row.append(weeklyCol);

        const pauses = field(
            'att_policy',
            select(
                [
                    ['agreement', 'att_policy_agreement'],
                    ['paid', 'att_policy_paid'],
                    ['unpaid', 'att_policy_unpaid'],
                ],
                ['agreement', 'paid', 'unpaid'].includes(profile.pause_policy) ? profile.pause_policy : 'agreement',
            ),
        );

        const pactGroup = element('div', '', 'input-group');
        const pact = document.createElement('input');
        pact.type = 'number';
        pact.className = 'form-control';
        pact.min = '0';
        pact.step = '1';
        pact.value = profile.complementary_percent;
        pactGroup.append(pact, element('span', '%', 'input-group-text att-unit'));
        const pactCol = element('div', '', 'col-md-6');
        const pactLabel = element('label', lang('att_complementary_pact'), 'form-label');
        pact.id = `att-pact-${person.id}`;
        pactLabel.htmlFor = pact.id;
        pactCol.append(pactLabel, pactGroup);
        row.append(pactCol);
        card.append(row);

        /* Schedule */
        const scheduleBox = element('div', '', 'mt-4');
        const scheduleHeader = element('div', '', 'd-flex align-items-baseline gap-2 mb-1');
        const total = element('small', '', 'ms-auto');
        scheduleHeader.append(element('span', lang('att_schedule'), 'form-label mb-0'), total);
        scheduleBox.append(scheduleHeader);
        const days = {};
        weekdays.forEach((name, index) => {
            const day = index + 1;
            const line = element('div', '', 'att-day');
            const list = element('div');
            const add = element('button', '+', 'btn btn-sm btn-outline-secondary');
            add.type = 'button';
            add.setAttribute('aria-label', lang('att_add_interval'));
            days[day] = [];
            const addInterval = ([start, end] = ['', '']) => {
                if (days[day].length >= 3) return;
                const wrap = element('span', '', 'att-interval');
                const from = intervalInput(start);
                const to = intervalInput(end);
                const remove = element('button', '×', 'btn btn-sm btn-link text-danger px-1');
                remove.type = 'button';
                remove.setAttribute('aria-label', lang('att_remove_segment'));
                const pair = [from, to];
                remove.addEventListener('click', () => {
                    wrap.remove();
                    days[day].splice(days[day].indexOf(pair), 1);
                    add.disabled = days[day].length >= 3;
                    refresh();
                });
                [from, to].forEach((input) => input.addEventListener('change', refresh));
                wrap.append(from, element('span', '–'), to, remove);
                list.append(wrap);
                days[day].push(pair);
                add.disabled = days[day].length >= 3;
            };
            add.addEventListener('click', () => {
                addInterval();
                refresh();
            });
            (profile.schedule[day] || []).forEach(addInterval);
            line.append(element('span', lang(name)), list, add);
            scheduleBox.append(line);
        });
        card.append(scheduleBox);

        const save = element('button', lang('save'), 'btn btn-primary mt-4');
        save.type = 'submit';
        const footer = element('div', '', 'text-end');
        footer.append(save);
        card.append(footer);

        const minutesOf = (time) => {
            const [h, m] = time.split(':').map(Number);
            return h * 60 + m;
        };
        function scheduled() {
            let minutes = 0;
            Object.values(days).forEach((pairs) =>
                pairs.forEach(([from, to]) => {
                    if (from.value && to.value && to.value > from.value)
                        minutes += minutesOf(to.value) - minutesOf(from.value);
                }),
            );
            return minutes;
        }
        function refresh() {
            const weeklyMinutes = Math.round(Number(weekly.value) * 60);
            const planned = scheduled();
            total.textContent = `${lang('att_schedule_total')}: ${hours(planned)} h`;
            total.className =
                'ms-auto ' + (weekly.value && planned && planned !== weeklyMinutes ? 'text-danger' : 'text-muted');
            const part = weekly.value && weeklyMinutes < Number(rules.full_time_minutes);
            contractLabel.textContent = !weekly.value
                ? lang('att_unknown')
                : part
                  ? `${lang('att_part')} · ${Math.round((weeklyMinutes / rules.full_time_minutes) * 100)} %`
                  : lang('att_full');
            weekly.max = hours(rules.full_time_minutes);
            pact.max = rules.complementary_max_percent;
            pact.disabled = !part || weeklyMinutes < vars('attendance_complementary_min');
            if (pact.disabled) pact.value = 0;
        }
        weekly.addEventListener('input', refresh);
        contractLabel.addEventListener('refresh', refresh);
        refresh();

        card.addEventListener('submit', async (event) => {
            event.preventDefault();
            const schedule = {};
            Object.entries(days).forEach(([day, pairs]) => {
                schedule[day] = pairs
                    .filter(([from, to]) => from.value || to.value)
                    .map(([from, to]) => [from.value, to.value]);
            });
            try {
                const result = await post('attendance_settings/employee', {
                    employee_id: person.id,
                    profile: JSON.stringify({
                        category: category.value.trim(),
                        contract_duration: duration.value,
                        weekly_minutes: weekly.value ? String(Math.round(Number(weekly.value) * 60)) : '',
                        pause_policy: pauses.value,
                        complementary_percent: String(Number(pact.value) || 0),
                        schedule,
                    }),
                });
                person.profile = result.profile;
                notify(lang('settings_saved'));
            } catch (error) {
                notify(error.message);
            }
        });
        return card;
    }

    renderRules();
    fillRules(rules);
    const employees = vars('attendance_employees').filter((person) => person.current);
    $id('att-employees').replaceChildren(...employees.map(renderEmployee));
    if (!employees.length) $id('att-employees').append(element('p', lang('att_no_staff'), 'text-muted'));

    /* Staff whose employment relationship is still undefined: neither clocks in nor is known to be exempt. */
    const undefinedStaff = vars('attendance_undefined');
    if (undefinedStaff.length) {
        const warning = element('div', '', 'alert alert-warning');
        warning.append(element('div', lang('att_employment_missing'), 'mb-1'));
        undefinedStaff.forEach((person, index) => {
            const link = element('a', person.name);
            link.href = App.Utils.Url.siteUrl(person.role === 'provider' ? 'providers' : 'secretaries');
            warning.append(index ? ', ' : '', link);
        });
        $id('att-employees').prepend(warning);
    }
});
