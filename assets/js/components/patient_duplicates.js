/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Possible duplicate patients.
 *
 * Before a new patient is created, looks for patients with the same phone number or the same name and asks whether
 * to use one of them or to create the new patient anyway. Patients are never merged: relatives can share a phone and
 * two people can have the same name.
 */
App.Components.PatientDuplicates = (function () {
    /**
     * Ask what to do when a new patient looks like an existing one.
     *
     * @param {Object} customer New patient data (first_name, last_name, phone_number).
     *
     * @return {Promise<Object|null>} {create: true} to create the new patient, {id, patient} to use an existing one, or
     * null when the user cancels.
     */
    function confirmNewPatient(customer) {
        return new Promise((resolve) => {
            App.Http.Customers.duplicates(customer)
                .done((patients) => {
                    if (!patients.length) {
                        resolve({create: true});

                        return;
                    }

                    const $body = $('<div/>').append(
                        $('<p/>', {text: lang('possible_duplicates_intro')}),
                        $('<div/>', {class: 'list-group'}).append(
                            patients.map((patient) =>
                                $('<button/>', {
                                    type: 'button',
                                    class: 'list-group-item list-group-item-action d-flex align-items-center gap-2',
                                    'data-patient-id': patient.id,
                                    html: [
                                        $('<span/>', {
                                            class: 'fw-semibold',
                                            text: [patient.first_name, patient.last_name].filter(Boolean).join(' '),
                                        }),
                                        $('<small/>', {class: 'text-muted', text: patient.phone_number || ''}),
                                        $('<span/>', {
                                            class: 'badge text-bg-warning ms-auto',
                                            text: patient.same_phone ? lang('same_phone') : lang('same_name'),
                                        }),
                                        $('<span/>', {class: 'small text-primary', text: lang('use_this_patient')}),
                                    ],
                                }),
                            ),
                        ),
                    );

                    const $messageModal = App.Utils.Message.show(lang('possible_duplicates_title'), $body.html(), [
                        {
                            text: lang('close'),
                            className: 'btn btn-outline-secondary',
                            click: (event, messageModal) => {
                                resolve(null);
                                messageModal.hide();
                            },
                        },
                        {
                            text: lang('create_patient_anyway'),
                            click: (event, messageModal) => {
                                resolve({create: true});
                                messageModal.hide();
                            },
                        },
                    ]);

                    $messageModal.on('click', '[data-patient-id]', (event) => {
                        const id = Number($(event.currentTarget).data('patientId'));

                        resolve({id, patient: patients.find((patient) => Number(patient.id) === id)});
                        bootstrap.Modal.getInstance($messageModal[0]).hide();
                    });

                    // Closing the dialog with its cross or Escape counts as cancelling. The choices above resolve
                    // first, because the dialog has no animation and hiding it fires this event right away.
                    $messageModal.on('hidden.bs.modal', () => resolve(null));
                })
                .fail(() => resolve(null));
        });
    }

    return {
        confirmNewPatient,
    };
})();
