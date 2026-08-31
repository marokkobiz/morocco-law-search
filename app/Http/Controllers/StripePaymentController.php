<?php

namespace App\Http\Controllers;

use App\Mail\LegalAidPaymentReceivedMail;
use App\Mail\ShopOrderConfirmationMail;
use App\Models\LegalAidRequest;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\OrderCaseService;
use App\Support\AdvisorNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use Throwable;

class StripePaymentController extends Controller
{
    /**
     * Create a Stripe Checkout Session and return its hosted URL.
     *
     * The frontend shows a single "Pay with Stripe" button; clicking it
     * POSTs here and then redirects the browser to the returned `url`
     * (Stripe hosted payment page). No card form is rendered in-app.
     */
    public function createCheckoutSession(string $ticket): JsonResponse
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        if ($legalAidRequest->isFree()) {
            return $this->error(422, 'This request is free of charge and does not require payment.');
        }

        if ($legalAidRequest->isPaid()) {
            return $this->error(409, 'This request has already been paid.');
        }

        $amountMAD = $legalAidRequest->onlineTotal;

        if ($amountMAD === null) {
            return $this->error(422, 'This request does not have a payable amount.');
        }

        $amountCents = $this->toCents($amountMAD);
        $currency = strtolower((string) config('cashier.currency', 'mad'));

        if ($amountCents < 100) {
            return $this->error(422, 'The minimum payable amount is 1.00 MAD.');
        }

        $this->cancelStaleIntents($legalAidRequest);

        $successUrl = route('legal-aid.payment.checkout.success', ['ticket' => $legalAidRequest->ticket_number]).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('legal-aid.payment', $legalAidRequest->ticket_number);

        try {
            $session = $this->stripe()->checkout->sessions->create([
                'mode' => 'payment',
                // Bank transfers via Stripe are enabled in Dashboard — don't restrict to card only.
                // Stripe will show card + any additional online banking / transfer methods you enable.
                'customer_email' => $legalAidRequest->email,
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Legal aid request '.$legalAidRequest->ticketLabel,
                            'description' => $legalAidRequest->servicesSummary ?: 'Legal consultation services',
                            'tax_code' => 'txcd_10103000',
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'legal_aid_request_id' => (string) $legalAidRequest->id,
                    'ticket_number' => $legalAidRequest->ticket_number,
                ],
                'payment_intent_data' => [
                    'description' => 'Legal aid request '.$legalAidRequest->ticketLabel,
                    'metadata' => [
                        'legal_aid_request_id' => (string) $legalAidRequest->id,
                        'ticket_number' => $legalAidRequest->ticket_number,
                    ],
                ],
                'expires_at' => now()->addMinutes(30)->getTimestamp(),
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->error(502, 'We could not initialise your payment right now. Please try again in a moment.');
        } catch (Throwable $e) {
            report($e);

            return $this->error(500, 'Something went wrong while preparing your payment.');
        }

        // Checkout Sessions have no payment_intent at creation time (null). Keep the
        // legacy stripe_payment_intent_id column populated with a placeholder so the
        // unique/not-null constraint stays satisfied. Column is now 128 chars (see
        // 2026_08_25_000002_expand_payment_intent_id_length) so cs_pending_ prefix fits.
        $pendingIntentPlaceholder = $session->payment_intent ?? 'cs_pending_'.$session->id;

        PaymentTransaction::create([
            'legal_aid_request_id' => $legalAidRequest->id,
            'stripe_payment_intent_id' => substr($pendingIntentPlaceholder, 0, 128),
            'stripe_checkout_session_id' => $session->id,
            'currency' => $currency,
            'country' => (string) config('cashier.country', 'MA'),
            'amount_cents' => $amountCents,
            'amount' => $amountMAD,
            'status' => $session->payment_status ?? PaymentTransaction::STATUS_REQUIRES_PAYMENT_METHOD,
            'payload' => json_decode((string) $session->toJSON(), true),
        ]);

        return response()->json([
            'url' => $session->url,
            'id' => $session->id,
        ]);
    }

    /**
     * Handle redirect back from Stripe Checkout (success_url).
     * Verifies the session server-side and marks the request as paid.
     */
    public function checkoutSuccess(Request $request, string $ticket)
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        // If webhook already marked it paid (race), just show success
        if ($legalAidRequest->isPaid()) {
            return redirect()->route('legal-aid.payment', $ticket)->with('status', __('legal_aid.payment_success'));
        }

        $sessionId = $request->query('session_id');

        if (! $sessionId || ! preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            report('Stripe checkoutSuccess: invalid session_id format: '.$sessionId);
            return redirect()->route('legal-aid.payment', $ticket)->with('error', __('legal_aid.payment_incomplete'));
        }

        try {
            $session = $this->stripe()->checkout->sessions->retrieve($sessionId);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('legal-aid.payment', $ticket)->with('error', __('legal_aid.payment_generic_error'));
        }

        // Ensure the session belongs to this ticket (handle both array and object metadata)
        $metadata = $session->metadata ?? [];
        $metaTicket = $metadata['ticket_number'] ?? $metadata->ticket_number ?? null;
        if (is_object($metadata)) {
            $metaTicket = $metadata->ticket_number ?? $metadata['ticket_number'] ?? $metaTicket;
        }
        $metaIdRaw = $metadata['legal_aid_request_id'] ?? $metadata->legal_aid_request_id ?? 0;
        if (is_object($metadata)) {
            $metaIdRaw = $metadata->legal_aid_request_id ?? $metadata['legal_aid_request_id'] ?? $metaIdRaw;
        }
        $metaId = (int) $metaIdRaw;
        if ($metaTicket !== $legalAidRequest->ticket_number || $metaId !== $legalAidRequest->id) {
            report('Stripe checkoutSuccess metadata mismatch: session '.$sessionId.' expected ticket '.$legalAidRequest->ticket_number.'/'.$legalAidRequest->id.' got '.$metaTicket.'/'.$metaId);
            return redirect()->route('legal-aid.payment', $ticket)->with('error', __('legal_aid.payment_generic_error'));
        }

        // Stripe marks a successful Checkout as payment_status=paid. Status is
        // typically 'complete' for hosted Checkout. Be permissive to avoid false
        // negatives in test mode or with async methods.
        if (($session->payment_status ?? null) === 'paid') {
            // Sync transaction and mark paid
            $paymentIntentId = $session->payment_intent ?? null;
            if ($paymentIntentId) {
                try {
                    $intent = $this->stripe()->paymentIntents->retrieve($paymentIntentId);
                    $this->syncTransactionFromIntent($intent);
                    // Also update the checkout transaction if exists
                    PaymentTransaction::where('stripe_checkout_session_id', $sessionId)->update([
                        'stripe_payment_intent_id' => $paymentIntentId,
                        'status' => $intent->status,
                        'payload' => json_decode((string) $session->toJSON(), true),
                    ]);
                } catch (Throwable $e) {
                    report($e);
                }
            } else {
                PaymentTransaction::where('stripe_checkout_session_id', $sessionId)->update([
                    'status' => PaymentTransaction::STATUS_SUCCEEDED,
                    'payload' => json_decode((string) $session->toJSON(), true),
                ]);
            }

            $this->markRequestPaid($legalAidRequest);

            return redirect()->route('legal-aid.payment', $ticket)->with('status', __('legal_aid.payment_success'));
        }

        if ($session->status === 'open') {
            return redirect()->route('legal-aid.payment', $ticket)->with('error', __('legal_aid.payment_incomplete'));
        }

        return redirect()->route('legal-aid.payment', $ticket)->with('error', __('legal_aid.payment_incomplete'));
    }

    /**
     * Dynamically create a Stripe PaymentIntent and return the client secret
     * to the frontend so Stripe.js can render the Google Pay / Payment Request
     * button and confirm the payment.
     *
     * The amount is always derived server-side from the request's online total,
     * never trusted from the client.
     * @deprecated Kept for backward compatibility; new flow uses Checkout Sessions.
     */
    public function createIntent(string $ticket): JsonResponse
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        if ($legalAidRequest->isFree()) {
            return $this->error(422, 'This request is free of charge and does not require payment.');
        }

        if ($legalAidRequest->isPaid()) {
            return $this->error(409, 'This request has already been paid.');
        }

        $amountMAD = $legalAidRequest->onlineTotal;

        if ($amountMAD === null) {
            return $this->error(422, 'This request does not have a payable amount.');
        }

        $amountCents = $this->toCents($amountMAD);
        $currency = strtolower((string) config('cashier.currency', 'mad'));

        if ($amountCents < 100) {
            return $this->error(422, 'The minimum payable amount is 1.00 MAD.');
        }

        $this->cancelStaleIntents($legalAidRequest);

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $legalAidRequest->email,
                'description' => 'Legal aid request '.$legalAidRequest->ticketLabel,
                'statement_descriptor_suffix' => 'MAROCLOI',
                'metadata' => [
                    'legal_aid_request_id' => (string) $legalAidRequest->id,
                    'ticket_number' => $legalAidRequest->ticket_number,
                ],
            ], [
                'idempotency_key' => 'legal-aid-'.$legalAidRequest->id.'-'.Str::uuid(),
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->error(502, 'We could not initialise your payment right now. Please try again in a moment.');
        } catch (Throwable $e) {
            report($e);

            return $this->error(500, 'Something went wrong while preparing your payment.');
        }

        PaymentTransaction::create([
            'legal_aid_request_id' => $legalAidRequest->id,
            'stripe_payment_intent_id' => $intent->id,
            'currency' => $currency,
            'country' => (string) config('cashier.country', 'MA'),
            'amount_cents' => $amountCents,
            'amount' => $amountMAD,
            'status' => PaymentTransaction::STATUS_REQUIRES_PAYMENT_METHOD,
        ]);

        return response()->json([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'amount_cents' => $amountCents,
            'amount' => $amountMAD,
            'currency' => $currency,
            'country' => (string) config('cashier.country', 'MA'),
        ]);
    }

    /**
     * Verify a PaymentIntent that the client says succeeded.
     *
     * This endpoint is the server-side source of truth: we re-fetch the
     * intent from Stripe and only mark the request as paid when the status,
     * amount and metadata all match. A malicious client can never mark a
     * request as paid without Stripe actually holding the money.
     */
    public function verify(Request $request, string $ticket): JsonResponse
    {
        $legalAidRequest = LegalAidRequest::where('ticket_number', $ticket)->firstOrFail();

        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'regex:/^pi_[A-Za-z0-9]+$/'],
        ]);

        try {
            $intent = $this->stripe()->paymentIntents->retrieve($validated['payment_intent_id']);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->error(502, 'We could not verify your payment right now. Please try again.');
        } catch (Throwable $e) {
            report($e);

            return $this->error(500, 'Something went wrong while verifying your payment.');
        }

        if (! $this->intentMatchesRequest($intent, $legalAidRequest)) {
            return $this->error(422, 'The payment does not match this request.');
        }

        if ($intent->status !== PaymentIntent::STATUS_SUCCEEDED) {
            return $this->error(409, 'The payment has not been completed yet.');
        }

        $transaction = $this->syncTransactionFromIntent($intent);

        if (! $transaction) {
            return $this->error(422, 'The payment does not match this request.');
        }

        $this->markRequestPaid($legalAidRequest);

        return response()->json([
            'success' => true,
            'transaction_id' => $transaction->id,
            'payment_intent_id' => $intent->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
        ]);
    }

    /**
     * Stripe webhook endpoint (CSRF-exempt, signature verified).
     *
     * Guarantees the request is marked as paid even if the customer closes
     * the browser before the client-side verify call completes.
     */
    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('cashier.webhook.secret');

        if (! $secret) {
            report('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not configured.');

            return response()->json(['error' => 'Webhook signature verification is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret
            );
        } catch (SignatureVerificationException $e) {
            report($e);

            return response()->json(['error' => 'Invalid webhook signature.'], 400);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Could not process the webhook.'], 400);
        }

        try {
            match ($event->type) {
                'payment_intent.succeeded' => $this->handleIntentSucceeded($event->data->object),
                'payment_intent.payment_failed' => $this->handleIntentFailed($event->data->object),
                'payment_intent.canceled' => $this->handleIntentCanceled($event->data->object),
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                'checkout.session.expired' => $this->handleCheckoutSessionExpired($event->data->object),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Webhook handler failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function handleIntentSucceeded(PaymentIntent $intent): void
    {
        $transaction = $this->syncTransactionFromIntent($intent);

        if (! $transaction || $transaction->legalAidRequest->isPaid()) {
            return;
        }

        $this->markRequestPaid($transaction->legalAidRequest);
    }

    private function handleIntentFailed(PaymentIntent $intent): void
    {
        $transaction = $this->findTransactionForIntent($intent);

        if (! $transaction) {
            return;
        }

        $transaction->update([
            'status' => PaymentTransaction::STATUS_FAILED,
            'failure_code' => $intent->last_payment_error?->code,
            'failure_message' => $intent->last_payment_error?->message,
        ]);
    }

    private function handleIntentCanceled(PaymentIntent $intent): void
    {
        $transaction = $this->findTransactionForIntent($intent);

        if (! $transaction) {
            return;
        }

        $transaction->update([
            'status' => PaymentTransaction::STATUS_CANCELED,
        ]);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $sessionId = $session->id ?? null;
        $paymentIntentId = $session->payment_intent ?? null;
        $metadata = $session->metadata ?? [];
        // Detect shop order vs legal aid via metadata
        $orderIdRaw = $metadata['order_id'] ?? $metadata->order_id ?? null;
        if (is_object($metadata)) {
            $orderIdRaw = $metadata->order_id ?? $metadata['order_id'] ?? $orderIdRaw;
        }
        $orderId = $orderIdRaw ? (int) $orderIdRaw : null;

        // Shop order handling - CIN is ticket_number
        if ($orderId) {
            $this->handleShopCheckoutCompleted($session, $orderId);
            return;
        }

        $ticket = $metadata['ticket_number'] ?? $metadata->ticket_number ?? null;
        if (is_object($metadata)) {
            $ticket = $metadata->ticket_number ?? $metadata['ticket_number'] ?? $ticket;
        }
        $requestIdRaw = $metadata['legal_aid_request_id'] ?? $metadata->legal_aid_request_id ?? 0;
        if (is_object($metadata)) {
            $requestIdRaw = $metadata->legal_aid_request_id ?? $metadata['legal_aid_request_id'] ?? $requestIdRaw;
        }
        $requestId = (int) $requestIdRaw;

        // Prefer to mark via PaymentIntent if available
        if ($paymentIntentId) {
            try {
                $intent = $this->stripe()->paymentIntents->retrieve($paymentIntentId);
                $transaction = $this->syncTransactionFromIntent($intent);
                if ($transaction && ! $transaction->legalAidRequest->isPaid()) {
                    $this->markRequestPaid($transaction->legalAidRequest);
                }
                // Update checkout transaction as succeeded
                if ($sessionId) {
                    PaymentTransaction::where('stripe_checkout_session_id', $sessionId)->update([
                        'stripe_payment_intent_id' => $paymentIntentId,
                        'status' => $intent->status,
                    ]);
                }
                return;
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Fallback: mark via session metadata directly
        $legalAidRequest = $requestId ? LegalAidRequest::find($requestId) : ($ticket ? LegalAidRequest::where('ticket_number', $ticket)->first() : null);
        if ($legalAidRequest && ! $legalAidRequest->isPaid() && ($session->payment_status ?? null) === 'paid') {
            PaymentTransaction::where('stripe_checkout_session_id', $sessionId)->update([
                'status' => PaymentTransaction::STATUS_SUCCEEDED,
            ]);
            $this->markRequestPaid($legalAidRequest);
        }
    }

    private function handleShopCheckoutCompleted(object $session, int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            report('Shop webhook: order not found '.$orderId);
            return;
        }
        // Idempotent: already paid -> ensure advisor case exists, don't duplicate email
        if ($order->isPaid()) {
            try {
                OrderCaseService::createCaseFromOrder($order);
            } catch (\Throwable $e) {
                report($e);
            }
            return;
        }

        // Only mark paid if Stripe says paid
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        // Extract CIN from Stripe custom_fields (collected inside Checkout)
        $extractedCin = null;
        if (isset($session->custom_fields) && is_array($session->custom_fields)) {
            foreach ($session->custom_fields as $field) {
                $key = is_object($field) ? ($field->key ?? null) : ($field['key'] ?? null);
                if ($key === 'cin') {
                    $val = null;
                    if (is_object($field) && isset($field->text)) {
                        $val = is_object($field->text) ? ($field->text->value ?? null) : ($field->text['value'] ?? null);
                    } elseif (is_array($field) && isset($field['text'])) {
                        $val = $field['text']['value'] ?? null;
                    }
                    $extractedCin = $val ? strtoupper(trim((string) $val)) : null;
                }
            }
        }
        if (!$extractedCin) {
            $meta = $session->metadata ?? [];
            $extractedCin = is_object($meta) ? ($meta->cin ?? $meta->ticket_number ?? null) : ($meta['cin'] ?? $meta['ticket_number'] ?? null);
            if ($extractedCin) $extractedCin = strtoupper(trim((string) $extractedCin));
        }
        $extractedEmail = null;
        $customerDetails = $session->customer_details ?? null;
        if ($customerDetails) {
            $extractedEmail = is_object($customerDetails) ? ($customerDetails->email ?? null) : ($customerDetails['email'] ?? null);
        }
        if (!$extractedEmail) {
            $extractedEmail = $session->customer_email ?? null;
            if (!$extractedEmail) {
                $meta = $session->metadata ?? [];
                $extractedEmail = is_object($meta) ? ($meta->email ?? null) : ($meta['email'] ?? null);
            }
        }
        if ($extractedEmail) $extractedEmail = strtolower(trim((string) $extractedEmail));

        // Validate CIN format if extracted
        $validCin = $extractedCin && preg_match('/^[A-Z]{1,2}[0-9]{6}$/', $extractedCin) ? $extractedCin : null;
        $validEmail = $extractedEmail && filter_var($extractedEmail, FILTER_VALIDATE_EMAIL) ? $extractedEmail : null;

        $paymentIntentId = $session->payment_intent ?? null;
        $sessionId = $session->id ?? null;

        $updateData = [
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
            'stripe_payment_intent_id' => $paymentIntentId ?: $order->stripe_payment_intent_id,
            'stripe_checkout_session_id' => $sessionId ?: $order->stripe_checkout_session_id,
            'payload' => array_merge($order->payload ?? [], ['webhook_session' => (array) $session]),
        ];
        if ($validCin) {
            $updateData['cin'] = $validCin;
            $updateData['ticket_number'] = $validCin;
        }
        if ($validEmail) {
            $updateData['email'] = $validEmail;
        }

        $order->update($updateData);
        $order->refresh();
        $order->load('items.service');

        // Make it visible to advisors: create LegalAidRequest case so advisor can contact customer
        try {
            OrderCaseService::createCaseFromOrder($order);
        } catch (\Throwable $e) {
            report($e);
        }

        // Send confirmation email once (idempotent via isPaid check above)
        $freshEmail = $order->fresh()->email;
        Mail::to($freshEmail)->locale($order->locale ?: app()->getLocale())->queue(new ShopOrderConfirmationMail($order->fresh()->load('items.service')));
    }

    private function handleCheckoutSessionExpired(object $session): void
    {
        $sessionId = $session->id ?? null;
        if (! $sessionId) {
            return;
        }
        $metadata = $session->metadata ?? [];
        $orderIdRaw = $metadata['order_id'] ?? $metadata->order_id ?? null;
        if (is_object($metadata)) {
            $orderIdRaw = $metadata->order_id ?? $metadata['order_id'] ?? $orderIdRaw;
        }
        if ($orderIdRaw) {
            $order = Order::find((int) $orderIdRaw);
            if ($order && $order->status === Order::STATUS_PENDING) {
                $order->update(['status' => Order::STATUS_EXPIRED]);
            }
            return;
        }
        $transaction = PaymentTransaction::where('stripe_checkout_session_id', $sessionId)->first();
        if ($transaction) {
            $transaction->update(['status' => PaymentTransaction::STATUS_CANCELED]);
        }
        // Also handle shop order by session id lookup
        $shopOrder = Order::where('stripe_checkout_session_id', $sessionId)->first();
        if ($shopOrder && $shopOrder->status === Order::STATUS_PENDING) {
            $shopOrder->update(['status' => Order::STATUS_EXPIRED]);
        }
    }

    private function syncTransactionFromIntent(PaymentIntent $intent): ?PaymentTransaction
    {
        $transaction = PaymentTransaction::where('stripe_payment_intent_id', $intent->id)->first();

        if ($transaction) {
            $transaction->update([
                'status' => $intent->status,
                'stripe_payment_method_id' => $intent->payment_method,
                'payment_method_type' => $this->resolvePaymentMethodType($intent),
                'payload' => json_decode((string) $intent->toJSON(), true),
            ]);

            return $transaction;
        }

        $requestId = (int) ($intent->metadata['legal_aid_request_id'] ?? 0);

        if ($requestId < 1 || ! LegalAidRequest::whereKey($requestId)->exists()) {
            return null;
        }

        return PaymentTransaction::create([
            'legal_aid_request_id' => $requestId,
            'stripe_payment_intent_id' => $intent->id,
            'currency' => $intent->currency,
            'country' => (string) config('cashier.country', 'MA'),
            'amount_cents' => $intent->amount,
            'amount' => $intent->amount / 100,
            'status' => $intent->status,
            'stripe_payment_method_id' => $intent->payment_method,
            'payment_method_type' => $this->resolvePaymentMethodType($intent),
            'payload' => json_decode((string) $intent->toJSON(), true),
        ]);
    }

    private function findTransactionForIntent(PaymentIntent $intent): ?PaymentTransaction
    {
        return PaymentTransaction::where('stripe_payment_intent_id', $intent->id)->first();
    }

    private function resolvePaymentMethodType(PaymentIntent $intent): string
    {
        if (! $intent->payment_method) {
            return (string) ($intent->payment_method_types[0] ?? 'card');
        }

        try {
            $method = $this->stripe()->paymentMethods->retrieve($intent->payment_method);

            return (string) ($method->card?->wallet?->type ?? $method->type ?? 'card');
        } catch (Throwable) {
            return (string) ($intent->payment_method_types[0] ?? 'card');
        }
    }

    private function intentMatchesRequest(PaymentIntent $intent, LegalAidRequest $legalAidRequest): bool
    {
        return (int) $intent->amount === $this->toCents($legalAidRequest->onlineTotal)
            && strtolower((string) $intent->currency) === strtolower((string) config('cashier.currency', 'mad'))
            && (int) ($intent->metadata['legal_aid_request_id'] ?? 0) === $legalAidRequest->id
            && ($intent->metadata['ticket_number'] ?? null) === $legalAidRequest->ticket_number;
    }

    private function markRequestPaid(LegalAidRequest $legalAidRequest): void
    {
        if ($legalAidRequest->isPaid()) {
            return;
        }

        $legalAidRequest->update([
            'status' => LegalAidRequest::STATUS_PAID,
            'paid_at' => now(),
            'receipt_path' => null,
        ]);

        // Option A: notify customer payment received + advisors
        Mail::to($legalAidRequest->email)
            ->locale($legalAidRequest->locale ?: app()->getLocale())
            ->queue(new LegalAidPaymentReceivedMail($legalAidRequest));

        AdvisorNotifier::caseReady($legalAidRequest);
    }

    private function cancelStaleIntents(LegalAidRequest $legalAidRequest): void
    {
        PaymentTransaction::where('legal_aid_request_id', $legalAidRequest->id)
            ->whereIn('status', [
                PaymentTransaction::STATUS_REQUIRES_PAYMENT_METHOD,
                PaymentTransaction::STATUS_REQUIRES_CONFIRMATION,
                PaymentTransaction::STATUS_REQUIRES_ACTION,
            ])
            ->pluck('stripe_payment_intent_id')
            ->each(function (string $paymentIntentId): void {
                try {
                    $this->stripe()->paymentIntents->cancel($paymentIntentId);
                } catch (Throwable) {
                    // Already canceled or cannot be canceled; ignore.
                }
            });
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function stripe(): StripeClient
    {
        return app(StripeClient::class);
    }

    private function error(int $status, string $message): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
