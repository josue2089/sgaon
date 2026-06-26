function formatUsd(amount) {
    return '$' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatVes(amount) {
    return 'Bs ' + Number(amount || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function initPaymentCurrencyBlock(root) {
    const exchangeRate = parseFloat(root.dataset.exchangeRate || '0');
    const methods = JSON.parse(root.dataset.methods || '{}');
    const getBalanceUsd = () => parseFloat(root.dataset.balanceUsd || '0');

    const currencySelect = root.querySelector('.payment-currency-select');
    const methodSelect = root.querySelector('.payment-method-select');
    const amountInput = root.querySelector('.payment-original-amount');
    const accountLines = root.querySelector('.payment-method-account-lines');
    const vesHint = root.querySelector('.payment-currency-ves-hint');
    const vesEquivalent = root.querySelector('.payment-currency-ves-equivalent');
    const usdPreview = root.querySelector('.payment-usd-preview');

    if (!currencySelect || !methodSelect || !amountInput) {
        return;
    }

    const currentCurrency = () => currencySelect.value;
    const currentMethods = () => methods[currentCurrency()] || [];

    const renderMethods = () => {
        const items = currentMethods();
        methodSelect.innerHTML = '<option value="">Selecciona método</option>';
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.label;
            methodSelect.appendChild(option);
        });
        renderAccountDetails();
        updateHints();
    };

    const renderAccountDetails = () => {
        const selected = currentMethods().find((item) => String(item.id) === String(methodSelect.value));
        if (!selected || !selected.lines?.length) {
            accountLines.textContent = methodSelect.value ? 'Sin datos adicionales de cuenta.' : 'Selecciona un método de pago.';
            return;
        }
        accountLines.innerHTML = selected.lines.map((line) => `<div>${line}</div>`).join('');
    };

    const updateHints = () => {
        const currency = currentCurrency();
        const amount = parseFloat(amountInput.value || '0');
        const balanceUsd = getBalanceUsd();

        if (currency === 'VES') {
            vesHint.hidden = false;
            if (exchangeRate > 0) {
                vesEquivalent.textContent = formatVes(balanceUsd * exchangeRate);
            }
            if (usdPreview) {
                usdPreview.hidden = false;
                const usd = exchangeRate > 0 && amount > 0 ? amount / exchangeRate : 0;
                usdPreview.querySelector('strong').textContent = formatUsd(usd);
            }
            amountInput.placeholder = exchangeRate > 0 ? formatVes(balanceUsd * exchangeRate) : 'Monto en Bs';
            amountInput.max = '';
        } else {
            vesHint.hidden = true;
            if (usdPreview) {
                usdPreview.hidden = true;
            }
            amountInput.placeholder = formatUsd(balanceUsd);
            amountInput.max = balanceUsd > 0 ? balanceUsd.toFixed(2) : '';
        }
    };

    currencySelect.addEventListener('change', renderMethods);
    methodSelect.addEventListener('change', renderAccountDetails);
    amountInput.addEventListener('input', updateHints);

    renderMethods();
}

document.querySelectorAll('[data-payment-currency-root]').forEach((root) => {
    initPaymentCurrencyBlock(root);
});
