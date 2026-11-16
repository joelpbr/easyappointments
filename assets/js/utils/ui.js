/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * UI utility.
 *
 * This module provides commonly used UI functionality.
 */
window.App.Utils.UI = (function () {
    /**
     * Get the Flatpickr compatible date format.
     *
     * @return {String}
     */
    function getDateFormat() {
        switch (vars('date_format')) {
            case 'DMY':
                return 'd/m/Y';
            case 'MDY':
                return 'm/d/Y';
            case 'YMD':
                return 'Y/m/d';
            default:
                throw new Error('Invalid date format value.');
        }
    }

    /**
     * Get the Flatpickr compatible date format.
     *
     * @return {String}
     */
    function getTimeFormat() {
        switch (vars('time_format')) {
            case 'regular':
                return 'h:i K';
            case 'military':
                return 'H:i';
            default:
                throw new Error('Invalid date format value.');
        }
    }

    /**
     * Get the localization object.
     *
     * @return Object
     */
    function getFlatpickrLocale() {
        const firstWeekDay = vars('first_weekday');

        const firstWeekDayNumber = App.Utils.Date.getWeekdayId(firstWeekDay);

        return {
            weekdays: {
                shorthand: [
                    lang('sunday_short'),
                    lang('monday_short'),
                    lang('tuesday_short'),
                    lang('wednesday_short'),
                    lang('thursday_short'),
                    lang('friday_short'),
                    lang('saturday_short'),
                ],
                longhand: [
                    lang('sunday'),
                    lang('monday'),
                    lang('tuesday'),
                    lang('wednesday'),
                    lang('thursday'),
                    lang('friday'),
                    lang('saturday'),
                ],
            },
            months: {
                shorthand: [
                    lang('january_short'),
                    lang('february_short'),
                    lang('march_short'),
                    lang('april_short'),
                    lang('may_short'),
                    lang('june_short'),
                    lang('july_short'),
                    lang('august_short'),
                    lang('september_short'),
                    lang('october_short'),
                    lang('november_short'),
                    lang('december_short'),
                ],
                longhand: [
                    lang('january'),
                    lang('february'),
                    lang('march'),
                    lang('april'),
                    lang('may'),
                    lang('june'),
                    lang('july'),
                    lang('august'),
                    lang('september'),
                    lang('october'),
                    lang('november'),
                    lang('december'),
                ],
            },
            daysInMonth: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
            firstDayOfWeek: firstWeekDayNumber,
            ordinal: function (nth) {
                const s = nth % 100;

                if (s > 3 && s < 21) return 'th';
                switch (s % 10) {
                    case 1:
                        return 'st';
                    case 2:
                        return 'nd';
                    case 3:
                        return 'rd';
                    default:
                        return 'th';
                }
            },
            rangeSeparator: ` ${lang('to')} `,
            weekAbbreviation: lang('week_short'),
            scrollTitle: lang('scroll_to_increment'),
            toggleTitle: lang('click_to_toggle'),
            amPM: ['am', 'pm'],
            yearAriaLabel: lang('year'),
            monthAriaLabel: lang('month'),
            hourAriaLabel: lang('hour'),
            minuteAriaLabel: lang('minute'),
            time_24hr: false,
        };
    }

    /**
     * Whether the pickers are Air Datepicker ones (the management pages) or flatpickr ones (the booking page, which
     * relies on flatpickr's own calendar).
     *
     * @return {Boolean}
     */
    function usesAirDatepicker() {
        return typeof window.AirDatepicker === 'function';
    }

    // Moment formats of the dates a person can type, by date format setting; the first one is the one shown.
    const TYPED_DATE_FORMATS = {
        DMY: ['DD/MM/YYYY', 'D/M/YYYY', 'DD/MM/YY', 'D/M/YY', 'DD-MM-YYYY', 'D-M-YYYY', 'DDMMYYYY'],
        MDY: ['MM/DD/YYYY', 'M/D/YYYY', 'MM/DD/YY', 'M/D/YY', 'MM-DD-YYYY', 'M-D-YYYY', 'MMDDYYYY'],
        YMD: ['YYYY/MM/DD', 'YYYY/M/D', 'YYYY-MM-DD', 'YYYY-M-D', 'YYYYMMDD'],
    };

    /**
     * Moment formats a picker shows (the first one) and accepts when typed.
     *
     * @param {String} mode "date", "time" or "datetime".
     *
     * @return {String[]}
     */
    function getTypedFormats(mode) {
        const dates = TYPED_DATE_FORMATS[vars('date_format')] || TYPED_DATE_FORMATS.DMY;
        const times =
            vars('time_format') === 'regular'
                ? ['h:mm A', 'h:mmA', 'h:mm a', 'h:mma', 'h A', 'hA']
                : ['HH:mm', 'H:mm', 'HH.mm', 'H.mm', 'HHmm', 'H'];

        if (mode === 'date') {
            return dates;
        }

        if (mode === 'time') {
            return times;
        }

        return dates.flatMap((date) => times.map((time) => date + ' ' + time));
    }

    /**
     * Get the localization of Air Datepicker.
     *
     * @return {Object}
     */
    function getAirDatepickerLocale() {
        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const months = [
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august',
            'september',
            'october',
            'november',
            'december',
        ];

        return {
            days: days.map((day) => lang(day)),
            daysShort: days.map((day) => lang(day + '_short')),
            daysMin: days.map((day) => lang(day + '_short')),
            months: months.map((month) => lang(month)),
            monthsShort: months.map((month) => lang(month + '_short')),
            today: lang('today'),
            clear: lang('clear'),
            dateFormat: 'dd/MM/yyyy',
            timeFormat: 'HH:mm',
            firstDay: App.Utils.Date.getWeekdayId(vars('first_weekday')),
        };
    }

    /**
     * Read a value given to a picker: a date, "today", or a string (with "Y-m-d", the flatpickr name of YYYY-MM-DD).
     *
     * @param {*} value
     * @param {String} [format]
     *
     * @return {Date|null}
     */
    function toPickerDate(value, format) {
        if (value === null || value === undefined || value === '' || value === false) {
            return null;
        }

        if (value === 'today') {
            return new Date();
        }

        const date = value instanceof Date ? moment(value) : moment(value, format === 'Y-m-d' ? 'YYYY-MM-DD' : undefined);

        return date.isValid() ? date.toDate() : null;
    }

    /**
     * Place a picker under its input, or above it when there is not enough room below, and keep it inside the window,
     * so that it never needs scrolling to be seen.
     *
     * @param {Object} params Air Datepicker position callback parameters.
     */
    function positionAirDatepicker({$datepicker, $target}) {
        const gap = 6;
        const margin = 8;
        const target = $target.getBoundingClientRect();
        const width = $datepicker.offsetWidth;
        const height = $datepicker.offsetHeight;
        const below = window.innerHeight - target.bottom - margin;
        const above = target.top - margin;

        let top = below >= height + gap || below >= above ? target.bottom + gap : target.top - gap - height;
        top = Math.max(margin, Math.min(top, window.innerHeight - margin - height));

        let left = Math.min(target.left, window.innerWidth - margin - width);
        left = Math.max(margin, left);

        // The picker is placed in its container (the page or the window it is in), which may be scrolled.
        const parent = $datepicker.offsetParent || document.body;
        const origin = parent.getBoundingClientRect();

        $datepicker.style.left = left - origin.left + parent.scrollLeft + 'px';
        $datepicker.style.top = top - origin.top + parent.scrollTop + 'px';
    }

    /**
     * Make the hours and the minutes shown in an Air Datepicker time picker editable: each number becomes a small field
     * where the time can be typed (Enter, Tab or leaving the field takes it) or moved with the arrow keys (an hour, or
     * five minutes). The fields follow the time that the picker shows.
     *
     * @param {HTMLInputElement} input The input of the picker.
     * @param {AirDatepicker} datepicker
     */
    function addEditableTime(input, datepicker) {
        const $picker = datepicker.$datepicker;
        const $current = $picker.querySelector('.air-datepicker-time--current');
        const $hoursText = $picker.querySelector('.air-datepicker-time--current-hours');
        const $minutesText = $picker.querySelector('.air-datepicker-time--current-minutes');

        // Opened again before it finished closing: its parts, and the fields, are still there.
        if (!$current || !$hoursText || !$minutesText || $current.querySelector('.ea-picker-time-field')) {
            return;
        }

        const regular = vars('time_format') === 'regular';
        const pad = (value) => String(value).padStart(2, '0');

        const createField = (part, label) => {
            const field = document.createElement('input');
            field.type = 'text';
            field.inputMode = 'numeric';
            field.maxLength = 2;
            field.autocomplete = 'off';
            field.className = 'ea-picker-time-field ea-picker-time-' + part;
            field.setAttribute('aria-label', label);
            return field;
        };

        const $hours = createField('hours', lang('hour'));
        const $minutes = createField('minutes', lang('minute'));

        $hoursText.before($hours);
        $minutesText.before($minutes);

        // The time the picker shows (the chosen one, or the one it would give to a chosen day).
        const shownTime = () => {
            const selected = datepicker.selectedDates[0];

            return selected
                ? {hours: selected.getHours(), minutes: selected.getMinutes()}
                : {hours: datepicker.timepicker?.hours ?? 0, minutes: datepicker.timepicker?.minutes ?? 0};
        };

        const showTime = () => {
            const {hours, minutes} = shownTime();

            $hours.value = pad(regular ? hours % 12 || 12 : hours);
            $minutes.value = pad(minutes);
        };

        const selectTime = (hours, minutes) => {
            const selected = datepicker.selectedDates[0];
            const date = new Date(selected || new Date());

            date.setHours(hours, minutes, 0, 0);

            if (selected && date.getTime() === selected.getTime()) {
                showTime();
                return;
            }

            datepicker.selectDate(date, {updateTime: true});
            $hours.value = pad(regular ? hours % 12 || 12 : hours);
            $minutes.value = pad(minutes);
        };

        // Take what was typed, or show the time again when it is not a valid one.
        const readTime = () => {
            const hours = Number($hours.value);
            const minutes = Number($minutes.value);
            const hoursValid = regular ? hours >= 1 && hours <= 12 : hours >= 0 && hours <= 23;

            if (!/^\d{1,2}$/.test($hours.value) || !/^\d{1,2}$/.test($minutes.value) || !hoursValid || minutes > 59) {
                showTime();
                return;
            }

            // On 12-hour times the half of the day (AM or PM) stays the shown one.
            const afternoon = shownTime().hours >= 12;

            selectTime(regular ? (hours % 12) + (afternoon ? 12 : 0) : hours, minutes);
        };

        // The picker writes the numbers again on every change of the time; the fields follow them, except the one
        // being typed in.
        new MutationObserver(() => {
            if (document.activeElement !== $hours && document.activeElement !== $minutes) {
                showTime();
            }
        }).observe($current, {childList: true, subtree: true, characterData: true});

        showTime();

        [$hours, $minutes].forEach((field) => {
            // The picker puts the focus back on its input when the mouse is released inside it.
            field.addEventListener('mouseup', (event) => event.stopPropagation());

            field.addEventListener('focus', () => field.select());

            // Two digits in the hours go on to the minutes.
            field.addEventListener('input', () => {
                field.value = field.value.replace(/\D/g, '');

                if (field === $hours && field.value.length === 2) {
                    $minutes.focus();
                }
            });

            field.addEventListener('keydown', (event) => {
                // The focus goes back to the input before the picker closes (closing removes the field).
                if (event.key === 'Enter') {
                    event.preventDefault();
                    readTime();
                    input.focus();
                    datepicker.hide();
                } else if (event.key === 'Escape') {
                    showTime();
                    input.focus();
                    datepicker.hide();
                } else if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                    event.preventDefault();

                    const step = event.key === 'ArrowUp' ? 1 : -1;
                    const {hours, minutes} = shownTime();
                    const total = (hours * 60 + minutes + step * (field === $hours ? 60 : 5) + 1440) % 1440;

                    selectTime(Math.floor(total / 60), total % 60);
                    field.select();
                }
            });

            // Leaving the fields takes the time; leaving the picker as well closes it, as leaving its input does.
            field.addEventListener('blur', (event) => {
                readTime();

                const next = event.relatedTarget;

                if (next === $hours || next === $minutes) {
                    return;
                }

                datepicker.inFocus = false;

                if (next !== input && !$picker.contains(next)) {
                    datepicker.hide();
                }
            });
        });
    }

    /**
     * Turn inputs into Air Datepicker pickers.
     *
     * The picker shows the text in the format of the settings, so the code that reads the text of the input keeps
     * working; the date can also be typed (checked when leaving the input or pressing Enter, and put back when it is
     * not a valid date). Each input gets an `_flatpickr` object with the part of the flatpickr interface this
     * application uses (selectedDates, setDate, clear, open, close, destroy), and the flatpickr options onChange,
     * onClose, minDate and maxDate keep working.
     *
     * @param {jQuery} $target
     * @param {String} mode "date", "time" or "datetime".
     * @param {Object} [params]
     */
    function initializeAirDatepicker($target, mode, params = {}) {
        const {onChange, onClose, minDate, maxDate} = params;
        const formats = getTypedFormats(mode);

        $target.each((index, input) => {
            input._flatpickr?.destroy();

            const typed = input.value.trim() ? moment(input.value.trim(), formats, true) : null;
            let picker = null;

            const notifyChange = () => input.dispatchEvent(new Event('change', {bubbles: true}));

            // "Today" keeps the chosen time; on a time picker it is "Now".
            const todayButton = {
                content: lang(mode === 'time' ? 'now' : 'today'),
                className: 'ea-picker-today',
                onClick: (datepicker) => {
                    const date = new Date();
                    const selected = datepicker.selectedDates[0];

                    if (mode === 'date') {
                        date.setHours(0, 0, 0, 0);
                    } else if (mode === 'datetime' && selected) {
                        date.setHours(selected.getHours(), selected.getMinutes(), 0, 0);
                    } else {
                        date.setSeconds(0, 0);
                    }

                    datepicker.selectDate(date, {updateTime: true});
                    datepicker.setViewDate(date);

                    if (mode === 'date') {
                        datepicker.hide();
                    }
                },
            };

            const datepicker = new AirDatepicker(input, {
                locale: getAirDatepickerLocale(),
                dateFormat: (date) => moment(date).format(formats[0]),
                timepicker: mode !== 'date',
                onlyTimepicker: mode === 'time',
                minutesStep: 5,
                autoClose: mode === 'date',
                // Clicking the chosen day again keeps it instead of leaving the picker empty.
                toggleSelected: false,
                keyboardNav: false,
                position: positionAirDatepicker,
                // Inside a window, so that the window keeps the focus and the picker scrolls with it.
                container: input.closest('.modal') || undefined,
                minDate: toPickerDate(minDate) || false,
                maxDate: toPickerDate(maxDate) || false,
                selectedDates: typed?.isValid() ? [typed.toDate()] : [],
                buttons: [todayButton],
                onSelect: () => {
                    onChange?.(picker.selectedDates, input.value, picker);
                    notifyChange();
                },
                // The picker builds its parts every time it opens, so the editable time is added then.
                onShow: (isFinished) => {
                    if (!isFinished && mode !== 'date') {
                        addEditableTime(input, datepicker);
                    }
                },
                // Called as the picker starts to close (the end of the animation is not always reported).
                onHide: (isFinished) => {
                    if (!isFinished) {
                        onClose?.(picker.selectedDates, input.value, picker);
                    }
                },
            });

            const format = (date) => (date ? moment(date).format(formats[0]) : '');

            // A typed date is taken when it is valid; otherwise the chosen one is shown again.
            const readTyped = () => {
                const text = input.value.trim();
                const selected = datepicker.selectedDates[0];

                if (!text) {
                    if (selected) {
                        datepicker.clear({silent: true});
                        input.value = '';
                        notifyChange();
                    }

                    return;
                }

                const date = moment(text, formats, true);

                if (!date.isValid()) {
                    input.value = format(selected);
                    return;
                }

                if (selected && date.valueOf() === selected.getTime()) {
                    input.value = format(selected);
                    return;
                }

                // Without updateTime the picker keeps the time it shows and drops the typed one.
                datepicker.selectDate(date.toDate(), {updateTime: true});
                datepicker.setViewDate(date.toDate());
            };

            input.setAttribute('autocomplete', 'off');

            input.addEventListener('change', (event) => {
                // Only what the person typed; the picker and this code send untrusted change events.
                if (event.isTrusted) {
                    readTyped();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    readTyped();
                    datepicker.hide();
                }
            });

            picker = {
                input,
                datepicker,
                get selectedDates() {
                    return datepicker.selectedDates.slice();
                },
                setDate(value, triggerChange = false, valueFormat) {
                    const date = toPickerDate(value, valueFormat);

                    if (!date) {
                        this.clear();
                        return;
                    }

                    datepicker.selectDate(date, {silent: !triggerChange, updateTime: true});
                    datepicker.setViewDate(date);
                    // The picker writes the text a moment later; the code that reads it right away gets it now.
                    input.value = format(date);
                },
                clear() {
                    datepicker.clear({silent: true});
                    input.value = '';
                },
                open() {
                    datepicker.show();
                },
                close() {
                    datepicker.hide();
                },
                destroy() {
                    datepicker.destroy();
                    delete input._flatpickr;
                },
            };

            input._flatpickr = picker;
        });
    }

    /**
     * Initialize the date time picker component.
     *
     * @param {jQuery} $target
     * @param {Object} [params]
     */
    function initializeDateTimePicker($target, params = {}) {
        if (usesAirDatepicker()) {
            initializeAirDatepicker($target, 'datetime', params);
            return;
        }

        $target.flatpickr({
            enableTime: true,
            allowInput: true,
            static: true,
            dateFormat: `${getDateFormat()} ${getTimeFormat()}`,
            time_24hr: vars('time_format') === 'military',
            locale: getFlatpickrLocale(),
            ...params,
        });
    }

    /**
     * Initialize the date picker component.
     *
     * @param {jQuery} $target
     * @param {Object} [params]
     */
    function initializeDatePicker($target, params = {}) {
        if (usesAirDatepicker()) {
            initializeAirDatepicker($target, 'date', params);
            return;
        }

        $target.flatpickr({
            allowInput: true,
            dateFormat: getDateFormat(),
            locale: getFlatpickrLocale(),
            static: true,
            ...params,
        });
    }

    /**
     * Initialize the time picker component.
     *
     * @param {jQuery} $target
     * @param {Object} [params]
     */
    function initializeTimePicker($target, params = {}) {
        if (usesAirDatepicker()) {
            initializeAirDatepicker($target, 'time', params);
            return;
        }

        $target.flatpickr({
            noCalendar: true,
            enableTime: true,
            allowInput: true,
            dateFormat: getTimeFormat(),
            time_24hr: vars('time_format') === 'military',
            locale: getFlatpickrLocale(),
            static: true,
            ...params,
        });
    }

    /**
     * Initialize the select dropdown component.
     *
     * This method is based on Select2.
     *
     * @param {jQuery} $target
     * @param {Object} [params]
     */
    function initializeDropdown($target, params = {}) {
        $target.select2(params);
    }

    /**
     * Initialize the text editor component.
     *
     * This method is based on Trumbowyg.
     *
     * @param {jQuery} $target
     * @param {Object} [params]
     */
    function initializeTextEditor($target, params = {}) {
        $target.trumbowyg(params);
    }

    /**
     * Get Date, Date-Time or Time picker value.
     *
     * @param {jQuery} $target
     *
     * @return {Date}
     */
    function getDateTimePickerValue($target) {
        if (!$target?.length) {
            throw new Error('Empty $target argument provided.');
        }

        return $target[0]._flatpickr.selectedDates[0];
    }

    /**
     * Set Date, Date-Time or Time picker value.
     *
     * @param {jQuery} $target
     * @param {Date} value
     */
    function setDateTimePickerValue($target, value) {
        if (!$target?.length) {
            throw new Error('Empty $target argument provided.');
        }

        return $target[0]._flatpickr.setDate(value);
    }

    /**
     * Show a select as a dropdown like the provider filter of the calendar: a button with the chosen option and a list
     * with a round check per option (and a header per option group).
     *
     * The select stays in the page, hidden, so its value, its change event and the form validation work as before:
     * choosing an option sets it and triggers "change". Hidden options are left out, disabled ones cannot be chosen
     * and the colours of an option (style background and text colour) are kept. The button follows the select: its
     * chosen option, whether it is disabled and whether it is marked invalid, also when the code changes the select
     * without a change event.
     *
     * @param {jQuery} $select
     */
    function initializeSelectDropdown($select) {
        $select.each((index, select) => {
            if (select.closest('.ea-select-dropdown')) {
                return;
            }

            createSelectDropdown($(select));
        });
    }

    /**
     * Set the text of a dropdown button, with what comes before ": " (e.g. "Provider: " in "Provider: all") in its own
     * element, so that narrow toolbars can leave it out and show the value only.
     *
     * @param {HTMLElement} element
     * @param {String} text
     */
    function setDropdownLabel(element, text) {
        const index = text.indexOf(': ');

        if (index < 0) {
            element.textContent = text;
            return;
        }

        const prefix = document.createElement('span');

        prefix.className = 'ea-dropdown-label-prefix';
        prefix.textContent = text.slice(0, index + 2);
        element.replaceChildren(prefix, document.createTextNode(text.slice(index + 2)));
    }

    // The dropdowns made from a select, kept in step with their select (see syncSelectDropdowns()).
    const selectDropdowns = [];

    /**
     * Keep every dropdown made from a select in step with it while it is on screen: the code often sets a select with
     * val() or prop('selected') without a change event.
     */
    function syncSelectDropdowns() {
        selectDropdowns.forEach((dropdown) => {
            if (dropdown.button.isConnected && dropdown.button.offsetParent) {
                dropdown.sync();
            }
        });
    }

    setInterval(syncSelectDropdowns, 250);

    /**
     * Make the dropdown of one select (see initializeSelectDropdown()).
     *
     * @param {jQuery} $select
     */
    function createSelectDropdown($select) {
        const select = $select[0];
        const $group = $('<div/>', {class: 'dropdown ea-select-dropdown'}).insertAfter($select);
        const name = 'ea-select-' + ($select.attr('id') || Math.random().toString(36).slice(2));

        $select.prop('hidden', true).appendTo($group);

        // The form classes of the select, but not "required": the validation reads the fields that have it.
        const classes = ['form-select', 'text-start'].concat(
            ['ea-simple-filter', 'form-select-sm', 'form-select-lg'].filter((className) => $select.hasClass(className)),
        );

        const $button = $('<button/>', {
            type: 'button',
            class: classes.join(' '),
            'data-bs-toggle': 'dropdown',
            'aria-expanded': 'false',
            'aria-label': $select.attr('aria-label') || $('label[for="' + select.id + '"]').text().trim() || '',
        }).appendTo($group);

        const $menu = $('<div/>', {class: 'dropdown-menu ea-select-dropdown-menu'}).appendTo($group);

        const option = (element) =>
            $('<label/>', {
                class: 'dropdown-item d-flex align-items-center gap-2' + (element.disabled ? ' disabled' : ''),
                // The colours of the option, e.g. the colour of each treatment.
                css: {
                    backgroundColor: element.style.backgroundColor || '',
                    color: element.style.color || '',
                },
                html: [
                    $('<input/>', {
                        type: 'radio',
                        name,
                        class: 'form-check-input m-0',
                        value: element.value,
                        checked: element.selected,
                        disabled: element.disabled,
                    }),
                    $('<span/>', {text: element.text}),
                ],
            });

        const sync = () => {
            const text = select.selectedIndex >= 0 ? select.options[select.selectedIndex].text : '';
            const disabled = select.disabled || Boolean(select.closest('fieldset:disabled'));
            const invalid = select.classList.contains('is-invalid');

            if ($button.text() !== text) {
                setDropdownLabel($button[0], text);
            }

            $button.prop('disabled', disabled).toggleClass('is-invalid', invalid);
        };

        // The options are read when the list opens, so that options added later are shown too.
        $group.on('show.bs.dropdown', () => {
            $menu.empty();

            $select.children().each((index, child) => {
                if (child.tagName === 'OPTGROUP') {
                    const options = $(child)
                        .children('option')
                        .filter((childIndex, element) => !element.hidden);

                    if (options.length) {
                        $('<h6/>', {class: 'dropdown-header', text: child.label}).appendTo($menu);
                        options.each((childIndex, element) => $menu.append(option(element)));
                    }
                } else if (!child.hidden) {
                    $menu.append(option(child));
                }
            });
        });

        // The chosen option is in view when the list opens.
        $group.on('shown.bs.dropdown', () => {
            $menu.find('input:checked').closest('.dropdown-item')[0]?.scrollIntoView({block: 'nearest'});
        });

        $menu.on('change', 'input', (event) => {
            $select.val(event.target.value).trigger('change');
            select.classList.remove('is-invalid');
            bootstrap.Dropdown.getOrCreateInstance($button[0]).hide();
        });

        $select.on('change', sync);

        selectDropdowns.push({button: $button[0], sync});
        sync();
    }

    return {
        initializeDateTimePicker,
        initializeDatePicker,
        initializeTimePicker,
        initializeDropdown,
        initializeSelectDropdown,
        initializeTextEditor,
        setDropdownLabel,
        getDateTimePickerValue,
        setDateTimePickerValue,
    };
})();
