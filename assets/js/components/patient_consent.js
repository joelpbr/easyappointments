/**
 * Consent of the patient signed with a finger on the tablet: the button in the clinical record header shows whether it is
 * signed, and opens the full-screen window with the text, the optional consents (never ticked beforehand), who signs
 * and the pad. The signature is kept as a PDF in the documents of the patient (Patient_consents controller).
 */
App.Components.PatientConsent = (function () {
    let customerId = null;
    let form = null;
    let request = 0;
    let saving = false;
    let pad;
    let context;
    let drawing = false;
    let strokes = 0;

    const $id = (id) => document.getElementById(id);
    const element = (tag, text = '', cls = '') => {
        const el = document.createElement(tag);
        el.textContent = text;
        el.className = cls;
        return el;
    };
    const url = (path, params = {}) =>
        App.Utils.Url.siteUrl('patient_consents/' + path) + '?' + new URLSearchParams(params);
    const language = () => document.querySelector('input[name="patient-consent-language"]:checked').value;
    const role = () => $id('patient-consent-role').value;

    /** The signing button is an alert, visible only for a patient with no signed consent. */
    function showStatus() {
        const button = $id('patient-consent-open');
        button.hidden = !form || !!form.current;
        button.disabled = !form;
    }

    async function setCustomer(id) {
        customerId = id ? Number(id) : null;
        form = null;
        const current = ++request;
        $id('patient-consent-open').disabled = !customerId;
        showStatus();
        if (!customerId) return;
        try {
            // CodeIgniter skips session-ID rotation for AJAX. This request runs beside the visit request;
            // rotating here could delete the session that the other request is still using.
            const response = await fetch(url('form', {customer_id: customerId}), {
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) throw new Error();
            const data = await response.json();
            if (current !== request) return;
            form = data;
            showStatus();
        } catch {
            if (current === request) $id('patient-consent-open').disabled = true;
        }
    }

    function renderText() {
        const text = form.texts[language()];
        $id('patient-consent-title').textContent = text.title;
        $id('patient-consent-patient').textContent =
            form.patient.name + (form.patient.document ? ' · ' + form.patient.document : '');
        const sections = $id('patient-consent-sections');
        sections.replaceChildren();
        text.sections.forEach((section) => {
            sections.append(element('h3', section.title));
            section.paragraphs.forEach((paragraph) => sections.append(element('p', paragraph)));
        });
        // The optional consents keep what was ticked when the language changes; they start unticked.
        const options = $id('patient-consent-options');
        const ticked = Object.fromEntries(
            [...options.querySelectorAll('input')].map((input) => [input.value, input.checked]),
        );
        options.replaceChildren();
        const descriptions = $id('patient-consent-option-descriptions');
        descriptions.replaceChildren();
        const labels =
            language() === 'es'
                ? {
                      images: 'Autorizo imágenes anónimas para docencia o divulgación',
                  }
                : {
                      images: 'Autoritzo imatges anònimes per a docència o divulgació',
                  };
        Object.entries(text.options).forEach(([key, label]) => {
            const description = element('p', label);
            description.id = 'patient-consent-description-' + key;
            descriptions.append(description);
            const wrap = element('div', '', 'form-check');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.id = 'patient-consent-option-' + key;
            input.value = key;
            input.checked = !!ticked[key];
            input.setAttribute('aria-describedby', description.id);
            const text = element('label', labels[key] || label, 'form-check-label');
            text.htmlFor = input.id;
            wrap.append(input, text);
            options.append(wrap);
        });
        $id('patient-consent-declaration').textContent = text.declaration;
    }

    function signerDefaults() {
        const representative = role() === 'representative';
        $id('patient-consent-signer-fields').hidden = !representative;
        ['patient-consent-name', 'patient-consent-document', 'patient-consent-relationship'].forEach((id) => {
            $id(id).disabled = !representative;
        });
        $id('patient-consent-name').value = representative ? '' : form.patient.name;
        $id('patient-consent-document').value = representative ? '' : form.patient.document;
        $id('patient-consent-relationship').value = '';
    }

    /* Signature pad: pointer events, so it works with a finger, a pencil or a mouse; sized for sharp lines. */
    function resizePad(preserve = false) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const width = Math.round(pad.clientWidth * ratio);
        const height = Math.round(pad.clientHeight * ratio);
        if (!width || !height) return;
        if (preserve === true && pad.width === width && pad.height === height) return;
        const previous = document.createElement('canvas');
        previous.width = pad.width;
        previous.height = pad.height;
        if (preserve === true) previous.getContext('2d').drawImage(pad, 0, 0);
        pad.width = width;
        pad.height = height;
        context = pad.getContext('2d');
        context.scale(ratio, ratio);
        context.lineWidth = 2.5;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#000';
        if (preserve === true) {
            context.drawImage(previous, 0, 0, width / ratio, height / ratio);
        } else {
            strokes = 0;
        }
    }

    function point(event) {
        const rect = pad.getBoundingClientRect();
        return [event.clientX - rect.left, event.clientY - rect.top];
    }

    function initializePad() {
        pad = $id('patient-consent-pad');
        pad.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            pad.setPointerCapture(event.pointerId);
            drawing = true;
            $id('patient-consent-error').textContent = '';
            const [x, y] = point(event);
            context.beginPath();
            context.moveTo(x, y);
            context.lineTo(x + 0.1, y + 0.1);
            context.stroke();
        });
        pad.addEventListener('pointermove', (event) => {
            if (!drawing) return;
            event.preventDefault();
            const [x, y] = point(event);
            context.lineTo(x, y);
            context.stroke();
            strokes++;
        });
        const stop = () => (drawing = false);
        pad.addEventListener('pointerup', stop);
        pad.addEventListener('pointercancel', stop);
        $id('patient-consent-clear').addEventListener('click', resizePad);
    }

    function open() {
        if (!form) return;
        $id('patient-consent-error').textContent = '';
        $id('patient-consent-options').replaceChildren();
        $id('patient-consent-ca').checked = true;
        $id('patient-consent-role').value = 'patient';
        const warning = $id('patient-consent-warning');
        warning.hidden = !form.dpo_missing;
        warning.textContent = lang('consent_dpo_missing');
        renderText();
        signerDefaults();
        App.Components.DocumentDialog.show($id('patient-consent-modal'));
        resizePad();
    }

    async function save() {
        if (saving || !form) return;
        const $error = $id('patient-consent-error');
        $error.textContent = '';
        if (strokes < 5) {
            $error.textContent = lang('consent_signature_empty');
            return;
        }
        const choices = Object.fromEntries(
            [...$id('patient-consent-options').querySelectorAll('input')].map((input) => [input.value, input.checked]),
        );
        saving = true;
        $id('patient-consent-save').disabled = true;
        try {
            const response = await fetch(url('sign'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams({
                    csrf_token: vars('csrf_token'),
                    customer_id: customerId,
                    consent: JSON.stringify({
                        language: language(),
                        choices,
                        signer: {
                            role: role(),
                            name: $id('patient-consent-name').value,
                            document: $id('patient-consent-document').value,
                            relationship: $id('patient-consent-relationship').value,
                        },
                        signature: pad.toDataURL('image/png'),
                    }),
                }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || result.success === false) throw new Error(result.message || lang('consent_save_error'));
            form.current = result.current;
            showStatus();
            App.Components.DocumentDialog.close($id('patient-consent-modal'));
            App.Layouts.Backend.displayNotification(lang('consent_saved'));
        } catch (error) {
            $error.textContent = error.message || lang('consent_save_error');
        } finally {
            saving = false;
            $id('patient-consent-save').disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!$id('patient-consent-modal')) return;
        initializePad();
        new ResizeObserver(() => {
            if ($id('patient-consent-modal').open) resizePad(true);
        }).observe(pad);
        $id('patient-consent-open').addEventListener('click', open);
        $id('patient-consent-save').addEventListener('click', save);
        const dialog = $id('patient-consent-modal');
        dialog.addEventListener('focusin', (event) => event.stopPropagation());
        dialog.querySelectorAll('[data-consent-close]').forEach((button) => {
            button.addEventListener('click', () => App.Components.DocumentDialog.close(dialog));
        });
        window.addEventListener('resize', () => {
            if ($id('patient-consent-modal').open && !strokes) resizePad();
        });
        document
            .querySelectorAll('input[name="patient-consent-language"]')
            .forEach((input) => input.addEventListener('change', renderText));
        $id('patient-consent-role').addEventListener('change', signerDefaults);
    });

    return {setCustomer};
})();
