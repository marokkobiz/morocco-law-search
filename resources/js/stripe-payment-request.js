/**
 * Stripe Checkout — hosted payment page.
 *
 * No card form is rendered in-app. A single "Pay with Stripe" button
 * POSTs to the backend Checkout Session endpoint and redirects the
 * browser to the returned Stripe-hosted URL.
 *
 * Bootstrapped from `window.MarocLoiStripe`:
 *   { checkoutUrl, csrfToken, messages: { networkError, genericError } }
 */

const config = window.MarocLoiStripe;

if (!config || !config.checkoutUrl) {
    throw new Error('Stripe checkout is not configured for this page.');
}

const messages = config.messages || {};

const ui = {
    wrap: document.getElementById('stripe-checkout-wrap'),
    button: document.getElementById('stripe-checkout-button'),
    label: document.getElementById('stripe-checkout-label'),
    spinner: document.getElementById('stripe-checkout-spinner'),
    message: document.getElementById('payment-message'),

    setBusy(busy) {
        if (this.wrap) this.wrap.classList.toggle('is-loading', busy);
        if (this.button) {
            this.button.disabled = busy;
            this.button.classList.toggle('opacity-60', busy);
            this.button.classList.toggle('cursor-not-allowed', busy);
        }
        if (this.spinner) this.spinner.classList.toggle('hidden', !busy);
    },

    clearMessage() {
        if (this.message) {
            this.message.textContent = '';
            this.message.className = 'hidden';
        }
    },

    showError(message) {
        if (!this.message) return;
        this.message.textContent = message;
        this.message.className = 'payment-alert payment-alert-error';
    },
};

function jsonHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': config.csrfToken,
    };
}

async function postJson(url, body) {
    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
    } catch {
        throw new Error(messages.networkError || 'Network error. Please try again.');
    }

    let data = {};
    try {
        data = await response.json();
    } catch {
        // non-JSON
    }

    if (!response.ok) {
        throw new Error(data.message || messages.genericError || 'Something went wrong.');
    }
    return data;
}

async function handleCheckout() {
    ui.setBusy(true);
    ui.clearMessage();

    try {
        const data = await postJson(config.checkoutUrl, {});
        const url = data.url;
        if (!url) throw new Error(messages.genericError || 'Could not start checkout.');
        window.location.href = url;
    } catch (error) {
        ui.showError(error.message);
        ui.setBusy(false);
    }
}

if (ui.button) {
    ui.button.addEventListener('click', handleCheckout);
}
