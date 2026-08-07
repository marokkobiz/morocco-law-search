<?php

namespace Tests\Feature;

use App\Models\LegalAidRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_dashboard_stats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'user']);
        LegalAidRequest::create([
            'ticket_number' => '11111',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Pending Payments')
            ->assertSee('Registered Users')
            ->assertSee('#11111');
    }

    public function test_dashboard_and_users_pages_render_different_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->getContent();
        $users = $this->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString('Recent Legal Aid Requests', $dashboard);
        $this->assertStringNotContainsString('Recent Legal Aid Requests', $users);
        $this->assertStringContainsString('User Management', $users);
    }

    public function test_admin_can_confirm_payment_from_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '22222',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.confirm', $legalAidRequest->id))
            ->assertRedirect();

        $this->assertEquals(
            LegalAidRequest::STATUS_CONFIRMED,
            $legalAidRequest->fresh()->status,
        );
    }

    public function test_admin_can_toggle_user_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-admin', $user->id))
            ->assertRedirect();

        $this->assertEquals('admin', $user->fresh()->role);

        $this->post(route('admin.users.toggle-admin', $user->id));
        $this->assertEquals('user', $user->fresh()->role);
    }

    public function test_admin_cannot_toggle_own_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-admin', $admin->id))
            ->assertRedirect();

        $this->assertEquals('admin', $admin->fresh()->role);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin->id))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
