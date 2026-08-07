<?php

namespace Tests\Feature;

use App\Mail\LegalAidAdminNotificationMail;
use App\Mail\LegalAidConfirmationMail;
use App\Mail\LegalAidReceiptNotificationMail;
use App\Mail\LegalAidTicketMail;
use App\Models\LegalAidRequest;
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
        config(['legal_aid.payment_url' => 'https://pay.example/gpay']);

        $response = $this->post(route('legal-aid.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+212600000000',
            'whatsapp' => '+212600000001',
            'case_description' => 'I need help with a rental contract.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('ticket');

        $this->assertDatabaseHas('legal_aid_requests', [
            'email' => 'jane@example.com',
            'status' => LegalAidRequest::STATUS_PENDING_PAYMENT,
        ]);

        $ticket = LegalAidRequest::first()->ticket_number;
        $this->assertMatchesRegularExpression('/^\d{5}$/', $ticket);

        Mail::assertQueued(LegalAidTicketMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo('jane@example.com')
                && $mail->request->ticket_number === $ticket
                && $mail->paymentUrl === 'https://pay.example/gpay';
        });

        Mail::assertQueued(LegalAidAdminNotificationMail::class, function ($mail) use ($ticket) {
            return $mail->hasTo(config('legal_aid.contact_email'))
                && $mail->request->ticket_number === $ticket;
        });

        $this->assertStringStartsWith('#', session('ticket'));
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
}
