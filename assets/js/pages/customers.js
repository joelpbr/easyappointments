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
 * Customers page.
 *
 * This module implements the functionality of the customers page.
 */
App.Pages.Customers = (function () {
    const $customers = $('#customers');
    const $filterCustomers = $('#filter-customers');
    const $id = $('#customer-id');
    const $firstName = $('#first-name');
    const $lastName = $('#last-name');
    const $email = $('#email');
    const $phoneNumber = $('#phone-number');
    const $address = $('#address');
    const $city = $('#city');
    const $language = $('#language');
    const $ldapDn = $('#ldap-dn');
    const $preferredProvider = $('#preferred-provider');
    const $fiscalFields = $('#fiscal-data [data-fiscal]');
    const $fiscalDocumentType = $('#fiscal-document-type');
    const $fiscalDocumentHint = $('#fiscal-document-hint');
    const $customField1 = $('#custom-field-1');
    const $customField2 = $('#custom-field-2');
    const $customField3 = $('#custom-field-3');
    const $customField4 = $('#custom-field-4');
    const $customField5 = $('#custom-field-5');
    const $formMessage = $('#form-message');
    const $customerAppointments = $('#customer-appointments');
    const $recordName = $('#customer-record-name');
    const $openClinicalRecord = $('#open-clinical-record');

    // For admins and providers, the clinical record holds the name, phone, address and NIF/NIE of the patient, which
    // this page then does not show: they are kept from the shown patient when the page saves it.
    const IDENTITY_FIELDS = ['first_name', 'last_name', 'phone_number', 'address'];
    const RECORD_FISCAL_FIELDS = ['document_type', 'document_number'];
    let currentCustomer = null;
    let currentFiscal = {};

    const moment = window.moment;

    const $filterField = $('#filter-field');
    const $customersCount = $('#customers-count');

    let filterResults = {};
    let filterLimit = 20;

    /**
     * Add the page event listeners.
     */
    function addEventListeners() {
        /**
         * Event: Filter Customers Form "Submit"
         *
         * @param {jQuery.Event} event
         */
        $customers.on('submit', '#filter-customers form', (event) => {
            event.preventDefault();
            const key = $filterCustomers.find('.key').val();
            $filterCustomers.find('.selected').removeClass('selected');
            filterLimit = 20;
            App.Pages.Customers.resetForm();
            App.Pages.Customers.filter(key);
        });

        /**
         * Event: Search Field "Change"
         *
         * The search box shows an example of the chosen field and searches again with it.
         */
        $filterField.on('change', () => {
            updateSearchField();

            if ($filterCustomers.find('.key').val().trim()) {
                $filterCustomers.find('form').trigger('submit');
            }

            $filterCustomers.find('.key').trigger('focus');
        });

        /**
         * Event: Filter Entry "Click"
         *
         * Display the customer data of the selected row.
         *
         * @param {jQuery.Event} event
         */
        $customers.on('click', '.customer-row', (event) => {
            if ($filterCustomers.find('.filter').prop('disabled')) {
                return; // Do nothing when user edits a customer record.
            }

            const customerId = $(event.currentTarget).attr('data-id');
            const customer = filterResults.find((filterResult) => Number(filterResult.id) === Number(customerId));

            App.Pages.Customers.display(customer);
            $('#filter-customers .selected').removeClass('selected');
            $(event.currentTarget).addClass('selected');
            $('#edit-customer, #delete-customer').prop('disabled', false);

            // Automatically enter edit mode
            $('#customers-page').addClass('editing');
            $customers.find('.record-details').find('input, select, textarea').prop('disabled', false);
            $customers.find('.record-details .form-label span').prop('hidden', false);
            $customers.find('#add-edit-delete-group').hide();
            $customers.find('#save-cancel-group').show();
            $customers.find('#delete-customer').show(); // Show delete button when editing
            $filterCustomers.find('button').prop('disabled', true);
            $filterCustomers.find('.results').css('color', '#AAA');
        });

        /**
         * Event: Add Customer Button "Click"
         */
        $customers.on('click', '#add-customer', () => {
            // A new patient starts with the clinical record, which holds the name and the phone.
            if (vars('clinical_records')) {
                App.Components.ClinicalRecordModal.openNew({onChange: showPatient});

                return;
            }

            App.Pages.Customers.resetForm();
            $('#customers-page').addClass('editing');
            $customers.find('#add-edit-delete-group').hide();
            $customers.find('#save-cancel-group').show();
            $customers.find('#delete-customer').hide(); // Hide delete button when adding
            $customers.find('.record-details').find('input, select, textarea').prop('disabled', false);
            $customers.find('.record-details .form-label span').prop('hidden', false);
            $filterCustomers.find('button').prop('disabled', true);
            $filterCustomers.find('.results').css('color', '#AAA');
        });

        /**
         * Event: Edit Customer Button "Click"
         */
        $customers.on('click', '#edit-customer', () => {
            $('#customers-page').addClass('editing');
            $customers.find('.record-details').find('input, select, textarea').prop('disabled', false);
            $customers.find('.record-details .form-label span').prop('hidden', false);
            $customers.find('#add-edit-delete-group').hide();
            $customers.find('#save-cancel-group').show();
            $filterCustomers.find('button').prop('disabled', true);
            $filterCustomers.find('.results').css('color', '#AAA');
        });

        /**
         * Event: Cancel Customer Add/Edit Operation Button "Click"
         */
        $customers.on('click', '#cancel-customer', () => {
            const id = $id.val();

            App.Pages.Customers.resetForm();
            $('#customers-page').removeClass('editing');

            if (id) {
                select(id, true);
            }
        });

        /**
         * Event: Open Clinical Record Button "Click"
         */
        $openClinicalRecord.on('click', () => {
            if (currentCustomer) {
                App.Components.ClinicalRecordModal.open(currentCustomer.id, {onChange: refreshIdentity});
            }
        });

        /**
         * Event: Fiscal Document Type "Change"
         */
        $fiscalDocumentType.on('change', updateFiscalDocumentHint);

        /**
         * Event: Save Add/Edit Customer Operation "Click"
         */
        $customers.on('click', '#save-customer', () => {
            const customer = {
                first_name: $firstName.val(),
                last_name: $lastName.val(),
                email: $email.val(),
                phone_number: $phoneNumber.val(),
                address: $address.val(),
                city: $city.val(),
                // The page no longer shows the zip code nor the notes, which are kept as they are, and every patient
                // is in the clinic's timezone.
                zip_code: currentCustomer?.zip_code ?? '',
                notes: currentCustomer?.notes ?? '',
                timezone: vars('default_timezone') || 'Europe/Madrid',
                language: $language.val() || 'english',
                custom_field_1: $customField1.val(),
                custom_field_2: $customField2.val(),
                custom_field_3: $customField3.val(),
                custom_field_4: $customField4.val(),
                custom_field_5: $customField5.val(),
                ldap_dn: $ldapDn.val(),
                id_users_preferred_provider: $preferredProvider.val(),
            };

            if ($id.val()) {
                customer.id = $id.val();
            }

            // Fields edited in the clinical record keep the values of the shown patient.
            IDENTITY_FIELDS.forEach((field) => {
                if (customer[field] === undefined) {
                    customer[field] = currentCustomer?.[field] ?? '';
                }
            });

            if (!App.Pages.Customers.validate()) {
                return;
            }

            if (customer.id) {
                App.Pages.Customers.save(customer);

                return;
            }

            // A new patient might already exist: show the existing one instead, or create it anyway.
            App.Components.PatientDuplicates.confirmNewPatient(customer).then((choice) => {
                if (choice?.create) {
                    App.Pages.Customers.save(customer);
                } else if (choice?.id) {
                    App.Pages.Customers.resetForm();
                    $('#customers-page').removeClass('editing');
                    App.Pages.Customers.filter(
                        [choice.patient.first_name, choice.patient.last_name].join(' ').trim(),
                        choice.id,
                        true,
                    );
                }
            });
        });

        /**
         * Event: Delete Customer Button "Click"
         */
        $customers.on('click', '#delete-customer', () => {
            const customerId = $id.val();
            const buttons = [
                {
                    text: lang('cancel'),
                    click: (event, messageModal) => {
                        messageModal.hide();
                    },
                },
                {
                    text: lang('delete'),
                    click: (event, messageModal) => {
                        App.Pages.Customers.remove(customerId);
                        messageModal.hide();
                    },
                },
            ];

            App.Utils.Message.show(lang('delete_customer'), lang('delete_record_prompt'), buttons);
        });
    }

    /**
     * Save a customer record to the database (via ajax post).
     *
     * @param {Object} customer Contains the customer data.
     */
    function save(customer) {
        App.Http.Customers.save(customer, getFiscalData()).then((response) => {
            App.Layouts.Backend.displayNotification(lang('customer_saved'));
            App.Pages.Customers.resetForm();
            $('#customers-page').removeClass('editing');
            $('#filter-customers .key').val('');
            App.Pages.Customers.filter('', response.id, true);
        });
    }

    /**
     * Delete a customer record from database.
     *
     * @param {Number} id Record id to be deleted.
     */
    function remove(id) {
        App.Http.Customers.destroy(id).then(() => {
            App.Layouts.Backend.displayNotification(lang('customer_deleted'));
            App.Pages.Customers.resetForm();
            $('#customers-page').removeClass('editing');
            App.Pages.Customers.filter($('#filter-customers .key').val());
        });
    }

    /**
     * Validate customer data before save (insert or update).
     */
    function validate() {
        $formMessage.removeClass('alert-danger').hide();
        $('.is-invalid').removeClass('is-invalid');

        try {
            // Validate required fields.
            let missingRequired = false;

            $('.required').each((index, requiredField) => {
                if ($(requiredField).val() === '') {
                    $(requiredField).addClass('is-invalid');
                    missingRequired = true;
                }
            });

            if (missingRequired) {
                throw new Error(lang('fields_are_required'));
            }

            // Validate email address.
            const email = $email.val();

            if (email && !App.Utils.Validation.email(email)) {
                $email.addClass('is-invalid');
                throw new Error(lang('invalid_email'));
            }

            // Validate phone number.
            const phoneNumber = $phoneNumber.val();

            if (phoneNumber && !App.Utils.Validation.phone(phoneNumber)) {
                $phoneNumber.addClass('is-invalid');
                throw new Error(lang('invalid_phone'));
            }

            return true;
        } catch (error) {
            $formMessage.addClass('alert-danger').text(error.message).show();
            return false;
        }
    }

    /**
     * Bring the customer form back to its initial state.
     */
    function resetForm() {
        $customers.find('.record-details').find('input, select, textarea').val('').prop('disabled', true);
        $customers.find('.record-details .form-label span').prop('hidden', true);
        $customers.find('.record-details #language').val(vars('default_language'));

        $customerAppointments.empty();

        currentCustomer = null;
        currentFiscal = {};
        $recordName.text(lang('details'));
        $openClinicalRecord.prop('disabled', true);

        $customers.find('#edit-customer, #delete-customer').prop('disabled', true);
        $customers.find('#add-edit-delete-group').show();
        $customers.find('#save-cancel-group').hide();

        $customers.find('.record-details .is-invalid').removeClass('is-invalid');
        $customers.find('.record-details #form-message').hide();

        $filterCustomers.find('button').prop('disabled', false);
        $filterCustomers.find('.selected').removeClass('selected');
        $filterCustomers.find('.results').css('color', '');
    }

    /**
     * Get the fiscal data typed in the form.
     *
     * @return {Object}
     */
    function getFiscalData() {
        const fiscal = {};

        $fiscalFields.each((index, field) => {
            fiscal[$(field).data('fiscal')] = $(field).val();
        });

        // The NIF/NIE is edited in the clinical record when this page does not show it.
        RECORD_FISCAL_FIELDS.forEach((field) => {
            if (fiscal[field] === undefined) {
                fiscal[field] = currentFiscal[field] || '';
            }
        });

        return fiscal;
    }

    /**
     * Load the fiscal data of a patient, which the patient list does not include.
     *
     * @param {Number} customerId
     */
    function displayFiscalData(customerId) {
        $fiscalFields.val('');
        currentFiscal = {};
        updateFiscalDocumentHint();

        App.Http.Customers.find(customerId).done((customer) => {
            // Another patient might have been opened in the meantime.
            if (Number($id.val()) !== Number(customerId)) {
                return;
            }

            currentFiscal = customer.fiscal || {};

            $fiscalFields.each((index, field) => {
                $(field).val(customer.fiscal?.[$(field).data('fiscal')] || '');
            });

            updateFiscalDocumentHint();
        });
    }

    /**
     * Say which documents are checked: DNI, NIE and NIF are; passports and foreign documents cannot be.
     */
    function updateFiscalDocumentHint() {
        const type = $fiscalDocumentType.val();

        $fiscalDocumentHint.text(
            ['dni', 'nie', 'nif'].includes(type)
                ? lang('fiscal_document_checked')
                : type
                  ? lang('fiscal_document_not_checked')
                  : '',
        );
    }

    /**
     * Display a customer record into the form.
     *
     * @param {Object} customer Contains the customer record data.
     */
    function display(customer) {
        currentCustomer = customer;
        showIdentity(customer);
        $id.val(customer.id);
        $firstName.val(customer.first_name);
        $lastName.val(customer.last_name);
        $email.val(customer.email);
        $phoneNumber.val(customer.phone_number);
        $address.val(customer.address);
        $city.val(customer.city);
        $language.val(customer.language || 'english');
        $ldapDn.val(customer.ldap_dn);
        $preferredProvider.val(customer.id_users_preferred_provider || '');

        // Only shown for patients who missed some appointment.
        $('#no-show-count').val(customer.no_show_count || 0);
        $('#no-show-count-group').prop('hidden', !Number(customer.no_show_count));
        displayFiscalData(customer.id);
        $customField1.val(customer.custom_field_1);
        $customField2.val(customer.custom_field_2);
        $customField3.val(customer.custom_field_3);
        $customField4.val(customer.custom_field_4);
        $customField5.val(customer.custom_field_5);

        displayHistory(customer.appointments);
    }

    /**
     * Show the name of the patient above the form, where the clinical record opens.
     *
     * @param {Object} customer
     */
    function showIdentity(customer) {
        const name = [customer.first_name, customer.last_name].filter(Boolean).join(' ');

        $recordName.text(name || lang('details'));
        $openClinicalRecord.prop('disabled', false);
    }

    /**
     * After the clinical record saved the patient, show the new name and keep the new values, without losing what is
     * being edited on this page.
     *
     * @param {Number} customerId
     */
    function refreshIdentity(customerId) {
        App.Http.Customers.find(customerId).done((customer) => {
            if (!currentCustomer || Number(currentCustomer.id) !== Number(customerId)) {
                return;
            }

            IDENTITY_FIELDS.forEach((field) => (currentCustomer[field] = customer[field]));
            currentFiscal = customer.fiscal || {};
            showIdentity(currentCustomer);

            const $row = $filterCustomers.find('.entry[data-id="' + customerId + '"]');

            if ($row.length) {
                $row.replaceWith(App.Pages.Customers.getFilterHtml(Object.assign({}, currentCustomer)));
                $filterCustomers.find('.entry[data-id="' + customerId + '"]').addClass('selected');
            }
        });
    }

    /**
     * Show a patient created (or chosen) from the clinical record.
     *
     * @param {Number} customerId
     */
    function showPatient(customerId) {
        App.Pages.Customers.resetForm();
        $('#customers-page').removeClass('editing');
        $filterCustomers.find('.key').val('');
        App.Pages.Customers.filter('', customerId, true);
    }

    /**
     * Show the administrative history of the patient: a summary of their visits, then the coming appointments and the
     * previous ones, with their status. It is not a clinical record.
     *
     * @param {Array} appointments Appointments of the patient, with their service and provider.
     */
    function displayHistory(appointments) {
        $customerAppointments.empty();

        // Providers and reception only see the appointments of their own providers.
        const visible = appointments.filter((appointment) => {
            const providerId = Number(appointment.id_users_provider);

            if (vars('role_slug') === App.Layouts.Backend.DB_SLUG_PROVIDER) {
                return providerId === Number(vars('user_id'));
            }

            if (vars('role_slug') === App.Layouts.Backend.DB_SLUG_SECRETARY) {
                return vars('secretary_providers').map(Number).includes(providerId);
            }

            return true;
        });

        if (!visible.length) {
            $('<p/>', {text: lang('no_records_found')}).appendTo($customerAppointments);

            return;
        }

        const now = moment();
        const startOf = (appointment) => moment(appointment.start_datetime);
        const providerName = (appointment) =>
            [appointment.provider?.first_name, appointment.provider?.last_name].filter(Boolean).join(' ');

        const upcoming = visible
            .filter((appointment) => startOf(appointment).isSameOrAfter(now))
            .sort((a, b) => startOf(a) - startOf(b));

        const previous = visible
            .filter((appointment) => startOf(appointment).isBefore(now))
            .sort((a, b) => startOf(b) - startOf(a));

        const visits = previous.filter((appointment) => !['cancelled', 'no_show'].includes(appointment.status));
        const count = (status) => visible.filter((appointment) => appointment.status === status).length;

        const summary = [
            [lang('history_visits'), visits.length],
            [
                lang('history_last_visit'),
                visits.length
                    ? App.Utils.Date.format(startOf(visits[0]).toDate(), vars('date_format'), vars('time_format'))
                    : '—',
            ],
            [lang('history_cancelled'), count('cancelled')],
            [lang('history_no_shows'), count('no_show')],
            [lang('history_providers'), [...new Set(visits.map(providerName))].join(', ') || '—'],
        ];

        $('<dl/>', {
            class: 'ea-patient-history-summary small mb-3',
            html: summary.flatMap(([label, value]) => [$('<dt/>', {text: label}), $('<dd/>', {text: value})]),
        }).appendTo($customerAppointments);

        const statusName = (code) => vars('appointment_statuses').find((status) => status.code === code)?.name || code;

        const statusClass = {
            cancelled: 'text-bg-secondary',
            no_show: 'text-bg-danger',
            completed: 'text-bg-success',
            confirmed: 'text-bg-primary',
        };

        const section = (title, list) => {
            if (!list.length) {
                return;
            }

            $('<h6/>', {class: 'mt-2 mb-1', text: title}).appendTo($customerAppointments);

            list.forEach((appointment) => {
                $('<div/>', {
                    class: 'appointment-row py-1 border-bottom',
                    'data-id': appointment.id,
                    html: [
                        $('<a/>', {
                            href: App.Utils.Url.siteUrl(`calendar/reschedule/${appointment.hash}`),
                            class: 'me-1',
                            title: lang('edit'),
                            html: $('<i/>', {class: 'fas fa-edit'}),
                        }),
                        $('<small/>', {
                            class: 'fw-semibold',
                            text: App.Utils.Date.format(
                                startOf(appointment).toDate(),
                                vars('date_format'),
                                vars('time_format'),
                                true,
                            ),
                        }),
                        $('<span/>', {
                            class: 'badge ms-1 ' + (statusClass[appointment.status] || 'text-bg-light'),
                            text: statusName(appointment.status),
                        }),
                        $('<br/>'),
                        $('<small/>', {
                            text: [appointment.service_name || appointment.service?.name, providerName(appointment)]
                                .filter(Boolean)
                                .join(' · '),
                        }),
                    ],
                }).appendTo($customerAppointments);
            });
        };

        section(lang('history_upcoming'), upcoming);
        section(lang('history_previous'), previous);
    }

    /**
     * Filter customer records.
     *
     * @param {String} keyword This keyword string is used to filter the customer records.
     * @param {Number} selectId Optional, if set then after the filter operation the record with the given
     * ID will be selected (but not displayed).
     * @param {Boolean} show Optional (false), if true then the selected record will be displayed on the form.
     */
    function filter(keyword, selectId = null, show = false) {
        App.Http.Customers.search(keyword, filterLimit, null, null, $filterField.val()).then((result) => {
            const response = result.customers;

            filterResults = response;
            showCount(keyword, result.count, result.total);

            $filterCustomers.find('.results').empty();

            response.forEach((customer) => {
                $('#filter-customers .results').append(App.Pages.Customers.getFilterHtml(customer)).append($('<hr/>'));
            });

            if (!response.length) {
                $filterCustomers.find('.results').append(
                    $('<em/>', {
                        'text': lang('no_records_found'),
                    }),
                );
            } else if (response.length === filterLimit) {
                $('<button/>', {
                    'type': 'button',
                    'class': 'btn btn-outline-secondary w-100 load-more text-center',
                    'text': lang('load_more'),
                    'click': () => {
                        filterLimit += 20;
                        App.Pages.Customers.filter(keyword, selectId, show);
                    },
                }).appendTo('#filter-customers .results');
            }

            if (selectId) {
                App.Pages.Customers.select(selectId, show);
            }
        });
    }

    /**
     * Get the filter results row HTML code.
     *
     * @param {Object} customer Contains the customer data.
     *
     * @return {String} Returns the record HTML code.
     */
    /**
     * Show an example of the chosen search field in the search box, and the digit keyboard for the phone.
     */
    function updateSearchField() {
        const field = $filterField.val();

        $filterCustomers
            .find('.key')
            .attr('placeholder', lang('patient_search_example_' + field))
            .attr('inputmode', field === 'phone' ? 'tel' : 'text');
    }

    /**
     * Show how many patients there are, or how many match the search.
     *
     * @param {String} keyword
     * @param {Number} count Matching patients.
     * @param {Number} total Every patient.
     */
    function showCount(keyword, count, total) {
        const format = (number) => Number(number).toLocaleString(vars('language_code'));

        $customersCount.text(
            keyword.trim()
                ? lang('patients_found').replace('%s', format(count)).replace('%s', format(total))
                : lang('patients_total').replace('%s', format(total)),
        );
    }

    function getFilterHtml(customer) {
        const name = (customer.first_name || '[No First Name]') + ' ' + (customer.last_name || '[No Last Name]');

        let info = customer.email || '[No Email]';

        info = customer.phone_number ? info + ', ' + customer.phone_number : info;

        // The document helps to tell apart patients with the same name.
        info = customer.document_number ? info + ', ' + customer.document_number : info;

        return $('<div/>', {
            'class': 'customer-row entry',
            'data-id': customer.id,
            'html': [
                $('<strong/>', {
                    'text': name,
                }),
                $('<br/>'),
                $('<small/>', {
                    'class': 'text-muted',
                    'text': info,
                }),
                $('<br/>'),
            ],
        });
    }

    /**
     * Select a specific record from the current filter results.
     *
     * If the customer id does not exist in the list then no record will be selected.
     *
     * @param {Number} id The record id to be selected from the filter results.
     * @param {Boolean} show Optional (false), if true then the method will display the record on the form.
     */
    function select(id, show = false) {
        $('#filter-customers .selected').removeClass('selected');

        $('#filter-customers .entry[data-id="' + id + '"]').addClass('selected');

        if (show) {
            const customer = filterResults.find((filterResult) => Number(filterResult.id) === Number(id));

            App.Pages.Customers.display(customer);

            $('#edit-customer, #delete-customer').prop('disabled', false);
        }
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        updateSearchField();
        App.Pages.Customers.resetForm();
        App.Pages.Customers.addEventListeners();
        App.Pages.Customers.filter('');
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        filter,
        save,
        remove,
        validate,
        getFilterHtml,
        resetForm,
        display,
        select,
        addEventListeners,
    };
})();
