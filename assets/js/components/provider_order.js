/** Shared calendar order, saved independently of the other clinic settings. */
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('provider-order-list');
    if (!list) return;
    const status = document.getElementById('provider-order-status');
    let providers = vars('calendar_providers') || [];
    let busy = false;
    let draggedId = null;

    function render(focusId, direction) {
        list.replaceChildren();
        providers.forEach((provider, index) => {
            const row = document.createElement('li');
            row.className = 'list-group-item d-flex align-items-center gap-2';
            row.dataset.id = provider.id;
            row.draggable = !busy;
            const handle = document.createElement('span');
            handle.className = 'fas fa-grip-vertical text-muted';
            handle.setAttribute('aria-hidden', 'true');
            const position = document.createElement('span');
            position.className = 'badge bg-light text-dark';
            position.textContent = index + 1;
            const name = document.createElement('span');
            name.className = 'flex-grow-1 text-truncate';
            name.textContent = provider.name;
            row.append(handle, position, name);
            for (const [action, offset, icon] of [
                ['up', -1, 'arrow-up'],
                ['down', 1, 'arrow-down'],
            ]) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-outline-secondary btn-sm';
                button.dataset.direction = action;
                button.setAttribute('aria-label', lang('provider_order_' + action) + ': ' + provider.name);
                button.title = button.getAttribute('aria-label');
                button.disabled = busy || index + offset < 0 || index + offset >= providers.length;
                const arrow = document.createElement('i');
                arrow.className = 'fas fa-' + icon;
                arrow.setAttribute('aria-hidden', 'true');
                button.append(arrow);
                button.addEventListener('click', () => move(provider.id, index + offset, action));
                row.append(button);
            }
            row.addEventListener('dragstart', (event) => {
                if (busy || event.target.closest('button')) {
                    event.preventDefault();
                    return;
                }
                draggedId = provider.id;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(provider.id));
                row.classList.add('opacity-50');
            });
            row.addEventListener('dragend', () => {
                draggedId = null;
                row.classList.remove('opacity-50');
            });
            row.addEventListener('dragover', (event) => {
                if (!busy && draggedId !== null) {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                }
            });
            row.addEventListener('drop', (event) => {
                event.preventDefault();
                if (!busy && draggedId !== null) move(draggedId, index);
            });
            list.append(row);
        });
        if (focusId) {
            const row = list.querySelector(`[data-id="${focusId}"]`);
            (
                row?.querySelector(`button[data-direction="${direction}"]:not(:disabled)`) ||
                row?.querySelector('button:not(:disabled)')
            )?.focus();
        }
    }

    async function move(id, target, direction = 'down') {
        if (busy) return;
        const from = providers.findIndex((provider) => provider.id === id);
        if (from < 0 || from === target || target < 0 || target >= providers.length) return;
        const previous = [...providers];
        providers.splice(target, 0, providers.splice(from, 1)[0]);
        busy = true;
        status.classList.remove('text-danger');
        status.textContent = lang('provider_order_saving');
        render();
        try {
            const response = await $.post(App.Utils.Url.siteUrl('general_settings/save_calendar_order'), {
                csrf_token: vars('csrf_token'),
                provider_ids: providers.map((provider) => provider.id),
            });
            if (!response?.success) throw new Error('Save failed');
            status.textContent = lang('provider_order_saved');
        } catch {
            providers = previous;
            status.classList.add('text-danger');
            status.textContent = lang('provider_order_failed');
        } finally {
            busy = false;
            render(id, direction);
        }
    }

    render();
    if (!providers.length) status.textContent = lang('provider_order_empty');
});
