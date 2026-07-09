function initFinanceChargeForm(form) {
    const formId = form.id;
    const studentLocked = form.dataset.studentLocked === 'true';
    const chargeStudent = studentLocked ? null : document.getElementById(`${formId}-student-select`);
    const chargeEnrollment = document.getElementById(`${formId}-enrollment-select`);
    const chargeEnrollmentSearch = document.getElementById(`${formId}-enrollment-search`);

    const filterSelect = (select, { studentId = '', term = '' } = {}) => {
        if (!select) return;

        const wrap = select.closest('[data-searchable-select]');
        if (wrap?.searchableSelect?.filter) {
            wrap.searchableSelect.filter({ studentId, term });
        }
    };

    const syncChargeStudentFromEnrollment = () => {
        if (!chargeEnrollment) return;
        const selected = chargeEnrollment.selectedOptions[0];
        if (!selected || !selected.dataset.studentId) return;

        if (chargeStudent) {
            chargeStudent.value = selected.dataset.studentId;
            chargeStudent.dispatchEvent(new Event('change', { bubbles: true }));
        }

        filterSelect(chargeEnrollment, {
            studentId: selected.dataset.studentId,
            term: chargeEnrollmentSearch?.value || '',
        });
    };

    if (chargeStudent) {
        filterSelect(chargeEnrollment, { studentId: chargeStudent.value, term: chargeEnrollmentSearch?.value || '' });
        chargeStudent.addEventListener('change', () => {
            filterSelect(chargeEnrollment, { studentId: chargeStudent.value, term: chargeEnrollmentSearch?.value || '' });
        });
    } else if (studentLocked && form.dataset.studentId) {
        filterSelect(chargeEnrollment, { studentId: form.dataset.studentId, term: '' });
    }

    if (chargeEnrollmentSearch) {
        chargeEnrollmentSearch.addEventListener('input', () => {
            filterSelect(chargeEnrollment, {
                studentId: chargeStudent?.value || form.dataset.studentId || '',
                term: chargeEnrollmentSearch.value,
            });
        });
    }

    if (chargeEnrollment) {
        chargeEnrollment.addEventListener('change', syncChargeStudentFromEnrollment);
        if (chargeEnrollment.value) {
            syncChargeStudentFromEnrollment();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-finance-charge-form]').forEach((form) => {
        initFinanceChargeForm(form);
    });
});

export { initFinanceChargeForm };
