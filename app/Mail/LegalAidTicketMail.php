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

class LegalAidTicketMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalAidRequest $request,
        public string $paymentUrl,
        public string $paymentLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.legal_aid_ticket_subject', ['ticket' => $this->request->ticketLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-aid.ticket',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->request->ticket_pdf_path;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('public', $path)
                ->as('legal-aid-ticket-'.$this->request->ticket_number.'.pdf'),
        ];
    }
}
