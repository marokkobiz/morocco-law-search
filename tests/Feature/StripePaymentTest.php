<?php

namespace Tests\Feature;

use App\Models\LegalAidRequest;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeClient $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeStripeClient;
        $this->app->instance(StripeClient::class, $this->fake);

        config(['legal_aid.online_discount_percent' => 10]);
    }

    private function payableRequest(string $ticket = '12345', float $basePrice = 500): LegalAidRequest
    {
        return LegalAidRequest::create([
            'ticket_number' => $ticket,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'base_price' => $basePrice,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);
    }

    public function test_create_intent_returns_client_secret_and_persists_transaction(): void
    {
        $legalAidRequest = $this->payableRequest();

        $response = $this->post(route('legal-aid.payment.intent', $legalAidRequest->ticket_number));

        $response->assertOk()
            ->assertJson([
                'amount_cents' => 45000,
                'amount' => 450,
                'currency' => 'mad',
                'country' => 'MA',
            ])
            ->assertJsonStructure(['client_secret', 'payment_intent_id']);

        $this->assertDatabaseHas('payment_transactions', [
            'legal_aid_request_id' => $legalAidRequest->id,
            'stripe_payment_intent_id' => $response->json('payment_intent_id'),
            'amount_cents' => 45000,
            'currency' => 'mad',
            'country' => 'MA',
            'status' => PaymentTransaction::STATUS_REQUIRES_PAYMENT_METHOD,
        ]);
    }

    public function test_create_intent_rejects_free_requests(): void
    {
        $legalAidRequest = $this->payableRequest('12346', 0);

        $this->post(route('legal-aid.payment.intent', $legalAidRequest->ticket_number))
            ->assertStatus(422);

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_create_intent_rejects_already_paid_requests(): void
    {
        $legalAidRequest = $this->payableRequest('12347');
        $legalAidRequest->update(['status' => LegalAidRequest::STATUS_PAID]);

        $this->post(route('legal-aid.payment.intent', $legalAidRequest->ticket_number))
            ->assertStatus(409);

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_create_intent_cancels_previous_open_intents(): void
    {
        $legalAidRequest = $this->payableRequest();

        $this->fake->paymentIntents->intents['pi_stale_old'] = new PaymentIntent('pi_stale_old');

        PaymentTransaction::create([
            'legal_aid_request_id' => $legalAidRequest->id,
            'stripe_payment_intent_id' => 'pi_stale_old',
            'amount_cents' => 45000,
            'amount' => 450,
            'status' => PaymentTransaction::STATUS_REQUIRES_PAYMENT_METHOD,
        ]);

        $this->post(route('legal-aid.payment.intent', $legalAidRequest->ticket_number))
            ->assertOk();

        $this->assertContains('pi_stale_old', $this->fake->paymentIntents->cancelled);
    }

    public function test_verify_marks_request_paid_when_intent_succeeded(): void
    {
        $legalAidRequest = $this->payableRequest();

        $intent = $this->fake->paymentIntents->create([
            'amount' => 45000,
            'currency' => 'mad',
            'metadata' => [
                'legal_aid_request_id' => (string) $legalAidRequest->id,
                'ticket_number' => $legalAidRequest->ticket_number,
            ],
        ]);
        $intent->status = PaymentIntent::STATUS_SUCCEEDED;

        $response = $this->postJson(route('legal-aid.payment.verify', $legalAidRequest->ticket_number), [
            'payment_intent_id' => $intent->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(LegalAidRequest::STATUS_PAID, $legalAidRequest->fresh()->status);
        $this->assertNotNull($legalAidRequest->fresh()->paid_at);

        $this->assertDatabaseHas('payment_transactions', [
            'stripe_payment_intent_id' => $intent->id,
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
        ]);
    }

    public function test_verify_rejects_intent_that_does_not_match_request(): void
    {
        $legalAidRequest = $this->payableRequest();

        $intent = $this->fake->paymentIntents->create([
            'amount' => 10000,
            'currency' => 'mad',
            'metadata' => [
                'legal_aid_request_id' => (string) $legalAidRequest->id,
                'ticket_number' => $legalAidRequest->ticket_number,
            ],
        ]);
        $intent->status = PaymentIntent::STATUS_SUCCEEDED;

        $this->postJson(route('legal-aid.payment.verify', $legalAidRequest->ticket_number), [
            'payment_intent_id' => $intent->id,
        ])->assertStatus(422);

        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $legalAidRequest->fresh()->status);
    }

    public function test_verify_rejects_intent_that_has_not_succeeded(): void
    {
        $legalAidRequest = $this->payableRequest();

        $intent = $this->fake->paymentIntents->create([
            'amount' => 45000,
            'currency' => 'mad',
            'metadata' => [
                'legal_aid_request_id' => (string) $legalAidRequest->id,
                'ticket_number' => $legalAidRequest->ticket_number,
            ],
        ]);
        $intent->status = PaymentIntent::STATUS_REQUIRES_ACTION;

        $this->postJson(route('legal-aid.payment.verify', $legalAidRequest->ticket_number), [
            'payment_intent_id' => $intent->id,
        ])->assertStatus(409);

        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $legalAidRequest->fresh()->status);
    }

    public function test_verify_rejects_malformed_payment_intent_id(): void
    {
        $legalAidRequest = $this->payableRequest();

        $this->postJson(route('legal-aid.payment.verify', $legalAidRequest->ticket_number), [
            'payment_intent_id' => 'not-a-real-intent',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_intent_id');
    }

    public function test_verify_returns_not_found_for_unknown_ticket(): void
    {
        $this->postJson(route('legal-aid.payment.verify', '99999'), [
            'payment_intent_id' => 'pi_fake_1234567890',
        ])->assertNotFound();
    }

    public function test_payment_page_renders_stripe_checkout_when_key_configured(): void
    {
        config(['cashier.key' => 'pk_test_dummy']);

        $legalAidRequest = $this->payableRequest();

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('id="google-pay-button"', false)
            ->assertSee('MarocLoiStripe', false)
            ->assertSee('stripe-payment-request', false);
    }

    public function test_payment_page_hides_stripe_checkout_without_key(): void
    {
        config(['cashier.key' => null]);

        $legalAidRequest = $this->payableRequest();

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertDontSee('MarocLoiStripe', false)
            ->assertDontSee('id="google-pay-button"', false)
            ->assertSee('Online payment is temporarily unavailable');
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't=0,v1=not-a-signature', 'CONTENT_TYPE' => 'application/json'],
            '{"type":"payment_intent.succeeded","data":{"object":{}}}'
        )->assertStatus(400);
    }

    public function test_webhook_marks_request_paid_on_intent_succeeded(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $legalAidRequest = $this->payableRequest('88888');

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_123',
                    'object' => 'payment_intent',
                    'amount' => 45000,
                    'currency' => 'mad',
                    'status' => PaymentIntent::STATUS_SUCCEEDED,
                    'payment_method' => null,
                    'payment_method_types' => ['card'],
                    'last_payment_error' => null,
                    'metadata' => [
                        'legal_aid_request_id' => (string) $legalAidRequest->id,
                        'ticket_number' => $legalAidRequest->ticket_number,
                    ],
                ],
            ],
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        )->assertOk();

        $this->assertEquals(LegalAidRequest::STATUS_PAID, $legalAidRequest->fresh()->status);
        $this->assertDatabaseHas('payment_transactions', [
            'stripe_payment_intent_id' => 'pi_webhook_123',
            'status' => PaymentTransaction::STATUS_SUCCEEDED,
        ]);
    }
}

final class FakeStripeClient extends StripeClient
{
    public FakePaymentIntents $paymentIntents;

    public FakePaymentMethods $paymentMethods;

    public FakeCheckoutSessions $checkout;

    public function __construct()
    {
        $this->paymentIntents = new FakePaymentIntents;
        $this->paymentMethods = new FakePaymentMethods;
        $this->checkout = new FakeCheckoutSessions;
        // StripeClient exposes $checkout->sessions; emulate that structure
        $this->checkout->sessions = new FakeCheckoutSessionResource;
    }
}

final class FakeCheckoutSessions
{
    public FakeCheckoutSessionResource $sessions;
}

final class FakeCheckoutSessionResource
{
    /** @var array<string, object> */
    public array $sessions = [];

    public function create(array $params = []): object
    {
        $id = 'cs_test_'.Str::random(48);
        $session = (object) [
            'id' => $id,
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/'.$id,
            'payment_intent' => null,
            'payment_status' => 'unpaid',
            'status' => 'open',
            'metadata' => (object) ($params['metadata'] ?? []),
            'payment_method_types' => $params['payment_method_types'] ?? ['card'],
            'currency' => $params['line_items'][0]['price_data']['currency'] ?? 'mad',
            'amount_total' => $params['line_items'][0]['price_data']['unit_amount'] ?? 0,
            'success_url' => $params['success_url'] ?? null,
            'cancel_url' => $params['cancel_url'] ?? null,
        ];
        // Allow toJSON for controller's json_decode((string)$session->toJSON())
        $session->toJSON = fn() => json_encode($session);
        // Provide toJSON method via __call
        $this->sessions[$id] = $session;
        return new class($session) {
            public function __construct(private object $s) {}
            public function __get(string $name) { return $this->s->$name ?? null; }
            public function __isset(string $name): bool { return isset($this->s->$name); }
            public function toJSON(): string { return json_encode($this->s); }
        };
    }

    public function retrieve(string $id): object
    {
        if (!isset($this->sessions[$id])) {
            throw new \Stripe\Exception\InvalidRequestException("No such checkout.session: {$id}");
        }
        return $this->sessions[$id];
    }
}

final class FakePaymentIntents
{
    /** @var array<string, PaymentIntent> */
    public array $intents = [];

    /** @var array<int, string> */
    public array $cancelled = [];

    public function create(array $params = []): PaymentIntent
    {
        $id = 'pi_'.Str::random(24);
        $intent = new PaymentIntent($id);
        $intent->client_secret = $id.'_secret_'.Str::random(16);
        $intent->amount = $params['amount'];
        $intent->currency = $params['currency'];
        $intent->status = PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD;
        $intent->payment_method = null;
        $intent->payment_method_types = ['card'];
        $intent->metadata = $params['metadata'] ?? [];

        $this->intents[$id] = $intent;

        return $intent;
    }

    public function retrieve(string $id): PaymentIntent
    {
        if (! isset($this->intents[$id])) {
            throw new InvalidRequestException("No such payment_intent: {$id}");
        }

        return $this->intents[$id];
    }

    public function cancel(string $id): PaymentIntent
    {
        $this->cancelled[] = $id;

        $intent = $this->intents[$id] ?? new PaymentIntent($id);
        $intent->status = PaymentIntent::STATUS_CANCELED;

        return $intent;
    }
}

final class FakePaymentMethods
{
    public function retrieve(string $id): object
    {
        return (object) [
            'type' => 'card',
            'card' => (object) ['wallet' => (object) ['type' => 'google_pay']],
        ];
    }
}
