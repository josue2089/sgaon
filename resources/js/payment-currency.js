function formatUsd(amount) {
    return '$' + Number(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatEur(amount) {
    return '€' + Number(amount || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatVes(amount) {
    return 'Bs ' + Number(amount || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function initPaymentCurrencyBlock(root) {
    const chargeCurrency = (root.dataset.chargeCurrency || 'USD').toUpperCase();
    const usdExchangeRate = parseFloat(root.dataset.usdExchangeRate || root.dataset.exchangeRate || '0');
    const eurExchangeRate = parseFloat(root.dataset.eurExchangeRate || '0');
    const methods = JSON.parse(root.dataset.methods || '{}');
    const getBalanceAmount = () => parseFloat(root.dataset.balanceAmount || root.dataset.balanceUsd || '0');

    const currencySelect = root.querySelector('.payment-currency-select');
    const methodSelect = root.querySelector('.payment-method-select');
    const amountInput = root.querySelector('.payment-original-amount');
    const accountLines = root.querySelector('.payment-method-account-lines');
    const vesHint = root.querySelector('.payment-currency-ves-hint');
    const vesEquivalent = root.querySelector('.payment-currency-ves-equivalent');
    const ledgerPreview = root.querySelector('.payment-ledger-preview');

    if (!currencySelect || !methodSelect || !amountInput) {
        return;
    }

    const currentCurrency = () => currencySelect.value;
    const currentMethods = () => methods[currentCurrency()] || [];
    const ledgerFormatter = () => (chargeCurrency === 'EUR' ? formatEur : formatUsd);
    const activeExchangeRate = () => (chargeCurrency === 'EUR' ? eurExchangeRate : usdExchangeRate);

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
        const balanceAmount = getBalanceAmount();
        const rate = activeExchangeRate();
        const formatLedger = ledgerFormatter();

        if (currency === 'VES') {
            vesHint.hidden = false;
            if (rate > 0) {
                vesEquivalent.textContent = formatVes(balanceAmount * rate);
            }
            if (ledgerPreview) {
                ledgerPreview.hidden = false;
                const ledgerAmount = rate > 0 && amount > 0 ? amount / rate : 0;
                ledgerPreview.querySelector('strong').textContent = formatLedger(ledgerAmount);
            }
            amountInput.placeholder = rate > 0 ? formatVes(balanceAmount * rate) : 'Monto en Bs';
            amountInput.max = '';
        } else if (currency === 'EUR') {
            vesHint.hidden = rate > 0;
            if (rate > 0 && vesEquivalent) {
                vesEquivalent.textContent = formatVes(balanceAmount * rate);
            }
            if (ledgerPreview) {
                ledgerPreview.hidden = true;
            }
            amountInput.placeholder = formatEur(balanceAmount);
            amountInput.max = balanceAmount > 0 ? balanceAmount.toFixed(2) : '';
        } else {
            vesHint.hidden = true;
            if (ledgerPreview) {
                ledgerPreview.hidden = true;
            }
            amountInput.placeholder = formatUsd(balanceAmount);
            amountInput.max = balanceAmount > 0 ? balanceAmount.toFixed(2) : '';
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
