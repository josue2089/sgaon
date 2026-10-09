// Componentes de UI compartidos: modales (<dialog>), confirmaciones con motivo y errores junto al campo.

// --- Modales x-ui.modal -------------------------------------------------------
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        document.getElementById(opener.dataset.modalOpen)?.showModal();
        return;
    }
    if (event.target.closest('[data-modal-close]')) {
        event.target.closest('dialog')?.close();
        return;
    }
    // Clic en el fondo (fuera del contenido) cierra el modal.
    if (event.target instanceof HTMLDialogElement && (event.target.classList.contains('ui-modal') || event.target.classList.contains('fi-drawer'))) {
        const rect = event.target.getBoundingClientRect();
        const outside = event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
        if (outside) {
            event.target.close();
        }
    }
});

// --- Confirmaciones: <form data-confirm="Mensaje" [data-confirm-reason="Motivo…"] [data-confirm-danger]> --
const confirmDialog = document.querySelector('[data-confirm-dialog]');

if (confirmDialog) {
    const title = confirmDialog.querySelector('[data-confirm-title]');
    const message = confirmDialog.querySelector('[data-confirm-message]');
    const reasonWrap = confirmDialog.querySelector('[data-confirm-reason-wrap]');
    const reasonLabel = confirmDialog.querySelector('[data-confirm-reason-label]');
    const reasonInput = confirmDialog.querySelector('[data-confirm-reason]');
    const reasonError = confirmDialog.querySelector('[data-confirm-reason-error]');
    const okButton = confirmDialog.querySelector('[data-confirm-ok]');
    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) {
            return;
        }
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        pending = { form, submitter: event.submitter };

        const needsReason = form.dataset.confirmReason !== undefined;
        title.textContent = form.dataset.confirmTitle || 'Confirmar';
        message.textContent = form.dataset.confirm;
        reasonWrap.hidden = !needsReason;
        reasonLabel.textContent = form.dataset.confirmReason || 'Motivo';
        reasonInput.value = '';
        reasonInput.required = needsReason;
        reasonError.hidden = true;
        okButton.textContent = form.dataset.confirmOk || 'Confirmar';
        okButton.classList.toggle('btn-danger', form.dataset.confirmDanger !== undefined);
        confirmDialog.showModal();
        (needsReason ? reasonInput : okButton).focus();
    }, true);

    okButton.addEventListener('click', (event) => {
        event.preventDefault();
        if (!pending) {
            confirmDialog.close();
            return;
        }
        if (reasonInput.required && !reasonInput.value.trim()) {
            reasonError.hidden = false;
            reasonInput.focus();
            return;
        }

        const { form, submitter } = pending;
        pending = null;
        if (reasonInput.required) {
            let field = form.querySelector('[name="reason"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'reason';
                form.appendChild(field);
            }
            field.value = reasonInput.value.trim();
        }
        confirmDialog.close();
        form.dataset.confirmed = '1';
        form.requestSubmit(submitter && form.contains(submitter) ? submitter : undefined);
    });

    confirmDialog.addEventListener('close', () => {
        pending = null;
    });
}

// --- Errores de validación junto a su campo -------------------------------------
const errorsNode = document.getElementById('form-errors');

if (errorsNode) {
    let errors = {};
    try {
        errors = JSON.parse(errorsNode.textContent || '{}');
    } catch {
        errors = {};
    }

    Object.entries(errors).forEach(([key, messages]) => {
        const parts = key.split('.');
        const bracketName = parts[0] + parts.slice(1).map((part) => `[${part}]`).join('');
        const field = document.querySelector(`main [name="${CSS.escape(bracketName)}"], main [name="${CSS.escape(parts[0])}[]"]`);
        if (!field || field.type === 'hidden' || field.closest('.form-field--invalid')) {
            return;
        }
        field.setAttribute('aria-invalid', 'true');
        const note = document.createElement('div');
        note.className = 'field-error';
        note.setAttribute('role', 'alert');
        note.textContent = Array.isArray(messages) ? messages[0] : String(messages);
        const anchor = field.closest('.searchable-select') || field;
        anchor.insertAdjacentElement('afterend', note);
    });
}

// --- Menús del header (<details class="fi-menu">): uno abierto a la vez; se cierran con clic fuera o Esc --
const headerMenus = () => Array.from(document.querySelectorAll('details.fi-menu'));

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !menu.classList.contains('fi-menu') || !menu.open) {
        return;
    }
    headerMenus().forEach((other) => {
        if (other !== menu) {
            other.open = false;
        }
    });
}, true);

document.addEventListener('click', (event) => {
    headerMenus().forEach((menu) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }
    headerMenus().forEach((menu) => {
        if (menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});

// --- Búsqueda global de alumnos -------------------------------------------------
document.querySelectorAll('[data-student-search]').forEach((box) => {
    const input = box.querySelector('[data-student-search-input]');
    const list = box.querySelector('[data-student-search-results]');
    let timer = null;
    let controller = null;
    let activeIndex = -1;

    const options = () => Array.from(list.querySelectorAll('[role="option"]'));

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    };

    const highlight = (index) => {
        const items = options();
        if (items.length === 0) {
            return;
        }
        activeIndex = (index + items.length) % items.length;
        items.forEach((item, position) => item.setAttribute('aria-selected', position === activeIndex ? 'true' : 'false'));
        input.setAttribute('aria-activedescendant', items[activeIndex].id);
    };

    const render = (results, term) => {
        list.innerHTML = '';
        if (results.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'fi-search-empty';
            empty.textContent = `Sin alumnos para “${term}”`;
            list.appendChild(empty);
        }
        results.forEach((result, index) => {
            const link = document.createElement('a');
            link.href = result.url;
            link.id = `${list.id}-${index}`;
            link.className = 'fi-search-result';
            link.setAttribute('role', 'option');
            const name = document.createElement('strong');
            name.textContent = result.name;
            link.appendChild(name);
            if (result.detail) {
                const detail = document.createElement('small');
                detail.textContent = result.detail;
                link.appendChild(detail);
            }
            list.appendChild(link);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
    };

    const search = async () => {
        const term = input.value.trim();
        if (term.length < 2) {
            close();
            return;
        }
        controller?.abort();
        controller = new AbortController();
        try {
            const url = new URL(box.dataset.url, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            render(data.results || [], term);
        } catch (error) {
            if (error.name !== 'AbortError') {
                close();
            }
        }
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, 220);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(activeIndex - 1);
        } else if (event.key === 'Enter') {
            const items = options();
            const target = items[activeIndex] || (items.length === 1 ? items[0] : null);
            if (target) {
                event.preventDefault();
                window.location.href = target.href;
            }
        } else if (event.key === 'Escape') {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!box.contains(event.target)) {
            close();
        }
    });
});

// --- Pestañas genéricas: [data-tabs] con botones [data-tab-target] y paneles [data-tab-panel] -----
// Admite enlace directo: #finanzas abre esa pestaña; #student-finance abre la pestaña que contiene ese elemento.
document.querySelectorAll('[data-tabs]').forEach((tabList) => {
    const tabs = Array.from(tabList.querySelectorAll('[data-tab-target]'));
    const panels = tabs
        .map((tab) => document.querySelector(`[data-tab-panel="${tab.dataset.tabTarget}"]`))
        .filter(Boolean);

    const activate = (key, updateHash = true) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.tabTarget === key;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.tabPanel === key));
        if (updateHash) {
            history.replaceState(null, '', `#${key}`);
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.tabTarget));
        tab.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
                return;
            }
            const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];
            next.focus();
            activate(next.dataset.tabTarget);
        });
    });

    const openFromHash = () => {
        const hash = decodeURIComponent(window.location.hash.slice(1));
        if (!hash) {
            return;
        }
        if (tabs.some((tab) => tab.dataset.tabTarget === hash)) {
            activate(hash, false);
            return;
        }
        const target = document.getElementById(hash);
        const panel = target?.closest('[data-tab-panel]');
        if (panel && panels.includes(panel)) {
            activate(panel.dataset.tabPanel, false);
            target.scrollIntoView({ block: 'start' });
        }
    };

    openFromHash();
    window.addEventListener('hashchange', openFromHash);
});

// Modales que deben abrirse al cargar (p. ej. el formulario que volvió con errores).
document.querySelectorAll('dialog[data-open-on-load]').forEach((dialog) => dialog.showModal());
