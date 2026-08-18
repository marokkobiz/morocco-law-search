<?php

namespace Tests\Feature;

use App\Mail\LegalAidAdvisorNotificationMail;
use App\Models\LegalAidRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdvisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function paidCase(array $overrides = []): LegalAidRequest
    {
        return LegalAidRequest::create(array_merge([
            'ticket_number' => '40001',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PAID,
            'paid_at' => now(),
            'base_price' => 500,
        ], $overrides));
    }

    public function test_advisor_can_see_dashboard_with_paid_cases(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $this->paidCase(['ticket_number' => '41001']);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertOk()
            ->assertSee('#41001')
            ->assertSee('Jane Doe');
    }

    public function test_unpaid_cases_are_not_visible_to_advisors(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        $pending = LegalAidRequest::create([
            'ticket_number' => '42001',
            'full_name' => 'Not Ready',
            'email' => 'pending@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Not paid yet',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertOk()
            ->assertDontSee('#42001');

        $this->actingAs($advisor)
            ->get(route('advisor.cases.show', $pending->id))
            ->assertNotFound();
    }

    public function test_free_consultations_are_visible_in_order(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        LegalAidRequest::create([
            'ticket_number' => '43001',
            'full_name' => 'First Free',
            'email' => 'first@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Free case',
            'status' => LegalAidRequest::STATUS_PENDING,
            'base_price' => 0,
            'created_at' => now()->subDay(),
        ]);

        LegalAidRequest::create([
            'ticket_number' => '43002',
            'full_name' => 'Second Free',
            'email' => 'second@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Free case',
            'status' => LegalAidRequest::STATUS_PENDING,
            'base_price' => 0,
        ]);

        $content = $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(strpos($content, '#43002'), strpos($content, '#43001'));
    }

    public function test_regular_users_cannot_access_advisor_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('advisor.cases.index'))
            ->assertForbidden();
    }

    public function test_admins_can_access_advisor_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->paidCase();

        $this->actingAs($admin)
            ->get(route('advisor.cases.index'))
            ->assertOk();
    }

    public function test_advisors_can_filter_cases_by_status(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        $this->paidCase(['ticket_number' => '44001', 'case_status' => LegalAidRequest::CASE_OPEN]);
        $this->paidCase(['ticket_number' => '44002', 'case_status' => LegalAidRequest::CASE_CLOSED, 'closed_at' => now()]);

        $content = $this->actingAs($advisor)
            ->get(route('advisor.cases.index', ['case_status' => LegalAidRequest::CASE_CLOSED]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('#44002', $content);
        $this->assertStringNotContainsString('#44001', $content);
    }

    public function test_free_filter_returns_pending_and_confirmed_free_cases(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        $freePending = LegalAidRequest::create([
            'ticket_number' => '44101',
            'full_name' => 'Free Pending',
            'email' => 'freepending@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Free case',
            'status' => LegalAidRequest::STATUS_PENDING,
            'base_price' => 0,
        ]);

        $freeConfirmed = LegalAidRequest::create([
            'ticket_number' => '44102',
            'full_name' => 'Free Confirmed',
            'email' => 'freeconfirmed@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Free case',
            'status' => LegalAidRequest::STATUS_CONFIRMED,
            'base_price' => 0,
        ]);

        $paid = LegalAidRequest::create([
            'ticket_number' => '44103',
            'full_name' => 'Paid Case',
            'email' => 'paid@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Paid case',
            'status' => LegalAidRequest::STATUS_PAID,
            'base_price' => 500,
            'paid_at' => now(),
        ]);

        $content = $this->actingAs($advisor)
            ->get(route('advisor.cases.index', ['payment_status' => 'free']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('#44101', $content);
        $this->assertStringContainsString('#44102', $content);
        $this->assertStringNotContainsString('#44103', $content);
    }

    public function test_paid_and_confirmed_filters_exclude_free_cases(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        $freeConfirmed = LegalAidRequest::create([
            'ticket_number' => '44201',
            'full_name' => 'Free Confirmed',
            'email' => 'freeconfirmed@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Free case',
            'status' => LegalAidRequest::STATUS_CONFIRMED,
            'base_price' => 0,
        ]);

        $bankConfirmed = LegalAidRequest::create([
            'ticket_number' => '44202',
            'full_name' => 'Bank Confirmed',
            'email' => 'bank@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Bank case',
            'status' => LegalAidRequest::STATUS_CONFIRMED,
            'base_price' => 500,
            'confirmed_at' => now(),
        ]);

        $paid = LegalAidRequest::create([
            'ticket_number' => '44203',
            'full_name' => 'Paid Case',
            'email' => 'paid@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Paid case',
            'status' => LegalAidRequest::STATUS_PAID,
            'base_price' => 500,
            'paid_at' => now(),
        ]);

        $confirmedContent = $this->actingAs($advisor)
            ->get(route('advisor.cases.index', ['payment_status' => 'confirmed']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('#44202', $confirmedContent);
        $this->assertStringNotContainsString('#44201', $confirmedContent);

        $paidContent = $this->get(route('advisor.cases.index', ['payment_status' => 'paid']))
            ->getContent();

        $this->assertStringContainsString('#44203', $paidContent);
        $this->assertStringNotContainsString('#44202', $paidContent);
    }

    public function test_advisor_can_mark_service_task_done_and_missing(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $service = Service::create([
            'name_en' => 'Consultation',
            'name_fr' => 'Consultation',
            'name_ar' => 'استشارة',
            'price' => 500,
            'allows_whatsapp' => true,
            'allows_office' => true,
        ]);

        $case = $this->paidCase(['ticket_number' => '45001', 'service_id' => $service->id]);
        $case->services()->attach($service->id);

        $this->actingAs($advisor)
            ->post(route('advisor.cases.toggle-service', [$case->id, $service->id]))
            ->assertRedirect();

        $this->assertNotNull($case->fresh()->services()->first()->pivot->completed_at);
        $this->assertTrue($case->fresh()->isFullyCompleted());
        $this->assertNotNull($case->fresh()->last_touched_at);

        $this->post(route('advisor.cases.toggle-service', [$case->id, $service->id]));
        $this->assertNull($case->fresh()->services()->first()->pivot->completed_at);
        $this->assertFalse($case->fresh()->isFullyCompleted());
    }

    public function test_toggle_works_for_cases_without_pivot_row(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $service = Service::create([
            'name_en' => 'Consultation',
            'name_fr' => 'Consultation',
            'name_ar' => 'استشارة',
            'price' => 500,
            'allows_whatsapp' => true,
            'allows_office' => true,
        ]);

        $case = $this->paidCase(['ticket_number' => '45101', 'service_id' => $service->id]);

        $this->assertDatabaseMissing('legal_aid_request_service', [
            'legal_aid_request_id' => $case->id,
            'service_id' => $service->id,
        ]);

        $this->actingAs($advisor)
            ->post(route('advisor.cases.toggle-service', [$case->id, $service->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $pivot = $case->fresh()->services()->first()->pivot;
        $this->assertNotNull($pivot->completed_at);
        $this->assertEquals($service->id, $pivot->service_id);

        $this->post(route('advisor.cases.toggle-service', [$case->id, $service->id]));
        $this->assertNull($case->fresh()->services()->first()->pivot->completed_at);
    }

    public function test_admin_layout_displays_error_flash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->withSession([])->get(route('admin.dashboard'));
        $response = $this->withSession(['error' => 'Something went wrong.'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Something went wrong.');

        $this->assertNotNull($response);
    }

    public function test_advisor_can_claim_first_contact(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $case = $this->paidCase(['ticket_number' => '46001']);

        $this->actingAs($advisor)
            ->post(route('advisor.cases.first-contact', $case->id))
            ->assertRedirect();

        $fresh = $case->fresh();
        $this->assertEquals($advisor->id, $fresh->advisor_id);
        $this->assertNotNull($fresh->first_contact_at);
        $this->assertNotNull($fresh->last_touched_at);
    }

    public function test_claimed_case_shows_first_contact_advisor(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor', 'name' => 'Sarah Advisor']);
        $case = $this->paidCase(['ticket_number' => '46002', 'advisor_id' => $advisor->id, 'first_contact_at' => now()]);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.show', $case->id))
            ->assertOk()
            ->assertSee('Sarah Advisor');
    }

    public function test_show_page_renders_services_notes_and_timeline(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $service = Service::create([
            'name_en' => 'Contract Review',
            'name_fr' => 'Examen de contrat',
            'name_ar' => 'مراجعة عقد',
            'price' => 500,
            'allows_whatsapp' => true,
            'allows_office' => true,
        ]);

        $case = $this->paidCase([
            'ticket_number' => '46003',
            'service_id' => $service->id,
            'case_description' => 'Long case description.',
            'last_touched_at' => now()->subHours(2),
        ]);
        $case->services()->attach($service->id, ['completed_at' => now()]);

        $case->caseNotes()->create([
            'user_id' => $advisor->id,
            'note' => 'Spoke with the client, next step is the contract review.',
        ]);

        $this->actingAs($advisor)
            ->get(route('advisor.cases.show', $case->id))
            ->assertOk()
            ->assertSee('Contract Review')
            ->assertSee('1/1 done')
            ->assertSee('Completed')
            ->assertSee('Mark as missing')
            ->assertSee('Spoke with the client, next step is the contract review.')
            ->assertSee('Status & Timeline', false)
            ->assertSee('2 hours ago');
    }

    public function test_case_can_be_closed_and_reopened(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $case = $this->paidCase(['ticket_number' => '47001']);

        $this->actingAs($advisor)
            ->post(route('advisor.cases.close', $case->id))
            ->assertRedirect();

        $fresh = $case->fresh();
        $this->assertTrue($fresh->isCaseClosed());
        $this->assertNotNull($fresh->closed_at);

        $this->post(route('advisor.cases.reopen', $case->id));
        $this->assertTrue($case->fresh()->isCaseOpen());
        $this->assertNull($case->fresh()->closed_at);
    }

    public function test_advisor_can_add_notes_that_touch_the_case(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);
        $case = $this->paidCase(['ticket_number' => '48001']);

        $this->actingAs($advisor)
            ->post(route('advisor.cases.notes', $case->id), [
                'note' => 'Called the customer, everything confirmed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('legal_aid_case_notes', [
            'legal_aid_request_id' => $case->id,
            'user_id' => $advisor->id,
            'note' => 'Called the customer, everything confirmed.',
        ]);
        $this->assertNotNull($case->fresh()->last_touched_at);
    }

    public function test_advisors_are_notified_when_case_becomes_paid(): void
    {
        Mail::fake();

        $advisorA = User::factory()->create(['role' => 'advisor']);
        $advisorB = User::factory()->create(['role' => 'advisor']);
        User::factory()->create(['role' => 'user']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '49001',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'base_price' => 500,
            'payment_method' => LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY,
        ]);

        $legalAidRequest->update([
            'status' => LegalAidRequest::STATUS_PAID,
            'paid_at' => now(),
        ]);

        \App\Support\AdvisorNotifier::caseReady($legalAidRequest);

        Mail::assertQueued(LegalAidAdvisorNotificationMail::class, 1);
        Mail::assertQueued(LegalAidAdvisorNotificationMail::class, fn ($mail) => $mail->hasTo($advisorA->email) && $mail->hasTo($advisorB->email));
    }
}