<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'cin',
        'ticket_number',
        'email',
        'full_name',
        'phone',
        'whatsapp',
        'case_description',
        'call_time',
        'status',
        'currency',
        'total_cents',
        'total_amount',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'locale',
        'payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'total_amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function legalAidRequest(): HasOne
    {
        return $this->hasOne(\App\Models\LegalAidRequest::class, 'order_id');
    }

    public function getTicketLabelAttribute(): string
    {
        return '#'.$this->ticket_number;
    }

    /**
     * CIN is ticket number
     */
    public function getCinLabelAttribute(): string
    {
        return $this->cin;
    }
}
