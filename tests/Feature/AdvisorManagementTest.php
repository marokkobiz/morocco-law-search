<?php

namespace Tests\Feature;

use App\Mail\AdvisorCredentialsMail;
use App\Models\LegalAidRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdvisorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function advisor(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => 'advisor'], $overrides));
    }

    public function test_only_admins_can_access_advisor_management(): void
    {
        $advisor = $this->advisor();

        $this->actingAs($advisor)
            ->get(route('admin.advisors.index'))
            ->assertForbidden();
    }

    public function test_admin_can_list_advisors(): void
    {
        $admin = $this->admin();
        $this->advisor(['name' => 'Yassine El Amrani', 'email' => 'yassine@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.advisors.index'))
            ->assertOk()
            ->assertSee('Yassine El Amrani')
            ->assertSee('yassine@example.com');
    }

    public function test_admin_can_create_advisor_with_generated_password_emailed(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.advisors.store'), [
                'name' => 'Fatima Zahra',
                'email' => 'fatima@example.com',
                'phone' => '+212600123456',
            ])
            ->assertRedirect(route('admin.advisors.index'))
            ->assertSessionHas('success');

        $advisor = User::where('email', 'fatima@example.com')->firstOrFail();

        $this->assertSame('advisor', $advisor->role);
        $this->assertSame('active', $advisor->access_status);

        Mail::assertQueued(AdvisorCredentialsMail::class, function (AdvisorCredentialsMail $mail) use ($advisor) {
            return $mail->hasTo($advisor->email)
                && $mail->temporaryPassword !== null;
        });
    }

    public function test_advisor_credentials_password_is_stored_hashed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.advisors.store'), [
            'name' => 'Khadija Alaoui',
            'email' => 'khadija@example.com',
        ]);

        $advisor = User::where('email', 'khadija@example.com')->firstOrFail();

        $this->assertNotSame(14, strlen($advisor->password));
        $this->assertFalse(Hash::needsRehash($advisor->password));
    }

    public function test_advisor_can_log_in_with_the_emailed_credentials(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.advisors.store'), [
            'name' => 'Salma Bennani',
            'email' => 'salma@example.com',
        ]);

        Mail::assertQueued(AdvisorCredentialsMail::class);

        $mail = Mail::queued(AdvisorCredentialsMail::class)->first();
        $this->assertNotNull($mail);
        $this->assertNotEmpty($mail->temporaryPassword);

        $this->post('/logout');

        $this->post('/login', [
            'email' => 'salma@example.com',
            'password' => $mail->temporaryPassword,
        ])->assertRedirect(route('advisor.cases.index'));

        $this->assertAuthenticatedAs(User::where('email', 'salma@example.com')->first());
    }

    public function test_email_duplicate_is_rejected(): void
    {
        $admin = $this->admin();
        $this->advisor(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.advisors.store'), [
                'name' => 'Duplicate',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_admin_can_edit_advisor_details(): void
    {
        $admin = $this->admin();
        $advisor = $this->advisor(['name' => 'Old Name', 'email' => 'old@example.com']);

        $this->actingAs($admin)
            ->put(route('admin.advisors.update', $advisor), [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'phone' => '+212699999999',
            ])
            ->assertRedirect();

        $advisor->refresh();

        $this->assertSame('New Name', $advisor->name);
        $this->assertSame('new@example.com', $advisor->email);
        $this->assertSame('+212699999999', $advisor->phone);
    }

    public function test_suspend_blocks_advisor_from_portal(): void
    {
        $admin = $this->admin();
        $advisor = $this->advisor();

        $this->actingAs($admin)
            ->post(route('admin.advisors.suspend', $advisor))
            ->assertRedirect();

        $advisor->refresh();
        $this->assertSame('suspended', $advisor->access_status);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertForbidden();
    }

    public function test_unsuspend_restores_portal_access(): void
    {
        $admin = $this->admin();
        $advisor = $this->advisor(['access_status' => 'suspended']);

        $this->actingAs($admin)
            ->post(route('admin.advisors.unsuspend', $advisor))
            ->assertRedirect();

        $advisor->refresh();
        $this->assertSame('active', $advisor->access_status);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertOk();
    }

    public function test_reset_password_emails_new_credentials(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $advisor = $this->advisor();
        $oldPassword = $advisor->password;

        $this->actingAs($admin)
            ->post(route('admin.advisors.reset-password', $advisor))
            ->assertRedirect()
            ->assertSessionHas('success');

        $advisor->refresh();

        $this->assertNotSame($oldPassword, $advisor->password);

        Mail::assertQueued(AdvisorCredentialsMail::class, function (AdvisorCredentialsMail $mail) use ($advisor) {
            return $mail->hasTo($advisor->email);
        });
    }

    public function test_management_endpoints_404_for_non_advisor_users(): void
    {
        $admin = $this->admin();
        $regularUser = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->post(route('admin.advisors.suspend', $regularUser))
            ->assertNotFound();

        $this->actingAs($admin)
            ->delete(route('admin.advisors.destroy', $regularUser))
            ->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $regularUser->id]);
    }

    public function test_deleting_advisor_reassigns_their_open_cases(): void
    {
        $admin = $this->admin();
        $advisor = $this->advisor();

        $case = LegalAidRequest::create([
            'ticket_number' => '43001',
            'full_name' => 'Case Client',
            'email' => 'client@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Case',
            'status' => LegalAidRequest::STATUS_PAID,
            'paid_at' => now(),
            'base_price' => 500,
            'advisor_id' => $advisor->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.advisors.destroy', $advisor))
            ->assertRedirect(route('admin.advisors.index'));

        $this->assertDatabaseMissing('users', ['id' => $advisor->id]);
        $this->assertDatabaseHas('legal_aid_requests', [
            'id' => $case->id,
            'advisor_id' => null,
        ]);
    }
}