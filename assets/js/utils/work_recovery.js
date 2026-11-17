/** Wake-up/session recovery and navigation guards; drafts stay in the open page. */
window.App = window.App || {Utils: {}};
App.Utils.WorkRecovery = (function () {
    const guards = new Set();
    const siteUrl = (path) => `${vars('base_url')}${vars('index_page') ? '/' + vars('index_page') : ''}/${path}`;
    let pending = null;
    let ready = true;
    const banner = () => document.getElementById('connection-status');

    function show(key) {
        const main = banner();
        if (!main) return;
        document.querySelectorAll('.work-connection-status').forEach((el) => el.remove());
        const host = document.querySelector('dialog[open]') || document.querySelector('.modal.show .modal-content');
        const boxes = [main];
        if (key && host) {
            const notice = document.createElement('div');
            notice.className = 'work-connection-status alert alert-warning m-2';
            notice.setAttribute('role', 'status');
            host.prepend(notice);
            boxes.push(notice);
        }
        boxes.forEach((box) => {
        box.replaceChildren();
        box.hidden = !key;
        if (!key) return;
        const text = document.createElement('span');
        text.textContent = lang(key);
        box.append(text);
        if (key === 'work_session_expired') {
            const link = document.createElement('a');
            link.href = siteUrl('login');
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'btn btn-sm btn-outline-dark ms-2';
            link.textContent = lang('work_login');
            box.append(link);
        }
        if (key !== 'work_reconnecting') {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-sm btn-outline-dark ms-2';
            retry.textContent = lang('work_retry');
            retry.addEventListener('click', check);
            box.append(retry);
        }
        });
    }

    function check() {
        if (pending) return pending;
        if (document.hidden) return Promise.resolve(false);
        ready = false;
        show('work_reconnecting');
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 12000);
        pending = (async () => {
            try {
                const response = await fetch(siteUrl('user/session_status'), {
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                });
                if (response.status === 401 || response.status === 403) {
                    show('work_session_expired');
                    return false;
                }
                if (!response.ok) throw new Error('Connection failed');
                const result = await response.json();
                if (!result.authenticated || Number(result.user_id) !== Number(banner()?.dataset.userId)) {
                    show('work_session_expired');
                    return false;
                }
                vars().csrf_token = result.csrf_token;
                ready = true;
                show(null);
                window.dispatchEvent(new Event('app:reconnected'));
                return true;
            } catch {
                show('work_offline');
                return false;
            } finally {
                clearTimeout(timer);
                pending = null;
            }
        })();
        return pending;
    }

    function hasUnsaved() {
        return [...guards].some((guard) => guard());
    }

    window.addEventListener('beforeunload', (event) => {
        if (!hasUnsaved()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        if (!link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey) return;
        const target = new URL(link.href, location.href);
        if (!['http:', 'https:'].includes(target.protocol) ||
            (target.pathname === location.pathname && target.search === location.search && target.hash)) return;
        if (hasUnsaved() && !window.confirm(lang('clinical_unsaved_changes'))) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) ready = false;
        else check();
    });
    window.addEventListener('pageshow', (event) => { if (event.persisted) check(); });
    window.addEventListener('online', check);
    window.addEventListener('offline', () => { ready = false; show('work_offline'); });
    setInterval(() => { if (!ready && !document.hidden) check(); }, 30000);

    return {check, connectionFailed: () => { ready = false; show('work_offline'); }, isReady: () => ready, register: (guard) => guards.add(guard)};
})();
