function formatAmount(amount, decimals = 2) {
    return Number(amount || 0).toLocaleString('es-VE', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatUsd(amount) {
    return '$' + formatAmount(amount);
}

function formatEur(amount) {
    return '€' + formatAmount(amount);
}

function formatVes(amount) {
    return 'Bs ' + formatAmount(amount);
}

const CURRENCY_LABELS = {
    USD: 'USD (dólares)',
    EUR: 'EUR (euros)',
    VES: 'Bs (bolívares)',
};

function availableCurrenciesForCharge(chargeCurrency) {
    return chargeCurrency === 'EUR' ? ['EUR', 'VES'] : ['USD', 'VES'];
}

function rebuildCurrencySelect(currencySelect, chargeCurrency) {
    const available = availableCurrenciesForCharge(chargeCurrency);
    const previous = currencySelect.value;
    currencySelect.innerHTML = '';

    available.forEach((code) => {
        const option = document.createElement('option');
        option.value = code;
        option.textContent = CURRENCY_LABELS[code];
        currencySelect.appendChild(option);
    });

    currencySelect.value = available.includes(previous) ? previous : available[0];
}

function initPaymentCurrencyBlock(root) {
    const usdExchangeRate = parseFloat(root.dataset.usdExchangeRate || root.dataset.exchangeRate || '0');
    const eurExchangeRate = parseFloat(root.dataset.eurExchangeRate || '0');
    const methods = JSON.parse(root.dataset.methods || '{}');

    const currencySelect = root.querySelector('.payment-currency-select');
    const methodSelect = root.querySelector('.payment-method-select');
    const amountInput = root.querySelector('.payment-original-amount');
    const accountLines = root.querySelector('.payment-method-account-lines');
    const vesHint = root.querySelector('.payment-currency-ves-hint');
    const vesEquivalent = root.querySelector('.payment-currency-ves-equivalent');
    const ledgerPreview = root.querySelector('.payment-ledger-preview');
    const balanceLabel = root.querySelector('.payment-balance-label');

    if (!currencySelect || !methodSelect || !amountInput) {
        return;
    }

    const getChargeCurrency = () => (root.dataset.chargeCurrency || 'USD').toUpperCase();
    const getBalanceAmount = () => parseFloat(root.dataset.balanceAmount || root.dataset.balanceUsd || '0');
    const currentCurrency = () => currencySelect.value;
    const currentMethods = () => methods[currentCurrency()] || [];
    const ledgerFormatter = () => (getChargeCurrency() === 'EUR' ? formatEur : formatUsd);
    const activeExchangeRate = () => (getChargeCurrency() === 'EUR' ? eurExchangeRate : usdExchangeRate);

    const updateBalanceLabel = () => {
        if (!balanceLabel) {
            return;
        }

        const amount = getBalanceAmount();
        balanceLabel.textContent = getChargeCurrency() === 'EUR' ? formatEur(amount) : formatUsd(amount);
    };

    const renderMethods = () => {
        const items = currentMethods();
        methodSelect.innerHTML = '';

        if (items.length === 0) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = `No hay métodos configurados para ${currentCurrency()}`;
            emptyOption.disabled = true;
            emptyOption.selected = true;
            methodSelect.appendChild(emptyOption);
            methodSelect.required = false;
            accountLines.textContent = 'Configura un método de pago para esta moneda en Ajustes → Métodos de pago.';
            updateHints();
            return;
        }

        methodSelect.required = true;
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
            vesHint.hidden = rate <= 0;
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

    root.configurePaymentCurrency = ({ chargeCurrency, balanceAmount } = {}) => {
        if (chargeCurrency !== undefined) {
            root.dataset.chargeCurrency = String(chargeCurrency).toUpperCase();
            rebuildCurrencySelect(currencySelect, getChargeCurrency());
        }

        if (balanceAmount !== undefined) {
            root.dataset.balanceAmount = Number(balanceAmount).toFixed(2);
        }

        updateBalanceLabel();
        renderMethods();
        updateHints();
    };

    currencySelect.addEventListener('change', renderMethods);
    methodSelect.addEventListener('change', renderAccountDetails);
    amountInput.addEventListener('input', updateHints);

    rebuildCurrencySelect(currencySelect, getChargeCurrency());
    updateBalanceLabel();
    renderMethods();
}

document.querySelectorAll('[data-payment-currency-root]').forEach((root) => {
    initPaymentCurrencyBlock(root);
});

window.configurePaymentCurrencyBlock = (root, options = {}) => {
    if (root?.configurePaymentCurrency) {
        root.configurePaymentCurrency(options);
    }
};
