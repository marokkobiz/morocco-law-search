<?php

namespace App\Mail;

use App\Models\LegalAidRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LegalAidRejectionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalAidRequest $request,
        public string $paymentLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.legal_aid_rejection_subject', ['ticket' => $this->request->ticketLabel]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-aid.rejection',
        );
    }
}
