function initSearchableSelect(container) {
    const select = container?.querySelector('.searchable-select__list');
    const search = container.querySelector('.searchable-select__search');
    const isCombo = container.hasAttribute('data-searchable-combo') && !select?.multiple;

    if (!select) {
        return null;
    }

    let optionsRoot = container.querySelector('.searchable-select__options');
    if (!optionsRoot) {
        optionsRoot = document.createElement('div');
        optionsRoot.className = 'searchable-select__options';
        select.insertAdjacentElement('afterend', optionsRoot);
    }

    if (isCombo) {
        optionsRoot.classList.add('searchable-select__options--combo');
    }

    select.classList.add('searchable-select__native');

    const items = [];

    const updateSelectedState = () => {
        items.forEach(({ option, item }) => {
            const isSelected = option.selected;
            item.classList.toggle('is-selected', isSelected);

            if (select.multiple) {
                const checkbox = item.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = isSelected;
                }
            }
        });
    };

    const syncComboSearchLabel = () => {
        if (!isCombo || !search) {
            return;
        }

        const selected = select.selectedOptions[0];
        if (selected && selected.value !== '') {
            search.value = selected.textContent.trim();
        } else {
            search.value = '';
        }
    };

    const openCombo = () => {
        if (isCombo) {
            optionsRoot.classList.add('is-open');
        }
    };

    const closeCombo = () => {
        if (isCombo) {
            optionsRoot.classList.remove('is-open');
            syncComboSearchLabel();
        }
    };

    const addItem = (option, index) => {
        const item = document.createElement(select.multiple ? 'label' : 'button');
        item.type = select.multiple ? undefined : 'button';
        item.className = 'searchable-select__option';
        item.dataset.search = option.dataset.search || option.textContent.toLowerCase();
        item.dataset.studentId = option.dataset.studentId || '';
        item.dataset.value = option.value;

        if (select.multiple) {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option.value;
            checkbox.checked = option.selected;
            checkbox.className = 'searchable-select__check';
            checkbox.addEventListener('change', () => {
                option.selected = checkbox.checked;
                updateSelectedState();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            item.appendChild(checkbox);
        } else {
            item.addEventListener('click', () => {
                Array.from(select.options).forEach((entry) => {
                    entry.selected = false;
                });
                option.selected = true;
                select.value = option.value;
                updateSelectedState();
                if (isCombo) {
                    syncComboSearchLabel();
                    closeCombo();
                }
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        const text = document.createElement('span');
        text.className = 'searchable-select__option-text';
        text.textContent = option.textContent.trim();
        item.appendChild(text);

        optionsRoot.appendChild(item);
        items.push({ option, item, index });
    };

    const buildItems = () => {
        items.length = 0;
        optionsRoot.replaceChildren();
        Array.from(select.options).forEach((option, index) => addItem(option, index));
    };

    buildItems();

    // #452: modo remoto. El <select> trae solo los alumnos ya elegidos; al escribir se piden
    // coincidencias a data-remote-url (students.search) y se agregan como opciones.
    const remoteUrl = container.dataset.remoteUrl;
    let remoteTimer = null;
    let remoteSeq = 0;
    const remoteSearch = (term) => {
        clearTimeout(remoteTimer);
        if (!remoteUrl || term.trim().length < 2) {
            return;
        }
        remoteTimer = setTimeout(async () => {
            const seq = ++remoteSeq;
            try {
                const response = await fetch(`${remoteUrl}?q=${encodeURIComponent(term.trim())}`, { headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (seq !== remoteSeq) {
                    return;
                }
                Array.from(select.options).forEach((option) => {
                    if (!option.selected && option.value !== '') {
                        option.remove();
                    }
                });
                const present = new Set(Array.from(select.options).map((option) => option.value));
                (data.results || []).forEach((result) => {
                    if (present.has(String(result.id))) {
                        return;
                    }
                    const option = new Option(result.detail ? `${result.name} · ${result.detail}` : result.name, result.id);
                    option.dataset.search = term.trim().toLowerCase();
                    select.add(option);
                });
                buildItems();
                updateSelectedState();
                if (isCombo) {
                    openCombo();
                }
            } catch {
                // Sin red: queda lo que ya había.
            }
        }, 250);
    };

    const filter = ({ studentId = '', term = '' } = {}) => {
        const normalizedTerm = (term || (isCombo ? '' : search?.value) || '').trim().toLowerCase();

        items.forEach(({ option, item, index }) => {
            if (index === 0 && option.value === '') {
                item.classList.toggle('is-hidden', false);
                return;
            }

            const optionStudentId = item.dataset.studentId || '';
            const searchText = item.dataset.search || '';
            const studentMatch = !studentId || optionStudentId === String(studentId);
            const termMatch = !normalizedTerm || searchText.includes(normalizedTerm);
            const visible = studentMatch && termMatch;

            item.classList.toggle('is-hidden', !visible);

            if (!visible && option.selected) {
                option.selected = false;
                if (!select.multiple) {
                    select.value = '';
                }
            }
        });

        updateSelectedState();
    };

    if (isCombo && search) {
        search.addEventListener('focus', () => {
            search.value = '';
            openCombo();
            filter({ term: '' });
        });

        search.addEventListener('input', () => {
            openCombo();
            if (remoteUrl) {
                remoteSearch(search.value);

                return;
            }
            filter({ term: search.value });
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                closeCombo();
            }
        });

        select.addEventListener('change', syncComboSearchLabel);
        syncComboSearchLabel();
    } else {
        search?.addEventListener('input', () => (remoteUrl ? remoteSearch(search.value) : filter()));
    }

    updateSelectedState();

    return {
        filter,
        syncFromSelect: syncComboSearchLabel,
        syncSize: () => {},
        select,
    };
}

export function syncSearchableSelectSize() {}

export { initSearchableSelect };

window.initSearchableSelect = initSearchableSelect;
window.syncSearchableSelectSize = syncSearchableSelectSize;

document.querySelectorAll('[data-searchable-select]').forEach((container) => {
    container.searchableSelect = initSearchableSelect(container);
});
