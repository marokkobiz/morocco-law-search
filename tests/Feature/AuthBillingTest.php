<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_requires_login(): void
    {
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_auth_pages_are_reachable_for_guests_and_authenticated_users(): void
    {
        $this->get('/login')->assertOk()->assertSee('Login');
        $this->get('/register')->assertOk()->assertSee('Create account');

        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertOk()->assertSee('Login');
        $this->actingAs($user)->get('/register')->assertOk()->assertSee('Create account');
    }

    public function test_login_authenticates_and_opens_the_search_workspace(): void
    {
        config(['billing.require_payment' => false]);

        $user = User::factory()->create([
            'email' => 'lawyer@example.test',
            'password' => 'correct-password',
        ]);

        $this->post('/login', [
            'lang' => 'en',
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect('/app?lang=en');

        $this->assertAuthenticatedAs($user);
        $this->get('/app?lang=en')
            ->assertOk()
            ->assertSee('id="root"', false)
            ->assertSee('search-workspace-');
    }

    public function test_register_creates_lawyer_profile_and_logs_in(): void
    {
        config(['billing.require_payment' => false]);

        $this->post('/register', [
            'lang' => 'en',
            'name' => 'Karim Lawyer',
            'company' => 'Karim Law Firm',
            'phone' => '+212600000000',
            'email' => 'karim@example.test',
            'bar' => 'Kenitra Bar / Kenitra Court',
            'password' => '123456789',
        ])->assertRedirect('/app?lang=en');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'karim@example.test',
            'company' => 'Karim Law Firm',
            'phone' => '+212600000000',
            'bar' => 'Kenitra Bar / Kenitra Court',
            'access_status' => 'active',
        ]);

        $this->get('/app?lang=en')->assertOk()->assertSee('id="root"', false);
    }

    public function test_payment_required_users_are_sent_to_billing(): void
    {
        config(['billing.require_payment' => true]);

        $user = User::factory()->create([
            'access_status' => 'pending_payment',
            'billing_active_at' => null,
        ]);

        $this->actingAs($user)->get('/app')->assertRedirect('/billing?lang=en');
    }

    public function test_stripe_webhook_activates_user_with_valid_signature(): void
    {
        config(['billing.stripe.webhook_secret' => 'whsec_test']);

        $user = User::factory()->create(['access_status' => 'pending_payment']);
        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'customer' => 'cus_test',
                    'subscription' => 'sub_test',
                    'client_reference_id' => (string) $user->id,
                    'metadata' => ['user_id' => (string) $user->id],
                ],
            ],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        $this->postJson('/api/billing/stripe-webhook', json_decode($payload, true), [
            'Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ])->assertOk();

        $user->refresh();

        $this->assertSame('active', $user->access_status);
        $this->assertSame('cus_test', $user->stripe_customer_id);
        $this->assertSame('sub_test', $user->stripe_subscription_id);
    }
}
