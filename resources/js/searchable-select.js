function initSearchableSelect(container) {
    const select = container?.querySelector('.searchable-select__list');
    const search = container.querySelector('.searchable-select__search');

    if (!select) {
        return null;
    }

    let optionsRoot = container.querySelector('.searchable-select__options');
    if (!optionsRoot) {
        optionsRoot = document.createElement('div');
        optionsRoot.className = 'searchable-select__options';
        select.insertAdjacentElement('afterend', optionsRoot);
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

    Array.from(select.options).forEach((option, index) => {
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
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        const text = document.createElement('span');
        text.className = 'searchable-select__option-text';
        text.textContent = option.textContent.trim();
        item.appendChild(text);

        optionsRoot.appendChild(item);
        items.push({ option, item, index });
    });

    const filter = ({ studentId = '', term = '' } = {}) => {
        const normalizedTerm = (term || search?.value || '').trim().toLowerCase();

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

    search?.addEventListener('input', () => filter());

    updateSelectedState();

    return {
        filter,
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
