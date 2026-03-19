import './bootstrap';

document.querySelectorAll('[data-student-picker]').forEach((form) => {
    const modal = form.querySelector('[data-picker-modal]');
    const openButton = form.querySelector('[data-picker-open]');
    const closeButtons = form.querySelectorAll('[data-picker-close]');
    const searchInput = form.querySelector('[data-picker-search]');
    const rows = Array.from(form.querySelectorAll('[data-picker-row]'));
    const checkboxes = Array.from(form.querySelectorAll('[data-picker-checkbox]'));
    const toggleAll = form.querySelector('[data-picker-toggle-all]');
    const countNode = form.querySelector('[data-picker-count]');
    const selectionNode = form.querySelector('[data-picker-selection]');

    if (!modal || !openButton || !countNode || !selectionNode) {
        return;
    }

    const updateSummary = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        countNode.textContent = `${selected.length} seleccionados`;

        if (selected.length === 0) {
            selectionNode.textContent = 'Sin alumnos seleccionados.';
            selectionNode.classList.remove('is-filled');
            return;
        }

        const labels = selected
            .map((checkbox) => checkbox.closest('tr')?.querySelector('.table-title')?.textContent?.trim())
            .filter(Boolean);

        selectionNode.textContent = labels.join(', ');
        selectionNode.classList.add('is-filled');
    };

    const applyFilter = () => {
        const term = (searchInput?.value ?? '').trim().toLowerCase();

        rows.forEach((row) => {
            const haystack = row.dataset.search ?? '';
            row.hidden = term !== '' && !haystack.includes(term);
        });

        if (toggleAll) {
            const visibleRows = rows.filter((row) => !row.hidden);
            const visibleChecks = visibleRows
                .map((row) => row.querySelector('[data-picker-checkbox]'))
                .filter(Boolean);
            toggleAll.checked = visibleChecks.length > 0 && visibleChecks.every((checkbox) => checkbox.checked);
        }
    };

    const openModal = () => {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        searchInput?.focus();
    };

    const closeModal = () => {
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    openButton.addEventListener('click', openModal);
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    searchInput?.addEventListener('input', applyFilter);

    toggleAll?.addEventListener('change', () => {
        rows
            .filter((row) => !row.hidden)
            .forEach((row) => {
                const checkbox = row.querySelector('[data-picker-checkbox]');
                if (checkbox) {
                    checkbox.checked = toggleAll.checked;
                }
            });
        updateSummary();
    });

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateSummary();
            applyFilter();
        });
    });

    updateSummary();
    applyFilter();
});
