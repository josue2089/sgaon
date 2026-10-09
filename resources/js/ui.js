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
    if (event.target instanceof HTMLDialogElement && event.target.classList.contains('ui-modal')) {
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
