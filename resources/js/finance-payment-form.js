function initFinancePaymentForm(form) {
    const formId = form.id;
    const studentLocked = form.dataset.studentLocked === 'true';
    const paymentStudent = studentLocked ? null : document.getElementById(`${formId}-student-select`);
    const paymentCharge = document.getElementById(`${formId}-charge-select`);
    const paymentChargeSearch = document.getElementById(`${formId}-charge-search`);
    const prefix = form.querySelector('[data-payment-currency-root]')?.dataset.paymentCurrencyRoot || 'finance-payment';
    const paymentCurrencyRoot = form.querySelector(`[data-payment-currency-root="${prefix}"]`);
    const paymentOriginalAmount = paymentCurrencyRoot?.querySelector('.payment-original-amount');

    const filterSelect = (select, { studentId = '', term = '' } = {}) => {
        if (!select) return;

        const wrap = select.closest('[data-searchable-select]');
        if (wrap?.searchableSelect?.filter) {
            wrap.searchableSelect.filter({ studentId, term });
            return;
        }

        const normalizedTerm = term.trim().toLowerCase();

        Array.from(select.options).forEach((option, index) => {
            if (index === 0 && option.value === '') {
                option.hidden = false;
                return;
            }

            const optionStudentId = option.dataset.studentId || '';
            const searchText = option.dataset.search || option.textContent.toLowerCase();
            const studentMatch = !studentId || optionStudentId === String(studentId);
            const termMatch = !normalizedTerm || searchText.includes(normalizedTerm);
            const visible = studentMatch && termMatch;
            option.hidden = !visible;

            if (!visible && option.selected) {
                option.selected = false;
                if (!select.multiple) {
                    select.value = '';
                }
            }
        });
    };

    const syncPaymentStudentFromCharge = () => {
        if (!paymentCharge) return;
        const selectedOptions = Array.from(paymentCharge.selectedOptions);
        if (selectedOptions.length === 0) return;

        const studentId = selectedOptions[0].dataset.studentId || '';
        if (paymentStudent && studentId) {
            paymentStudent.value = studentId;
            paymentStudent.dispatchEvent(new Event('change', { bubbles: true }));
            filterSelect(paymentCharge, { studentId, term: paymentChargeSearch?.value || '' });
        }

        const total = selectedOptions.reduce((sum, option) => sum + Number(option.dataset.balance || 0), 0);
        const chargeCurrency = selectedOptions[0]?.dataset.currency || 'USD';
        if (paymentCurrencyRoot?.configurePaymentCurrency) {
            paymentCurrencyRoot.configurePaymentCurrency({
                chargeCurrency,
                balanceAmount: total,
            });
        } else if (window.configurePaymentCurrencyBlock) {
            window.configurePaymentCurrencyBlock(paymentCurrencyRoot, {
                chargeCurrency,
                balanceAmount: total,
            });
        }
        if (paymentOriginalAmount && total > 0) {
            paymentOriginalAmount.value = total.toFixed(2);
            paymentOriginalAmount.dispatchEvent(new Event('input'));
        }
    };

    if (paymentStudent) {
        filterSelect(paymentCharge, { studentId: paymentStudent.value, term: paymentChargeSearch?.value || '' });
        paymentStudent.addEventListener('change', () => {
            filterSelect(paymentCharge, { studentId: paymentStudent.value, term: paymentChargeSearch?.value || '' });
        });
    }

    if (paymentChargeSearch) {
        paymentChargeSearch.addEventListener('input', () => {
            filterSelect(paymentCharge, {
                studentId: paymentStudent?.value || (studentLocked ? String(form.dataset.studentId || '') : ''),
                term: paymentChargeSearch.value,
            });
        });
    }

    if (paymentCharge) {
        paymentCharge.addEventListener('change', syncPaymentStudentFromCharge);
        if (paymentCharge.selectedOptions.length > 0) {
            syncPaymentStudentFromCharge();
        } else if (studentLocked && form.dataset.studentId) {
            filterSelect(paymentCharge, { studentId: form.dataset.studentId, term: '' });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-finance-payment-form]').forEach((form) => {
        initFinancePaymentForm(form);
    });
});

export { initFinancePaymentForm };
