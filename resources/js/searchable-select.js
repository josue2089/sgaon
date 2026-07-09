export function syncSearchableSelectSize(select) {
    if (!select) {
        return;
    }

    const visibleCount = Array.from(select.options).filter((option, index) => {
        if (option.hidden) {
            return false;
        }

        if (!select.multiple && index === 0 && option.value === '') {
            return true;
        }

        return option.value !== '';
    }).length;

    if (select.multiple) {
        select.size = Math.min(Math.max(visibleCount, 3), 8);
        return;
    }

    select.size = Math.min(Math.max(visibleCount, 3), 6);
}

export function initSearchableSelect(container) {
    const select = container?.querySelector('.searchable-select__list');
    if (!select) {
        return null;
    }

    syncSearchableSelectSize(select);

    return {
        syncSize: () => syncSearchableSelectSize(select),
        select,
    };
}

window.initSearchableSelect = initSearchableSelect;
window.syncSearchableSelectSize = syncSearchableSelectSize;
