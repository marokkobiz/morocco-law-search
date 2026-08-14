<?php

namespace Tests\Feature;

use App\Mail\LegalAidAdminNotificationMail;
use App\Mail\LegalAidBookingConfirmationMail;
use App\Mail\LegalAidConfirmationMail;
use App\Mail\LegalAidReceiptNotificationMail;
use App\Mail\LegalAidRejectionMail;
use App\Mail\LegalAidTicketMail;
use App\Models\LegalAidConfirmation;
use App\Models\LegalAidRequest;
use App\Models\Service;
use App\Models\User;
use App\Support\PdfArabic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalAidFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(string $name = 'Initial Consultation', float $price = 500, array $modes = []): Service
    {
        return Service::create([
            'name_en' => $name,
            'name_fr' => $name,
            'name_ar' => $name,
            'price' => $price,
            'allows_office' => in_array('office', $modes, true),
            'allows_whatsapp' => in_array('whatsapp', $modes, true),
        ]);
    }

    private function basePayload(Service ...$services): array
    {
        return [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'I need help with a rental contract.',
            'service_ids' => array_map(fn (Service $service) => $service->id, $services),
            'payment_method' => 'google_pay',
        ];
    }

    private function submitBooking(array $payload): LegalAidConfirmation
    {
        $this->post(route('legal-aid.store'), $payload)->assertRedirect();

        return LegalAidConfirmation::firstOrFail();
    }

    public function test_submitting_request_creates_confirmation_and_sends_confirmation_email(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = $this->makeService();

        $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
            'consultation_mode' => 'whatsapp',
        ]))->assertRedirect()->assertSessionHas('confirmation_sent', 'jane@example.com');

        $this->assertDatabaseCount('legal_aid_confirmations', 1);
        $this->assertDatabaseCount('legal_aid_requests', 0);

        $confirmation = LegalAidConfirmation::first();
        $this->assertNotNull($confirmation->token);
        $this->assertNull($confirmation->confirmed_at);

        Mail::assertQueued(LegalAidBookingConfirmationMail::class, function ($mail) use ($confirmation) {
            return $mail->hasTo('jane@example.com')
                && $mail->confirmation->token === $confirmation->token;
        });

        Mail::assertNotQueued(LegalAidTicketMail::class);
        Mail::assertNotQueued(LegalAidAdminNotificationMail::class);
    }

    public function test_confirming_booking_creates_request_and_sends_ticket_with_pdf_attachment(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = $this->makeService(modes: ['office', 'whatsapp']);
        $confirmation = $this->submitBooking(array_merge($this->basePayload($service), ['consultation_mode' => 'whatsapp']));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))
            ->assertRedirect()
            ->assertRedirect(route('legal-aid.confirmed', ['ticket' => LegalAidRequest::firstOrFail()->ticket_number]));

        $ticket = LegalAidRequest::firstOrFail();

        $this->get(route('legal-aid.confirmed', ['ticket' => $ticket->ticket_number]))
            ->assertOk()
            ->assertSee('#'.$ticket->ticket_number)
            ->assertSee('href="'.route('legal-aid').'"', escape: false)
            ->assertSee(__('legal_aid.confirmed_back_booking'));

        $this->assertMatchesRegularExpression('/^\d{5}$/', $ticket->ticket_number);
        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $ticket->status);
        $this->assertEquals($service->id, $ticket->service_id);
        $this->assertEquals(500, (float) $ticket->base_price);
        $this->assertEquals('whatsapp', $ticket->consultation_mode);
        $this->assertEquals(LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY, $ticket->payment_method);
        $this->assertEquals('ar', $ticket->locale);

        $this->assertSame([$service->id], $ticket->services->pluck('id')->all());

        $this->assertNotNull($ticket->ticket_pdf_path);
        Storage::disk('public')->assertExists($ticket->ticket_pdf_path);

        $this->assertNotNull($confirmation->fresh()->confirmed_at);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $ticket->ticket_number
                && $mail->paymentUrl === 'https://pay.example/gpay'
                && count($mail->attachments()) === 1;
        });

        Mail::assertQueued(LegalAidAdminNotificationMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo(config('legal_aid.contact_email'))
                && $mail->request->ticket_number === $ticket->ticket_number;
        });
    }

    public static function emailLanguageProvider(): array
    {
        return [
            'arabic' => ['ar', 'مرحباً', 'رقم التذكرة'],
            'english' => ['en', 'Hello', 'Ticket No.'],
            'french' => ['fr', 'Bonjour', 'N° de ticket'],
        ];
    }

    #[DataProvider('emailLanguageProvider')]
    public function test_submitted_language_controls_confirmation_email_ticket_email_and_pdf(
        string $locale,
        string $expectedEmailGreeting,
        string $expectedPdfLabel
    ): void {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = $this->makeService(modes: ['office', 'whatsapp']);

        $this->withSession(['locale' => $locale])
            ->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
                'consultation_mode' => 'whatsapp',
            ]))->assertRedirect();

        $confirmation = LegalAidConfirmation::firstOrFail();
        $this->assertSame($locale, $confirmation->payload['locale']);

        Mail::assertQueued(LegalAidBookingConfirmationMail::class, function ($mail) use ($locale, $expectedEmailGreeting) {
            return $mail->locale === $locale
                && str_contains($mail->render(), $expectedEmailGreeting);
        });

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();
        $this->assertSame($locale, $ticket->locale);

        $this->get(route('legal-aid.confirmed', ['ticket' => $ticket->ticket_number]))
            ->assertOk()
            ->assertSee(__('legal_aid.confirmed_heading', [], $locale));

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($locale, $expectedEmailGreeting) {
            return $mail->locale === $locale
                && str_contains($mail->render(), $expectedEmailGreeting);
        });

        app()->setLocale($locale);
        $pdfHtml = view('pdf.legal-aid-ticket', ['request' => $ticket, 'locale' => $locale])->render();
        $this->assertStringContainsString(PdfArabic::shape($expectedPdfLabel, $locale), $pdfHtml);

        if ($locale === 'ar') {
            $this->assertMatchesRegularExpression('/[\x{FB50}-\x{FEFF}]/u', $pdfHtml);
            $this->assertStringNotContainsString('رقم التذكرة', $pdfHtml);
        }
    }

    public function test_multi_service_booking_sums_prices_and_attaches_all_services(): void
    {
        Mail::fake();
        Storage::fake('public');

        $free = $this->makeService('Initial interview', 0, modes: ['whatsapp']);
        $paid = $this->makeService('Tracking the case', 300, modes: ['whatsapp']);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($free, $paid), [
            'consultation_mode' => 'whatsapp',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();
        $this->assertSame([$free->id, $paid->id], $ticket->services->pluck('id')->sort()->values()->all());
        $this->assertEquals(300, (float) $ticket->base_price);
        $this->assertEquals(LegalAidRequest::STATUS_PENDING_PAYMENT, $ticket->status);

        $this->assertDatabaseHas('legal_aid_request_service', [
            'legal_aid_request_id' => $ticket->id,
            'service_id' => $free->id,
        ]);
        $this->assertDatabaseHas('legal_aid_request_service', [
            'legal_aid_request_id' => $ticket->id,
            'service_id' => $paid->id,
        ]);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) {
            $rendered = $mail->render();

            return str_contains($rendered, 'Initial interview')
                && str_contains($rendered, 'Tracking the case');
        });

        $pdfHtml = view('pdf.legal-aid-ticket', ['request' => $ticket, 'locale' => 'en'])->render();
        $this->assertStringContainsString('Initial interview', $pdfHtml);
        $this->assertStringContainsString('Tracking the case', $pdfHtml);
        $this->assertStringContainsString('300 MAD', $pdfHtml);
        $this->assertStringContainsString('270 MAD', $pdfHtml);
    }

    public function test_booking_keeps_consultation_mode_when_other_services_have_no_modes(): void
    {
        Mail::fake();
        Storage::fake('public');

        $withModes = $this->makeService('Initial interview', 300, modes: ['office', 'whatsapp']);
        $noModes = $this->makeService('Submission to the court', 200);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($withModes, $noModes), [
            'consultation_mode' => 'office',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();
        $this->assertEquals('office', $ticket->consultation_mode);
        $this->assertEquals(500, (float) $ticket->base_price);
    }

    public function test_confirm_booking_rejects_invalid_or_used_token(): void
    {
        Mail::fake();

        $this->get(route('legal-aid.confirm-booking', 'unknown-token'))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseCount('legal_aid_requests', 0);

        $service = $this->makeService();
        $confirmation = $this->submitBooking($this->basePayload($service));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();
        $this->assertDatabaseCount('legal_aid_requests', 1);

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseCount('legal_aid_requests', 1);
    }

    public function test_confirm_booking_rejects_expired_token(): void
    {
        Mail::fake();

        $service = $this->makeService();
        $confirmation = $this->submitBooking($this->basePayload($service));

        $confirmation->update(['expires_at' => now()->subMinute()]);

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseCount('legal_aid_requests', 0);
    }

    public function test_booking_requires_a_valid_email(): void
    {
        Mail::fake();

        $service = $this->makeService();

        $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), ['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);

        $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), ['email' => 'jane@example.com']))
            ->assertRedirect();
        $this->assertDatabaseCount('legal_aid_confirmations', 1);
    }

    public function test_booking_requires_at_least_one_service(): void
    {
        Mail::fake();

        $payload = $this->basePayload();
        unset($payload['service_ids']);

        $this->post(route('legal-aid.store'), $payload)->assertSessionHasErrors('service_ids');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);
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

        LegalAidRequest::create([
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

    public function test_booking_page_shows_confirmation_sent_after_submit(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = $this->makeService();

        $this->withSession(['locale' => 'en'])
            ->post(route('legal-aid.store'), $this->basePayload($service))
            ->assertRedirect()
            ->assertSessionHas('confirmation_sent', 'jane@example.com');

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('We sent a confirmation email to jane@example.com')
            ->assertDontSee('Your booking is confirmed');
    }

    public function test_confirmed_page_shows_ticket_and_download_link(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = $this->makeService();

        $this->withSession(['locale' => 'en'])
            ->post(route('legal-aid.store'), $this->basePayload($service))
            ->assertRedirect();

        $confirmation = LegalAidConfirmation::firstOrFail();

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();

        $this->get(route('legal-aid.confirmed', ['ticket' => $ticket->ticket_number]))
            ->assertOk()
            ->assertSee('Your booking is confirmed!')
            ->assertSee('#'.$ticket->ticket_number)
            ->assertSee('Download your ticket', false);
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

        $service = $this->makeService();

        $payload = $this->basePayload($service);

        $this->post(route('legal-aid.store'), $payload)->assertRedirect();
        $this->post(route('legal-aid.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('legal_aid_confirmations', 2);

        LegalAidConfirmation::query()->get()->each(function (LegalAidConfirmation $confirmation) {
            $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();
        });

        $this->assertDatabaseCount('legal_aid_requests', 2);
        $this->assertEquals(2, LegalAidRequest::query()->distinct('ticket_number')->count());
    }

    public function test_payment_totals_use_snapshot_price_not_live_service_price(): void
    {
        config(['legal_aid.online_discount_percent' => 10]);
        config(['legal_aid.bank_admin_fee_percent' => 10]);

        $service = $this->makeService();

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
            ->assertSee('Google Pay discount (10%)')
            ->assertDontSee('550 MAD')
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
            'payment_method' => 'bank',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('550 MAD')
            ->assertSee('Bank admin fee (10%)')
            ->assertDontSee('450 MAD')
            ->assertDontSee('Google Pay discount (10%)');
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

        $service = $this->makeService();

        $response = $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
            'phone' => '6123456',
            'consultation_mode' => 'office',
        ]));

        $response->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);
    }

    public function test_phone_accepts_valid_international_format(): void
    {
        Mail::fake();

        $service = $this->makeService();

        $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
            'phone' => '+212 612345678',
            'consultation_mode' => 'office',
        ]))->assertRedirect();

        $this->assertDatabaseCount('legal_aid_confirmations', 1);
    }

    public function test_consultation_mode_must_be_valid(): void
    {
        Mail::fake();

        $service = $this->makeService(modes: ['office', 'whatsapp']);

        $response = $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
            'consultation_mode' => 'carrier-pigeon',
        ]));

        $response->assertSessionHasErrors('consultation_mode');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);
    }

    public function test_booking_form_links_consultation_modes_to_selected_service(): void
    {
        $this->makeService('With Modes', 100, modes: ['office', 'whatsapp']);
        $this->makeService('Office Only', 200, modes: ['office']);
        $this->makeService('No Modes', 300, modes: []);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('data-modes="office,whatsapp"', false)
            ->assertSee('data-modes="office"', false)
            ->assertSee('data-modes=""', false);
    }

    public function test_consultation_mode_is_required_only_when_all_services_allow_modes(): void
    {
        Mail::fake();
        Storage::fake('public');

        $officeOnly = $this->makeService('Office Only', 200, modes: ['office']);

        $base = $this->basePayload($officeOnly);

        $this->post(route('legal-aid.store'), $base)
            ->assertSessionHasErrors('consultation_mode');

        $this->post(route('legal-aid.store'), $base + ['consultation_mode' => 'whatsapp'])
            ->assertSessionHasErrors('consultation_mode');

        $this->post(route('legal-aid.store'), $base + ['consultation_mode' => 'office'])
            ->assertRedirect();

        $this->assertDatabaseCount('legal_aid_confirmations', 1);
    }

    public function test_consultation_mode_is_ignored_when_services_have_no_common_modes(): void
    {
        Mail::fake();
        Storage::fake('public');

        $officeOnly = $this->makeService('Office Only', 200, modes: ['office']);
        $whatsappOnly = $this->makeService('WhatsApp Only', 300, modes: ['whatsapp']);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($officeOnly, $whatsappOnly), [
            'consultation_mode' => 'whatsapp',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $this->assertDatabaseHas('legal_aid_requests', [
            'consultation_mode' => null,
        ]);
    }

    public function test_consultation_mode_is_ignored_when_service_has_no_modes(): void
    {
        Mail::fake();
        Storage::fake('public');

        $service = $this->makeService('No Modes', 300, modes: []);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($service), [
            'consultation_mode' => 'office',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $this->assertDatabaseHas('legal_aid_requests', [
            'consultation_mode' => null,
        ]);
    }

    public function test_payment_method_is_required_for_paid_services(): void
    {
        Mail::fake();

        $service = $this->makeService();

        $payload = $this->basePayload($service);
        unset($payload['payment_method']);

        $this->post(route('legal-aid.store'), $payload)
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);
    }

    public function test_payment_method_is_not_required_for_free_services(): void
    {
        Mail::fake();

        $service = $this->makeService('Initial interview (case content) 30 min.', 0, modes: ['whatsapp']);

        $payload = $this->basePayload($service);
        unset($payload['payment_method']);

        $this->post(route('legal-aid.store'), array_merge($payload, ['consultation_mode' => 'whatsapp']))
            ->assertRedirect();
        $this->assertDatabaseCount('legal_aid_confirmations', 1);
    }

    public function test_payment_method_must_be_valid(): void
    {
        Mail::fake();

        $service = $this->makeService();

        $this->post(route('legal-aid.store'), array_merge($this->basePayload($service), [
            'payment_method' => 'cash',
        ]))->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('legal_aid_confirmations', 0);
    }

    public function test_bank_booking_persists_method_and_emails_bank_payment_link(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = $this->makeService(modes: ['whatsapp']);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($service), [
            'consultation_mode' => 'whatsapp',
            'payment_method' => 'bank',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();
        $this->assertEquals(LegalAidRequest::PAYMENT_METHOD_BANK, $ticket->payment_method);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $ticket->ticket_number
                && $mail->paymentUrl === ''
                && $mail->paymentLink === route('legal-aid.payment', $ticket->ticket_number);
        });

        Mail::assertQueued(LegalAidAdminNotificationMail::class, function ($mail) use ($ticket) {
            return $mail->request->ticket_number === $ticket->ticket_number
                && $mail->paymentUrl === '';
        });
    }

    public function test_google_pay_booking_sends_external_payment_url(): void
    {
        Mail::fake();
        Storage::fake('public');
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $service = $this->makeService(modes: ['office']);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($service), [
            'consultation_mode' => 'office',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

        $ticket = LegalAidRequest::firstOrFail();

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($ticket) {
            return $mail->request->ticket_number === $ticket->ticket_number
                && $mail->paymentUrl === 'https://pay.example/gpay'
                && $mail->paymentLink === route('legal-aid.payment', $ticket->ticket_number);
        });
    }

    public function test_bank_payment_page_hides_google_pay_section(): void
    {
        config(['cashier.key' => 'pk_test_dummy']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '22221',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'base_price' => 500,
            'payment_method' => 'bank',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid.payment', $legalAidRequest->ticket_number))
            ->assertOk()
            ->assertSee('Bank Transfer')
            ->assertSee('Upload Receipt')
            ->assertDontSee('Pay Online with Google Pay')
            ->assertDontSee('id="google-pay-button"', false);
    }

    public function test_payment_form_renders_payment_method_cards_with_notes(): void
    {
        $this->makeService();

        $this->withSession(['locale' => 'en'])
            ->get(route('legal-aid'))
            ->assertOk()
            ->assertSee('Choose how to pay')
            ->assertSee('name="payment_method"', false)
            ->assertSee('value="google_pay"', false)
            ->assertSee('value="bank"', false)
            ->assertSee('You get 10% off')
            ->assertSee('10% admin fee applies');
    }

    public function test_rejected_request_payment_page_allows_retry(): void
    {
        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '33333',
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'Test case',
            'payment_method' => 'bank',
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

        $service = $this->makeService('Initial interview (case content) 30 min.', 0, modes: ['whatsapp']);

        $confirmation = $this->submitBooking(array_merge($this->basePayload($service), [
            'consultation_mode' => 'whatsapp',
        ]));

        $this->get(route('legal-aid.confirm-booking', $confirmation->token))->assertRedirect();

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

    public function test_ticket_pdf_renders_in_booking_locale(): void
    {
        app()->setLocale('ar');

        $service = $this->makeService('Consultation', 300, modes: ['office', 'whatsapp']);

        $legalAidRequest = LegalAidRequest::create([
            'ticket_number' => '12121',
            'full_name' => 'جين دو',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'case_description' => 'قضية',
            'service_id' => $service->id,
            'base_price' => 300,
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
            'payment_method' => LegalAidRequest::PAYMENT_METHOD_GOOGLE_PAY,
            'locale' => 'ar',
        ]);

        $legalAidRequest->services()->attach($service);
        $legalAidRequest->load('services');

        $html = view('pdf.legal-aid-ticket', [
            'request' => $legalAidRequest,
            'locale' => 'ar',
        ])->render();

        $this->assertStringNotContainsString('dir="rtl"', $html);
        $this->assertStringContainsString(PdfArabic::shape('رقم التذكرة', 'ar'), $html);
        $this->assertStringContainsString(PdfArabic::shape('العميل', 'ar'), $html);
        $this->assertStringNotContainsString('رقم التذكرة', $html);

        // Services are listed as priced rows in the payment section, not in the header.
        $this->assertStringNotContainsString(PdfArabic::shape('الخدمة (الخدمات)', 'ar'), $html);
        $this->assertStringContainsString('Consultation', $html);
        $this->assertStringContainsString('300 MAD', $html);
        $this->assertStringContainsString(PdfArabic::shape('السعر الأساسي', 'ar'), $html);
        $this->assertStringContainsString(PdfArabic::shape('طريقة الدفع', 'ar'), $html);
        $this->assertStringContainsString('270 MAD', $html);

        // dompdf 3.x only embeds the Arabic-bearing DejaVu Sans variants at
        // font-weight 400 and 700; other weights fall back to a font without
        // the Arabic presentation-form glyphs and render as "?".
        $this->assertStringNotContainsString('font-weight: 500', $html);
        $this->assertStringNotContainsString('font-weight: 600', $html);
        $this->assertStringNotContainsString('font-weight: 800', $html);
        $this->assertStringNotContainsString('font-weight: 900', $html);
        $this->assertStringNotContainsString('&minus;', $html);

        // Arabic renders the booking column on the left and the client column
        // on the right (dompdf lays table cells out left-to-right).
        $this->assertLessThan(
            strpos($html, PdfArabic::shape('العميل', 'ar')),
            strpos($html, PdfArabic::shape('الحجز', 'ar'))
        );

        // ar-php must not wrap long strings into independently-reversed
        // visual lines (that garbles the payment note); dompdf does the wrapping.
        $shapedNote = PdfArabic::shape(__('legal_aid_ticket.note_paid', [], 'ar'), 'ar');
        $this->assertStringNotContainsString("\n", $shapedNote);

        app()->setLocale('en');
    }
}
