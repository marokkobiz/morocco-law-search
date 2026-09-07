/**
 * Shop Checkout - handles CIN/email + cart validation and Stripe redirect
 */
const config = window.ShopCheckoutConfig;

if (config) {
    const form = document.getElementById('shop-checkout-form');
    const cinInput = document.getElementById('shop-cin');
    const emailInput = document.getElementById('shop-email');
    const nameInput = document.getElementById('shop-full-name');
    const button = document.getElementById('shop-checkout-button');
    const spinner = document.getElementById('shop-checkout-spinner');
    const label = document.getElementById('shop-checkout-label');
    const messageEl = document.getElementById('shop-checkout-message');
    const cinError = document.getElementById('shop-cin-error');
    const emailError = document.getElementById('shop-email-error');

    function setBusy(busy) {
        if (button) {
            button.disabled = busy;
            button.classList.toggle('opacity-60', busy);
            button.classList.toggle('cursor-not-allowed', busy);
        }
        if (spinner) spinner.classList.toggle('hidden', !busy);
        const wrap = document.getElementById('shop-checkout-wrap');
        if (wrap) wrap.classList.toggle('is-loading', busy);
    }

    function showMessage(text, type = 'error') {
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = type === 'error'
            ? 'mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800'
            : 'mt-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800';
        messageEl.classList.remove('hidden');
    }

    function clearMessage() {
        if (messageEl) {
            messageEl.textContent = '';
            messageEl.className = 'hidden';
        }
        if (cinError) { cinError.textContent = ''; cinError.classList.add('hidden'); }
        if (emailError) { emailError.textContent = ''; emailError.classList.add('hidden'); }
    }

    function validateCin(cin) {
        const re = /^[A-Za-z]{1,2}[0-9]{6}$/;
        return re.test(cin.trim());
    }

    async function handleSubmit(e) {
        e.preventDefault();
        clearMessage();

        const cart = window.ShopCart ? window.ShopCart.getCart() : [];
        if (!cart || cart.length === 0) {
            showMessage(config.messages.cartEmpty || 'Your cart is empty.');
            return;
        }

        const cin = cinInput ? cinInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const fullName = nameInput ? nameInput.value.trim() : '';

        let hasError = false;
        if (!cin) {
            if (cinError) { cinError.textContent = config.messages.cinInvalid || 'CIN is required.'; cinError.classList.remove('hidden'); }
            hasError = true;
        } else if (!validateCin(cin)) {
            if (cinError) { cinError.textContent = config.messages.cinInvalid || 'Invalid CIN format.'; cinError.classList.remove('hidden'); }
            hasError = true;
        }
        if (!email) {
            if (emailError) { emailError.textContent = 'Email is required.'; emailError.classList.remove('hidden'); }
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (emailError) { emailError.textContent = 'Please enter a valid email.'; emailError.classList.remove('hidden'); }
            hasError = true;
        }
        if (hasError) return;

        // Prevent duplicate submission
        if (button && button.disabled) return;

        setBusy(true);

        const items = cart.map(i => ({ price_id: i.priceId, quantity: i.quantity }));

        try {
            const res = await fetch(config.checkoutUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ cin, email, full_name: fullName || null, items }),
            });

            let data = {};
            try { data = await res.json(); } catch {}

            if (!res.ok) {
                const msg = data.message || data.error || config.messages.genericError || 'Could not start payment.';
                // Handle validation errors
                if (data.errors) {
                    const errs = data.errors;
                    if (errs.cin && cinError) { cinError.textContent = errs.cin[0]; cinError.classList.remove('hidden'); }
                    if (errs.email && emailError) { emailError.textContent = errs.email[0]; emailError.classList.remove('hidden'); }
                    if (errs.items) showMessage(errs.items[0]);
                    else showMessage(msg);
                } else {
                    showMessage(msg);
                }
                setBusy(false);
                return;
            }

            const url = data.url;
            if (!url) throw new Error(config.messages.genericError);
            window.location.href = url;
        } catch (err) {
            showMessage(err.message || config.messages.networkError || 'Network error.');
            setBusy(false);
        }
    }

    if (form) form.addEventListener('submit', handleSubmit);

    // Auto-uppercase CIN
    if (cinInput) {
        cinInput.addEventListener('input', () => {
            const pos = cinInput.selectionStart;
            cinInput.value = cinInput.value.toUpperCase();
            cinInput.setSelectionRange(pos, pos);
        });
    }
}
