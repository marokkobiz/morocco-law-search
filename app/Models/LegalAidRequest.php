<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAidRequest extends Model
{
    protected $fillable = [
        'ticket_number',
        'full_name',
        'email',
        'phone',
        'whatsapp',
        'case_description',
        'status',
        'locale',
        'receipt_path',
        'paid_at',
        'confirmed_at',
    ];

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_CONFIRMED], true);
    }

    public function getTicketLabelAttribute(): string
    {
        return '#' . $this->ticket_number;
    }
}
