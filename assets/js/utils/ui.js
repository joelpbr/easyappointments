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

                    datepicker.selectDate(date);
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

                datepicker.selectDate(date.toDate());
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

                    datepicker.selectDate(date, {silent: !triggerChange});
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
     * The select stays in the page, hidden, so its value and change event work as before: choosing an option sets it
     * and triggers "change". The button follows the changes of the select.
     *
     * @param {jQuery} $select
     */
    function initializeSelectDropdown($select) {
        const $group = $('<div/>', {class: 'dropdown ea-select-dropdown'}).insertAfter($select);
        const name = 'ea-select-' + ($select.attr('id') || Math.random().toString(36).slice(2));

        $select.prop('hidden', true).appendTo($group);

        const $button = $('<button/>', {
            type: 'button',
            class: 'form-select text-start ' + ($select.hasClass('ea-simple-filter') ? 'ea-simple-filter' : ''),
            'data-bs-toggle': 'dropdown',
            'aria-expanded': 'false',
            'aria-label': $select.attr('aria-label') || '',
        }).appendTo($group);

        const $menu = $('<div/>', {class: 'dropdown-menu ea-select-dropdown-menu'}).appendTo($group);

        const option = (element) =>
            $('<label/>', {
                class: 'dropdown-item d-flex align-items-center gap-2',
                html: [
                    $('<input/>', {
                        type: 'radio',
                        name,
                        class: 'form-check-input m-0',
                        value: element.value,
                        checked: element.selected,
                    }),
                    $('<span/>', {text: element.text}),
                ],
            });

        const updateLabel = () => $button.text($select.find('option:selected').text());

        // The options are read when the list opens, so that options added later are shown too.
        $group.on('show.bs.dropdown', () => {
            $menu.empty();

            $select.children().each((index, child) => {
                if (child.tagName === 'OPTGROUP') {
                    $('<h6/>', {class: 'dropdown-header', text: child.label}).appendTo($menu);
                    $(child)
                        .children('option')
                        .each((childIndex, element) => $menu.append(option(element)));
                } else {
                    $menu.append(option(child));
                }
            });
        });

        $menu.on('change', 'input', (event) => {
            $select.val(event.target.value).trigger('change');
            bootstrap.Dropdown.getOrCreateInstance($button[0]).hide();
        });

        $select.on('change', updateLabel);

        updateLabel();
    }

    return {
        initializeDateTimePicker,
        initializeDatePicker,
        initializeTimePicker,
        initializeDropdown,
        initializeSelectDropdown,
        initializeTextEditor,
        getDateTimePickerValue,
        setDateTimePickerValue,
    };
})();
