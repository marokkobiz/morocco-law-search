<?php

namespace App\Mail;

use App\Models\LegalAidRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LegalAidAdvisorNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalAidRequest $request,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->request->isFree()
            ? __('emails.legal_aid_advisor_subject_free', ['ticket' => $this->request->ticketLabel])
            : __('emails.legal_aid_advisor_subject', ['ticket' => $this->request->ticketLabel]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-aid.advisor-notification',
        );
    }
}
