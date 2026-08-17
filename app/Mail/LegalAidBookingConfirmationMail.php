<?php

namespace App\Mail;

use App\Models\LegalAidConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LegalAidBookingConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public LegalAidConfirmation $confirmation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.legal_aid_booking_confirmation_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.legal-aid.booking-confirmation',
        );
    }
}
