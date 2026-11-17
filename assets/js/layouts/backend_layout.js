/* ---------------------------------------------------------------------------- *
 * @package     ProgramaConsulta
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

/**
 * Backend layout.
 *
 * This module implements the backend layout functionality.
 */
window.App.Layouts.Backend = (function () {
    const $notification = $('#notification');
    const $loading = $('#loading');

    const DB_SLUG_ADMIN = 'admin';
    const DB_SLUG_PROVIDER = 'provider';
    const DB_SLUG_SECRETARY = 'secretary';
    const DB_SLUG_CUSTOMER = 'customer';

    const PRIV_VIEW = 1;
    const PRIV_ADD = 2;
    const PRIV_EDIT = 4;
    const PRIV_DELETE = 8;

    const PRIV_APPOINTMENTS = 'appointments';
    const PRIV_CUSTOMERS = 'customers';
    const PRIV_SERVICES = 'services';
    const PRIV_USERS = 'users';
    const PRIV_SYSTEM_SETTINGS = 'system_settings';
    const PRIV_USER_SETTINGS = 'user_settings';

    /**
     * Display backend notifications to user.
     *
     * Using this method you can display notifications to the use with custom messages. If the 'actions' array is
     * provided then an action link will be displayed too.
     *
     * @param {String} message Notification message
     * @param {Array} [actions] An array with custom actions that will be available to the user. Every array item is an
     * object that contains the 'label' and 'function' key values.
     */
    function displayNotification(message, actions = []) {
        if (!message) {
            return;
        }

        const $toast = $(`
            <div class="toast bg-dark d-flex align-items-center fade show position-fixed p-1 m-4 bottom-0 end-0 backend-notification" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-body w-100 text-white">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `).appendTo('body');

        actions.forEach(function (action) {
            $('<button/>', {
                class: 'btn btn-light btn-sm ms-2',
                text: action.label,
                on: {
                    click: action.function,
                },
            }).prependTo($toast);
        });

        const toast = new bootstrap.Toast($toast[0]);

        toast.show();

        setTimeout(() => {
            toast.dispose();
            $toast.remove();
        }, 5000);
    }

    /**
     * Whether the amounts of money are hidden on this browser.
     *
     * @return {Boolean}
     */
    function isMoneyHidden() {
        return document.documentElement.classList.contains('ea-money-hidden');
    }

    /**
     * Hide or show every amount of money of the pages. The elements marked with "ea-money" are hidden by the styles;
     * the options of a list cannot be hidden in part, so those with "data-money-label" show that label, without the amount,
     * instead. They are hidden by default: a choice made in the user menu is remembered on this browser.
     *
     * @param {Boolean} hidden
     * @param {Boolean} [remember] Remember it on this browser (only when someone chose it).
     */
    function setMoneyHidden(hidden, remember = false) {
        document.documentElement.classList.toggle('ea-money-hidden', hidden);

        if (remember) {
            try {
                localStorage.setItem('ea-money-shown', hidden ? '0' : '1');
            } catch (error) {
                // Without storage the choice only lasts for this page.
            }
        }

        updateMoneyLabels();

        const $toggle = $('#toggle-money');
        const label = $toggle.data(hidden ? 'labelShow' : 'labelHide');

        $toggle.attr('aria-pressed', String(hidden));
        $toggle.find('.ea-money-toggle-label').text(label);
    }

    /**
     * Show the options with an amount with or without it.
     */
    function updateMoneyLabels() {
        const hidden = isMoneyHidden();

        $('option[data-money-label]').each((index, option) => {
            if (option.dataset.fullLabel === undefined) {
                option.dataset.fullLabel = option.textContent.trim();
            }

            option.textContent = hidden ? option.dataset.moneyLabel : option.dataset.fullLabel;
        });
    }

    /**
     * Initialize the module.
     */
    function initialize() {
        setMoneyHidden(isMoneyHidden());

        $('#toggle-money').on('click', () => setMoneyHidden(!isMoneyHidden(), true));

        let loadingTimer;
        $(document).ajaxStart(() => {
            loadingTimer = setTimeout(() => $loading.show(), 180);
        });

        $(document).ajaxStop(() => {
            clearTimeout(loadingTimer);
            $loading.hide();
        });

        tippy('[data-tippy-content]');

        // On phones the toolbar of the calendar is folded behind its own button in the header. It slides open and
        // closed like the main menu (Bootstrap's collapse: height in 0.35s, ease), at once with reduced motion.
        $('#toggle-calendar-toolbar').on('click', (event) => {
            const toolbar = document.getElementById('calendar-toolbar');
            const open = !toolbar.classList.contains('ea-toolbar-open');
            const animate = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            $(event.currentTarget).attr('aria-expanded', String(open)).toggleClass('active', open);

            toolbar.getAnimations?.().forEach((animation) => animation.cancel());

            if (open) {
                toolbar.classList.add('ea-toolbar-open');
            }

            if (!animate) {
                toolbar.classList.toggle('ea-toolbar-open', open);
                return;
            }

            const height = toolbar.scrollHeight + 'px';
            const slide = toolbar.animate(
                open
                    ? [
                          {height: '0px', overflow: 'hidden'},
                          {height, overflow: 'hidden'},
                      ]
                    : [
                          {height, overflow: 'hidden'},
                          {height: '0px', overflow: 'hidden'},
                      ],
                {duration: 350, easing: 'ease'},
            );

            slide.onfinish = () => toolbar.classList.toggle('ea-toolbar-open', open);
        });

        // Language switch of the user menu (Catalan or Spanish).
        $(document).on('click', '.ea-change-language', (event) => {
            event.preventDefault();

            $.post(App.Utils.Url.siteUrl('localization/change_language'), {
                csrf_token: vars('csrf_token'),
                language: $(event.currentTarget).data('language'),
            }).done(() => window.location.reload());
        });
    }

    /**
     * Fade an element in, with the fade of the Bootstrap tabs, when what it shows changes (another record, another
     * visit). It restarts the fade when it is still running.
     *
     * @param {jQuery} $target
     */
    function fadeIn($target) {
        $target.each((index, element) => {
            element.classList.remove('ea-fade-in');
            void element.offsetWidth; // Restart the animation.
            element.classList.add('ea-fade-in');
        });
    }

    document.addEventListener('DOMContentLoaded', initialize);

    return {
        DB_SLUG_ADMIN,
        DB_SLUG_PROVIDER,
        DB_SLUG_SECRETARY,
        DB_SLUG_CUSTOMER,
        PRIV_VIEW,
        PRIV_ADD,
        PRIV_EDIT,
        PRIV_DELETE,
        PRIV_APPOINTMENTS,
        PRIV_CUSTOMERS,
        PRIV_SERVICES,
        PRIV_USERS,
        PRIV_SYSTEM_SETTINGS,
        PRIV_USER_SETTINGS,
        displayNotification,
        fadeIn,
        isMoneyHidden,
    };
})();
