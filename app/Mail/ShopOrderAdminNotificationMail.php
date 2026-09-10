<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShopOrderAdminNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('emails.shop_admin_subject', [
                'ticket' => $this->order->ticket_number,
                'amount' => number_format($this->order->total_cents / 100, 2),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shop.admin-notification',
        );
    }
}
