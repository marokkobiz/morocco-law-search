<?php

namespace App\Mail;

use App\Models\LegalAidRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class LegalAidReceiptNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalAidRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.legal_aid_receipt_subject', ['ticket' => $this->request->ticketLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-aid.receipt-notification',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $receiptPath = $this->request->receipt_path;

        if (! $receiptPath || ! Storage::disk('public')->exists($receiptPath)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('public', $receiptPath),
        ];
    }
}
