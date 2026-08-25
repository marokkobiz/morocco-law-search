<?php

namespace App\Http\Controllers;

use App\Models\LegalAidRequest;
use App\Models\PaymentTransaction;
use App\Support\AdvisorNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Dynamically create a Stripe PaymentIntent and return the client secret
     * to the frontend so Stripe.js can render the Google Pay / Payment Request
     * button and confirm the payment.
     *
     * The amount is always derived server-side from the request's online total,
     * never trusted from the client.
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
                'payment_method_types' => ['card'],
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
