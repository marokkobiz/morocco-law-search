<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'legal_aid_request_id',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'currency',
        'country',
        'amount_cents',
        'amount',
        'status',
        'payment_method_type',
        'failure_code',
        'failure_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public const STATUS_REQUIRES_PAYMENT_METHOD = 'requires_payment_method';

    public const STATUS_REQUIRES_CONFIRMATION = 'requires_confirmation';

    public const STATUS_REQUIRES_ACTION = 'requires_action';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_REQUIRES_PAYMENT_METHOD,
            self::STATUS_REQUIRES_CONFIRMATION,
            self::STATUS_REQUIRES_ACTION,
            self::STATUS_PROCESSING,
        ], true);
    }

    public function legalAidRequest(): BelongsTo
    {
        return $this->belongsTo(LegalAidRequest::class);
    }
}
