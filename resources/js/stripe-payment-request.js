/**
 * Stripe Express Checkout Element — Google Pay / Apple Pay buttons.
 *
 * Replaces the legacy, deprecated `stripe.paymentRequest` API (Payment Request
 * Button, which Stripe now disables for accounts via `isPaymentRequestDeprecated`
 * kill-switch). The Express Checkout Element renders one-click wallet buttons
 * (Google Pay / Apple Pay) directly and confirms the PaymentIntent with
 * `stripe.confirmPayment`.
 *
 * This module is bootstrapped from a `window.MarocLoiStripe` configuration
 * object injected by `legal-aid-payment.blade.php`:
 *
 *   {
 *     stripeKey:        "pk_test_..."  (publishable key from config/cashier.php)
 *     intentUrl:        route to create the dynamic PaymentIntent
 *     verifyUrl:        route to server-side verify the PaymentIntent
 *     csrfToken:        Laravel CSRF token
 *     currency:         "mad"  (Moroccan Dirham — 2 decimal places)
 *     totalLabel:       localised "Total to pay"
 *     messages:         localised { success, processing, unsupported, loadStripeError,
 *                       networkError, genericError, couldNotComplete }
 *   }
 *
 * Flow:
 *   1. Fetch a fresh PaymentIntent + client_secret from our backend.
 *   2. Mount the Express Checkout Element on the client secret.
 *   3. On `confirm`, confirm via `stripe.confirmPayment` (handles 3-D Secure
 *      automatically), then POST the outcome to our backend with CSRF headers.
 */

const config = window.MarocLoiStripe;

if (!config || !config.stripeKey) {
    throw new Error('Stripe checkout is not configured for this page.');
}

const messages = config.messages || {};

/* ----------------------------------------------------------------------------
 * Small DOM helpers bound to the checkout card in the view.
 * ------------------------------------------------------------------------- */
const ui = {
    wrap: document.getElementById('google-pay-wrap'),
    button: document.getElementById('google-pay-button'),
    unsupported: document.getElementById('google-pay-unsupported'),
    message: document.getElementById('payment-message'),
    status: document.getElementById('payment-status'),

    setBusy(busy) {
        if (this.wrap) {
            this.wrap.classList.toggle('is-loading', busy);
        }
    },

    clearMessage() {
        if (this.message) {
            this.message.textContent = '';
            this.message.className = 'hidden';
        }
    },

    showError(message) {
        if (!this.message) {
            return;
        }
        this.message.textContent = message;
        this.message.className = 'payment-alert payment-alert-error';
    },

    showInfo(message) {
        if (!this.message) {
            return;
        }
        this.message.textContent = message;
        this.message.className = 'payment-alert payment-alert-info';
    },

    showStatus(message) {
        if (this.status) {
            this.status.textContent = message;
            this.status.classList.remove('hidden');
        }
    },

    showSuccess(message) {
        this.setBusy(false);
        this.showStatus(message);
        this.showInfo(message);
        if (this.button) {
            this.button.classList.add('hidden');
        }
    },

    showUnsupported() {
        this.setBusy(false);
        if (this.unsupported) {
            this.unsupported.classList.remove('hidden');
        }
    },
};

/* ----------------------------------------------------------------------------
 * Load Stripe.js once (guards against double-loading and load failures) and
 * return the configured Stripe instance (`Stripe(publishableKey)`).
 * ------------------------------------------------------------------------- */
function loadStripe() {
    if (window.Stripe) {
        return Promise.resolve(window.Stripe(config.stripeKey));
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = () => resolve(window.Stripe(config.stripeKey));
        script.onerror = () =>
            reject(new Error(messages.loadStripeError || 'Stripe.js could not be loaded.'));
        document.head.appendChild(script);
    });
}

/* ----------------------------------------------------------------------------
 * CSRF-protected JSON helpers.
 * ------------------------------------------------------------------------- */
function jsonHeaders() {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': config.csrfToken,
    };
}

/**
 * POST JSON to a Laravel route. Throws with the server's `message` on error.
 */
async function postJson(url, body) {
    let response;

    try {
        response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
    } catch (error) {
        throw new Error(messages.networkError || 'Network error. Please try again.');
    }

    let data = {};

    try {
        data = await response.json();
    } catch {
        // Non-JSON response; fall through to the status check below.
    }

    if (!response.ok || data.success === false) {
        const error = new Error(data.message || messages.genericError || 'Something went wrong.');
        error.status = response.status;
        throw error;
    }

    return data;
}

/* ----------------------------------------------------------------------------
 * Bootstrap.
 * ------------------------------------------------------------------------- */
async function boot() {
    let stripe;

    try {
        stripe = await loadStripe();
    } catch (error) {
        ui.showUnsupported();
        ui.showError(error.message);
        return;
    }

    let intent;

    try {
        intent = await postJson(config.intentUrl, {});
    } catch (error) {
        ui.showError(error.message);
        return;
    }

    const clientSecret = intent.client_secret;

    let elements;

    try {
        elements = stripe.elements({ clientSecret });
    } catch (error) {
        ui.showUnsupported();
        ui.showError(error.message);
        return;
    }

    const expressCheckout = elements.create('expressCheckout', {
        paymentMethods: {
            googlePay: 'always',
            applePay: 'auto',
            link: 'never',
        },
        layout: {
            maxColumns: 2,
            maxRows: 1,
        },
    });

    expressCheckout.on('cancel', () => {
        ui.setBusy(false);
    });

    /*
     * `availablePaymentMethods` tells us which wallets (googlePay, applePay,
     * link, browserCard, ...) Stripe was able to surface. The wallet buttons
     * are only rendered on devices/browsers where the wallet is set up, so a
     * missing wallet means we should show the fallback notice instead.
     */
    expressCheckout.on('ready', (event) => {
        const available = event.availablePaymentMethods || {};

        const hasWallet =
            available.googlePay ||
            available.applePay ||
            available.browserCard ||
            available.link;

        if (!hasWallet) {
            ui.showUnsupported();
            return;
        }

        if (ui.button) {
            ui.button.classList.remove('hidden');
        }
    });

    expressCheckout.on('confirm', async (event) => {
        ui.setBusy(true);
        ui.clearMessage();

        const completeEvent = () => {
            try {
                event.complete();
            } catch {
                // The element may already have finalized the sheet; ignore.
            }
        };

        const verify = (paymentIntentId) => postJson(config.verifyUrl, {
            payment_intent_id: paymentIntentId,
        });

        const showSuccessAndReload = () => {
            completeEvent();
            ui.showSuccess(messages.success);
            setTimeout(() => window.location.reload(), 1400);
        };

        /*
         * Google Pay payments are sometimes asynchronous (intent stuck in
         * "processing" for a few seconds). Poll the backend verify endpoint —
         * the source of truth — until the payment is confirmed server-side,
         * then reload so the server-rendered "Payment received" card appears
         * without the customer having to refresh manually.
         */
        const pollUntilVerified = async (paymentIntentId, remaining = 12) => {
            try {
                await verify(paymentIntentId);
                return { verified: true };
            } catch (error) {
                if (remaining > 0 && error.status === 409) {
                    await new Promise((resolve) => setTimeout(resolve, 2500));
                    return pollUntilVerified(paymentIntentId, remaining - 1);
                }
                return { verified: false, error };
            }
        };

        try {
            const { error, paymentIntent } = await stripe.confirmPayment({
                elements,
                clientSecret,
                confirmParams: {
                    return_url: window.location.href,
                },
                redirect: 'if_required',
            });

            if (!paymentIntent) {
                completeEvent();
                event.paymentFailed({
                    message: (error && error.message) || messages.couldNotComplete,
                });
                return;
            }

            // The backend verify endpoint re-fetches the intent from Stripe
            // and only reports success when the payment really is succeeded.
            // Google Pay wallet timing can make confirmPayment resolve with an
            // error even though the charge went through, so never trust the
            // client result alone.
            let verified;
            try {
                await verify(paymentIntent.id);
                verified = { verified: true };
            } catch (firstError) {
                if (firstError.status === 409) {
                    if (paymentIntent.status === 'processing') {
                        completeEvent();
                        ui.showStatus(messages.processing);
                    }
                    verified = await pollUntilVerified(paymentIntent.id);
                } else {
                    verified = { verified: false, error: firstError };
                }
            }

            if (verified.verified) {
                showSuccessAndReload();
                return;
            }

            completeEvent();
            event.paymentFailed({
                message:
                    (verified.error && verified.error.message) ||
                    (error && error.message) ||
                    messages.couldNotComplete,
            });
        } catch (error) {
            // The Express Checkout Element can fire the confirm event more
            // than once (or finalize the sheet itself); calling paymentFailed
            // at that point throws "Unexpected call to paymentFailed()".
            // Guard so the error never surfaces as an uncaught rejection.
            try {
                event.paymentFailed({ message: error.message });
            } catch {
                ui.showError(error.message);
            }
        } finally {
            ui.setBusy(false);
        }
    });

    try {
        expressCheckout.mount('#google-pay-button');
        ui.clearMessage();
    } catch (error) {
        ui.showUnsupported();
        ui.showError(error.message);
    }
}

boot();