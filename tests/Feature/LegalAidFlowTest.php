<?php

namespace Tests\Feature;

use App\Mail\LegalAidAdminNotificationMail;
use App\Mail\LegalAidConfirmationMail;
use App\Mail\LegalAidReceiptNotificationMail;
use App\Mail\LegalAidRejectionMail;
use App\Mail\LegalAidTicketMail;
use App\Models\LegalAidRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalAidFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_request_creates_ticket_and_sends_emails(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
            'allows_office' => true,
            'allows_whatsapp' => true,
        ]);

        $response = $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'I need help with a rental contract.',
            'service_id' => $service->id,
            'consultation_mode' => 'whatsapp',
        ]);

        $ticket = LegalAidRequest::first()->ticket_number;
        $this->assertMatchesRegularExpression('/^\d{5}$/', $ticket);

        $response->assertRedirect()->assertSessionHas('ticket', '#'.$ticket);

        $this->get(route('legal-aid.ticket-pdf', $ticket))
            ->assertDownload('legal-aid-ticket-'.$ticket.'.pdf');

        $this->assertDatabaseHas('legal_aid_requests', [
            'email' => 'jane@example.com',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'service_id' => $service->id,
            'base_price' => 500,
            'consultation_mode' => 'whatsapp',
        ]);

        $fresh = LegalAidRequest::first();
        $this->assertNotNull($fresh->ticket_pdf_path);
        Storage::disk('public')->assertExists($fresh->ticket_pdf_path);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $ticket
                && $mail->paymentUrl === 'https://pay.example/gpay';
        });

        Mail::assertQueued(LegalAidAdminNotificationMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo(config('legal_aid.contact_email'))
                && $mail->request->ticket_number === $ticket;
        });
    }

    public function test_ticket_pdf_can_be_downloaded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tickets/legal-aid-ticket-99999.pdf', '%PDF-1.4 test');

        LegalAidRequest::create([
            'ticket_number' => '99999',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'ticket_pdf_path' => 'tickets/legal-aid-ticket-99999.pdf',
        ]);

        $this->get(route('legal-aid.ticket-pdf', '99999'))
            ->assertOk()
            ->assertDownload('legal-aid-ticket-99999.pdf');
    }

    public function test_ticket_pdf_download_returns_404_for_missing_or_unknown(): void
    {
        Storage::fake('public');

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '99999',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->get(route('legal-aid.ticket-pdf', '99999'))->assertNotFound();
        $this->get(route('legal-aid.ticket-pdf', '00000'))->assertNotFound();
    }

    public function test_booking_page_after_submit_shows_success_message_and_download_link(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'service_id' => $service->id,
        ])->assertRedirect()->assertSessionHas('ticket_number');

        $ticket = LegalAidRequest::first()->ticket_number;

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('Your request has been received!')
            ->assertSee('Download your ticket', false)
            ->assertSee(route('legal-aid.ticket-pdf', $ticket), false)
            ->assertDontSee('Jane Doe');
    }

    public function test_booking_page_shows_service_price_display_and_notes(): void
    {
        Service::create([
            'name_en' => 'Open a case',
            'name_fr' => 'Ouvrir un dossier',
            'name_ar' => 'فتح قضية',
            'price' => 100,
            'price_display_en' => '100,00 MAD *',
            'notes_en' => 'Fee to the court',
            'additional_notes_en' => 'Possibly a bailiff fee',
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('Open a case')
            ->assertSee('100,00 MAD *')
            ->assertSee('Fee to the court')
            ->assertSee('Possibly a bailiff fee')
            ->assertSee('Note: All prices are including VAT.');
    }

    public function test_free_service_shows_localized_free_label(): void
    {
        Service::create([
            'name_en' => 'Initial interview (case content) 30 min.',
            'name_fr' => 'Premier entretien (contenu du dossier) 30 min.',
            'name_ar' => 'مقابلة أولية (محتوى القضية) 30 دقيقة',
            'price' => 0,
            'notes_en' => 'Only by WhatsApp',
        ]);

        $this->withSession(['locale' => 'fr'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('Gratuit')
            ->assertSee('Only by WhatsApp');
    }

    public function test_two_submissions_get_distinct_ticket_numbers(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $payload = [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'I need help with a rental contract.',
            'service_id' => $service->id,
            'consultation_mode' => 'whatsapp',
        ];

        $this->post(route('legal-aid.store'), $payload)->assertRedirect();
        $this->post(route('legal-aid.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('legal_aid_requests', 2);
        $this->assertEquals(2, LegalAidRequest::query()->distinct('ticket_number')->count());
    }

    public function test_payment_totals_use_snapshot_price_not_live_service_price(): void
    {
        config(['legal_aid.online_discount_percent' => 10]);
        config(['legal_aid.bank_admin_fee_percent' => 10]);

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '98989',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'service_id' => $service->id,
            'base_price' => 500,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $service->update(['price' => 999]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('450 MAD')
            ->assertSee('550 MAD')
            ->assertDontSee('899')
            ->assertDontSee('1099');
    }

    public function test_payment_page_shows_status_and_rejects_unknown_ticket(): void
    {
        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '99999',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee($legalAidRequest->ticketLabel);

        $this->get(route('legal-aid.payment', '00000'))->assertNotFound();
    }

    public function test_payment_page_shows_auto_calculated_totals(): void
    {
        config(['legal_aid.online_discount_percent' => 10]);
        config(['legal_aid.bank_admin_fee_percent' => 10]);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '99998',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'service_id' => null,
            'base_price' => 500,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('450 MAD')
            ->assertSee('550 MAD')
            ->assertSee('Google Pay discount (10%)')
            ->assertSee('Bank admin fee (10%)');
    }

    public function test_receipt_upload_notifies_admin(): void
    {
        Mail::fake();
        Storage::fake('public');

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '88888',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->post(route('legal-aid.receipt', $legalAidRequest->ticket_number), [
            'receipt' => UploadedFile::fake()->createWithContent(
                'receipt.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            ),
        ])->assertRedirect();

        $this->assertNotNull($legalAidRequest->fresh()->receipt_path);
        Mail::assertQueued(LegalAidReceiptNotificationMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo(config('legal_aid.contact_email'))
                && $mail->request->ticket_number === $legalAidRequest->ticket_number
                && count($mail->attachments()) === 1;
        });
    }

    public function test_admin_confirms_case_and_sends_confirmation_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '77777',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
            'receipt_path' => 'receipts/receipt.png',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.confirm', $legalAidRequest->id))
            ->assertRedirect();

        $legalAidRequest->refresh();
        $this->assertEquals(LegalAidRequest::STATUS_CONFIRMED, $legalAidRequest->status);
        $this->assertNotNull($legalAidRequest->paid_at);
        $this->assertNotNull($legalAidRequest->confirmed_at);

        Mail::assertQueued(LegalAidConfirmationMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $legalAidRequest->ticket_number;
        });
    }

    public function test_admin_can_resend_payment_link(): void
    {
        Mail::fake();
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '66666',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.resend', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $legalAidRequest->ticket_number
                && $mail->paymentUrl === 'https://pay.example/gpay';
        });
    }

    public function test_resend_payment_link_not_allowed_for_paid_requests(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '55555',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.resend', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNotQueued(LegalAidTicketMail::class);
    }

    public function test_resend_on_rejected_request_resets_to_pending_and_clears_receipt(): void
    {
        Mail::fake();
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $receiptPath = UploadedFile::fake()->create('receipt.png', 100)->store('receipts', 'public');

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '77777',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_REJECTED,
            'locale' => 'en',
            'receipt_path' => $receiptPath,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.resend', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $legalAidRequest->fresh();
        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $fresh->status);
        $this->assertNull($fresh->receipt_path);
        Storage::disk('public')->assertMissing($receiptPath);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $legalAidRequest->ticket_number;
        });
    }

    public function test_admin_can_reject_request_and_notifies_client(): void
    {
        Mail::fake();
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $receiptPath = UploadedFile::fake()->create('receipt.png', 100)->store('receipts', 'public');

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '44444',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'fr',
            'receipt_path' => $receiptPath,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.reject', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $legalAidRequest->fresh();
        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $fresh->status);
        $this->assertNull($fresh->receipt_path);
        Storage::disk('public')->assertMissing($receiptPath);

        Mail::assertQueued(LegalAidRejectionMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $legalAidRequest->ticket_number
                && $mail->paymentLink === route('legal-aid.payment', $legalAidRequest->ticket_number);
        });
    }

    public function test_admin_cannot_reject_request_without_receipt(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '42424',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.reject', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $legalAidRequest->fresh()->status);
        Mail::assertNotQueued(LegalAidRejectionMail::class);
    }

    public function test_phone_must_be_in_international_format(): void
    {
        Mail::fake();

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $response = $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '6123456',
            'case_description' => 'Test case',
            'service_id' => $service->id,
            'consultation_mode' => 'office',
        ]);

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('legal_aid_requests', 0);
    }

    public function test_phone_accepts_valid_international_format(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212 612345678',
            'case_description' => 'Test case',
            'service_id' => $service->id,
            'consultation_mode' => 'office',
        ])->assertRedirect();

        $this->assertDatabaseCount('legal_aid_requests', 1);
    }

    public function test_consultation_mode_must_be_valid(): void
    {
        Mail::fake();

        $service = Service::create([
            'name_en' => 'Initial Consultation',
            'name_fr' => 'Consultation initiale',
            'name_ar' => 'الاستشارة الأولية',
            'price' => 500,
        ]);

        $response = $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212 612345678',
            'case_description' => 'Test case',
            'service_id' => $service->id,
            'consultation_mode' => 'carrier-pigeon',
        ]);

        $response->assertSessionHasErrors('consultation_mode');
        $this->assertDatabaseCount('legal_aid_requests', 0);
    }

    public function test_booking_form_links_consultation_modes_to_selected_service(): void
    {
        Service::create([
            'name_en' => 'With Modes',
            'name_fr' => 'Avec modes',
            'name_ar' => 'مع خيارات',
            'price' => 100,
            'allows_office' => true,
            'allows_whatsapp' => true,
        ]);
        Service::create([
            'name_en' => 'Office Only',
            'name_fr' => 'Bureau uniquement',
            'name_ar' => 'المكتب فقط',
            'price' => 200,
            'allows_office' => true,
            'allows_whatsapp' => false,
        ]);
        Service::create([
            'name_en' => 'No Modes',
            'name_fr' => 'Sans modes',
            'name_ar' => 'بدون خيارات',
            'price' => 300,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('data-modes="office,whatsapp"', false)
            ->assertSee('data-modes="office"', false)
            ->assertSee('data-modes=""', false);
    }

    public function test_consultation_mode_is_required_only_when_service_allows_modes(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = Service::create([
            'name_en' => 'Office Only',
            'name_fr' => 'Bureau uniquement',
            'name_ar' => 'المكتب فقط',
            'price' => 200,
            'allows_office' => true,
            'allows_whatsapp' => false,
        ]);

        $base = [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212 612345678',
            'case_description' => 'Test case',
            'service_id' => $service->id,
        ];

        $this->post(route('legal-aid.store'), $base)
            ->assertSessionHasErrors('consultation_mode');

        $this->post(route('legal-aid.store'), $base + ['consultation_mode' => 'whatsapp'])
            ->assertSessionHasErrors('consultation_mode');

        $this->post(route('legal-aid.store'), $base + ['consultation_mode' => 'office'])
            ->assertRedirect();

        $this->assertDatabaseCount('legal_aid_requests', 1);
        $this->assertDatabaseHas('legal_aid_requests', ['consultation_mode' => 'office']);
    }

    public function test_consultation_mode_is_ignored_when_service_has_no_modes(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = Service::create([
            'name_en' => 'No Modes',
            'name_fr' => 'Sans modes',
            'name_ar' => 'بدون خيارات',
            'price' => 300,
        ]);

        $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212 612345678',
            'case_description' => 'Test case',
            'service_id' => $service->id,
            'consultation_mode' => 'office',
        ])->assertRedirect();

        $this->assertDatabaseHas('legal_aid_requests', [
            'consultation_mode' => null,
        ]);
    }

    public function test_rejected_request_payment_page_allows_retry(): void
    {
        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '33333',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'status' => LegalAidRequest::STATUS_REJECTED,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('Payment not verified')
            ->assertSee('Upload Receipt');
    }

    public function test_free_service_request_sends_no_payment_link(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = Service::create([
            'name_en' => 'Initial interview (case content) 30 min.',
            'name_fr' => 'Premier entretien (contenu du dossier) 30 min.',
            'name_ar' => 'مقابلة أولية (محتوى القضية) 30 دقيقة',
            'price' => 0,
        ]);

        $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'I need help with a rental contract.',
            'service_id' => $service->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('legal_aid_requests', [
            'email' => 'jane@example.com',
            'base_price' => 0,
            'status' => LegalAidRequest::STATUS_PENDING,
        ]);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) {
            return $mail->hasTo('jane@example.com')
                && $mail->paymentUrl === ''
                && $mail->paymentLink === '';
        });

        Mail::assertQueued(LegalAidAdminNotificationMail::class, function ($mail) {
            return $mail->request->isFree();
        });
    }

    public function test_free_request_ticket_email_shows_whatsapp_message(): void
    {
        app()->setLocale('en');

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '33331',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'Test case',
            'base_price' => 0,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);

        $html = (new LegalAidTicketMail($legalAidRequest, '', ''))->render();

        $this->assertStringContainsString('This service is free of charge', $html);
        $this->assertStringContainsString('+212600000001', $html);
        $this->assertStringNotContainsString('Pay Online with Google Pay', $html);
        $this->assertStringNotContainsString('payment receipt', $html);
    }

    public function test_free_request_payment_page_shows_no_payment_message(): void
    {
        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '33332',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'Test case',
            'base_price' => 0,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('No payment needed')
            ->assertSee('+212600000001')
            ->assertDontSee('Pay Online with Google Pay')
            ->assertDontSee('Upload Receipt');
    }

    public function test_admin_can_confirm_free_request_without_receipt(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '33333',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'base_price' => 0,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.legal-aid.confirm', $legalAidRequest->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $legalAidRequest->refresh();
        $this->assertEquals(LegalAidRequest::STATUS_CONFIRMED, $legalAidRequest->status);
        $this->assertNull($legalAidRequest->paid_at);
        $this->assertNotNull($legalAidRequest->confirmed_at);

        Mail::assertQueued(LegalAidConfirmationMail::class, function ($mail) use ($legalAidRequest) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $legalAidRequest->ticket_number;
        });
    }
}
