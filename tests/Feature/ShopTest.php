<?php

namespace Tests\Feature;

use App\Mail\ShopOrderConfirmationMail;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.currency' => 'mad', 'cashier.key' => 'pk_test_dummy', 'cashier.webhook.secret' => 'whsec_test']);
    }

    private function createService(string $name = 'Test Service', float $price = 500, bool $active = true): Service
    {
        $s = Service::create([
            'name_en' => $name,
            'name_fr' => $name,
            'name_ar' => $name,
            'price' => $price,
            'is_active' => $active,
        ]);
        $s->refresh();
        if ($price >= 0.5 && empty($s->stripe_price_id)) {
            $s->forceFill(['stripe_price_id' => 'price_'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT).substr(md5($price),0,8), 'stripe_product_id' => 'prod_'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT).substr(md5($name),0,8)])->save();
            $s->refresh();
        }
        return $s;
    }

    private function validCheckoutPayload(Service $service, array $overrides = []): array
    {
        $base = [
            'full_name' => 'John Doe',
            'email' => 'customer@example.com',
            'phone' => '+212600000001',
            'whatsapp' => '+212600000002',
            'case_description' => str_repeat('Legal case description with sufficient length. ', 5),
            'call_time' => '09:00-09:30',
            'items' => [['price_id' => $service->stripe_price_id, 'quantity' => 1]],
        ];
        return array_merge($base, $overrides);
    }

    public function test_shop_index_lists_products(): void
    {
        $s1 = $this->createService('Service A', 100);
        $s2 = $this->createService('Service B', 200, false);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('Service A')
            ->assertDontSee('Service B');

        // Legacy shop alias should redirect
        $this->get(route('shop.index'))->assertRedirect(route('legal-aid'));
    }

    public function test_shop_api_products(): void
    {
        $s = $this->createService('API Service', 123);
        $this->get(route('legal-aid.api.products'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'API Service', 'price' => 123]);
    }

    public function test_checkout_without_cin_creates_pending_order_for_stripe_collection(): void
    {
        $s = $this->createService('Prod', 500);
        $response = $this->postJson(route('legal-aid.checkout.create'), $this->validCheckoutPayload($s))
            ->assertOk()->assertJsonStructure(['url', 'id', 'order_id']);

        $order = Order::find($response->json('order_id'));
        $this->assertEquals('PENDING', $order->cin);
        $this->assertEquals('customer@example.com', $order->email);
        $this->assertEquals('+212600000001', $order->phone);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
    }

    public function test_checkout_validates_cin_format_when_provided(): void
    {
        $s = $this->createService('Prod', 500);
        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['cin' => '123456']))
            ->assertStatus(422)->assertJsonValidationErrors('cin');

        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['cin' => 'AB123456']))
            ->assertOk();
    }

    public function test_checkout_requires_customer_fields(): void
    {
        $s = $this->createService('Prod', 500);
        $this->postJson(route('legal-aid.checkout.create'), [
            'items' => [['price_id' => $s->stripe_price_id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['full_name','email','phone','case_description','call_time']);

        $this->postJson(route('legal-aid.checkout.create'), [
            'full_name' => 'John',
            'email' => 'a@b.com',
            'phone' => 'invalid',
            'case_description' => 'short',
            'call_time' => 'invalid',
            'items' => [['price_id' => $s->stripe_price_id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_checkout_rejects_invalid_price_id(): void
    {
        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($this->createService('Tmp', 100)), ['items' => [['price_id' => 'price_invalid999', 'quantity' => 1]]]))
            ->assertStatus(422)->assertJson(['message' => __('shop.invalid_product')]);
    }

    public function test_checkout_rejects_inactive_product(): void
    {
        $s = $this->createService('Inactive', 500, false);
        $s->forceFill(['is_active' => false])->save();
        $this->postJson(route('legal-aid.checkout.create'), $this->validCheckoutPayload($s))
            ->assertStatus(422);
    }

    public function test_checkout_validates_quantity(): void
    {
        $s = $this->createService('Prod', 500);
        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['items' => [['price_id' => $s->stripe_price_id, 'quantity' => 0]]]))
            ->assertStatus(422);
        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['items' => [['price_id' => $s->stripe_price_id, 'quantity' => 100]]]))
            ->assertStatus(422);
    }

    public function test_checkout_creates_order_with_cin_as_ticket(): void
    {
        Mail::fake();
        $s1 = $this->createService('A', 100);
        $s2 = $this->createService('B', 200);
        $response = $this->postJson(route('legal-aid.checkout.create'), [
            'full_name' => 'John Doe',
            'email' => 'customer@example.com',
            'phone' => '+212600000001',
            'whatsapp' => '+212600000002',
            'case_description' => str_repeat('Case details. ', 10),
            'call_time' => '10:00-10:30',
            'cin' => 'AB123456',
            'items' => [
                ['price_id' => $s1->stripe_price_id, 'quantity' => 2],
                ['price_id' => $s2->stripe_price_id, 'quantity' => 1],
            ],
        ])->assertOk()->assertJsonStructure(['url', 'id', 'order_id']);

        $order = Order::find($response->json('order_id'));
        $this->assertNotNull($order);
        $this->assertEquals('AB123456', $order->cin);
        $this->assertEquals('AB123456', $order->ticket_number);
        $this->assertEquals('customer@example.com', $order->email);
        $this->assertEquals('+212600000001', $order->phone);
        $this->assertEquals(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(40000, $order->total_cents);
        $this->assertCount(2, $order->items);
        $this->assertStringStartsWith('cs_test_', $order->stripe_checkout_session_id);
        $this->assertStringContainsString($order->stripe_checkout_session_id, $response->json('url'));
    }

    public function test_checkout_merges_duplicate_price_ids(): void
    {
        $s = $this->createService('Prod', 100);
        $response = $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), [
            'items' => [
                ['price_id' => $s->stripe_price_id, 'quantity' => 1],
                ['price_id' => $s->stripe_price_id, 'quantity' => 2],
            ],
            'cin' => 'A123456',
        ]))->assertOk();
        $order = Order::find($response->json('order_id'));
        $this->assertCount(1, $order->items);
        $this->assertEquals(3, $order->items->first()->quantity);
        $this->assertEquals(30000, $order->total_cents);
    }

    public function test_success_page_marks_order_paid_for_dummy_session(): void
    {
        Mail::fake();
        $s = $this->createService('Prod', 500);
        $resp = $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['cin' => 'AB123456']))
            ->assertOk();
        $order = Order::find($resp->json('order_id'));

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.success', ['order' => $order->id]).'?session_id='.$order->stripe_checkout_session_id)
            ->assertOk()
            ->assertSee('AB123456')
            ->assertSee('Payment successful');

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        Mail::assertQueued(ShopOrderConfirmationMail::class, function ($mail) use ($order) {
            return $mail->hasTo('customer@example.com') && $mail->order->ticket_number === 'AB123456';
        });
    }

    public function test_cancel_page_updates_status(): void
    {
        $s = $this->createService('Prod', 500);
        $resp = $this->postJson(route('legal-aid.checkout.create'), $this->validCheckoutPayload($s, ['cin' => 'AB123456']))
            ->assertOk();
        $order = Order::find($resp->json('order_id'));
        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.cancel', $order->id))
            ->assertOk()
            ->assertSee('Payment cancelled');
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_webhook_marks_order_paid_and_idempotent(): void
    {
        Mail::fake();
        $s = $this->createService('Prod', 500);
        $resp = $this->postJson(route('legal-aid.checkout.create'), $this->validCheckoutPayload($s, ['cin' => 'AB123456']))
            ->assertOk();
        $order = Order::find($resp->json('order_id'));
        $sessionId = $order->stripe_checkout_session_id;

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'payment_intent' => 'pi_test_123',
                'payment_status' => 'paid',
                'status' => 'complete',
                'customer_details' => ['email' => 'customer@example.com'],
                'custom_fields' => [['key' => 'cin', 'text' => ['value' => 'AB123456']]],
                'metadata' => ['order_id' => (string) $order->id],
            ]],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);
        $this->assertEquals('AB123456', $order->fresh()->cin);
        Mail::assertQueued(ShopOrderConfirmationMail::class, 1);

        $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertEquals(Order::STATUS_PAID, $order->status);
        Mail::assertQueued(ShopOrderConfirmationMail::class, 1);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=0,v1=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);
    }

    public function test_webhook_expired_marks_expired(): void
    {
        $s = $this->createService('Prod', 500);
        $resp = $this->postJson(route('legal-aid.checkout.create'), $this->validCheckoutPayload($s))
            ->assertOk();
        $order = Order::find($resp->json('order_id'));
        $sessionId = $order->stripe_checkout_session_id;

        $payload = json_encode([
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => ['object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'payment_status' => 'unpaid',
                'status' => 'expired',
                'metadata' => ['order_id' => (string) $order->id],
            ]],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $order->refresh();
        $this->assertEquals(Order::STATUS_EXPIRED, $order->status);
    }

    public function test_confirmation_email_contains_cin_and_total(): void
    {
        $s = $this->createService('Prod', 100);
        $order = Order::create([
            'cin' => 'AB123456',
            'ticket_number' => 'AB123456',
            'email' => 'customer@example.com',
            'full_name' => 'John',
            'phone' => '+212600000001',
            'case_description' => str_repeat('Case ', 25),
            'call_time' => '09:00-09:30',
            'status' => Order::STATUS_PAID,
            'currency' => 'mad',
            'total_cents' => 20000,
            'total_amount' => 200,
            'locale' => 'en',
            'paid_at' => now(),
        ]);
        $order->items()->create([
            'service_id' => $s->id,
            'stripe_price_id' => $s->stripe_price_id,
            'quantity' => 2,
            'unit_amount_cents' => 10000,
            'line_total_cents' => 20000,
        ]);
        $order->load('items.service');
        $mail = new ShopOrderConfirmationMail($order);
        $html = $mail->render();
        $this->assertStringContainsString('AB123456', $html);
        $this->assertStringContainsString('200 MAD', $html);
        $this->assertStringContainsString('Qty 2', $html);
    }

    public function test_empty_cart_shows_message(): void
    {
        $this->postJson(route('legal-aid.checkout.create'), [
            'full_name' => 'John Doe',
            'email' => 'customer@example.com',
            'phone' => '+212600000001',
            'case_description' => str_repeat('Case ', 25),
            'call_time' => '09:00-09:30',
            'items' => [],
        ])->assertStatus(422);
    }

    public function test_price_change_reflected_in_checkout(): void
    {
        $s = $this->createService('Prod', 100);
        $oldPriceId = $s->stripe_price_id;
        $s->update(['price' => 200]);
        $s->forceFill(['stripe_price_id' => 'price_'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT).substr(md5('200'),0,8)])->save();
        $s->refresh();

        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['items' => [['price_id' => $oldPriceId, 'quantity' => 1]]]))
            ->assertStatus(422);

        $this->postJson(route('legal-aid.checkout.create'), array_merge($this->validCheckoutPayload($s), ['items' => [['price_id' => $s->stripe_price_id, 'quantity' => 1]]]))
            ->assertOk()
            ->assertJsonPath('order_id', fn($id) => Order::find($id)->total_cents === 20000);
    }

    public function test_legacy_shop_alias_still_works(): void
    {
        $s = $this->createService('Legacy', 100);
        $this->postJson(route('shop.checkout.create'), $this->validCheckoutPayload($s))
            ->assertOk();
        $this->get(route('shop.index'))->assertRedirect(route('legal-aid'));
    }
}
