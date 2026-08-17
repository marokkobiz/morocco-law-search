<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LegalAidRequest extends Model
{
    protected $fillable = [
        'ticket_number',
        'full_name',
        'email',
        'phone',
        'whatsapp',
        'case_description',
        'consultation_mode',
        'call_time',
        'payment_method',
        'service_id',
        'base_price',
        'status',
        'locale',
        'receipt_path',
        'ticket_pdf_path',
        'paid_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
        ];
    }

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_METHOD_GOOGLE_PAY = 'google_pay';

    public const PAYMENT_METHOD_BANK = 'bank';

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_CONFIRMED], true);
    }

    public function isFree(): bool
    {
        return $this->base_price !== null && (float) $this->base_price === 0.0;
    }

    public function getPayableTotalAttribute(): ?float
    {
        if ($this->base_price === null) {
            return null;
        }

        return $this->payment_method === self::PAYMENT_METHOD_BANK
            ? $this->bankTotal
            : $this->onlineTotal;
    }

    public function getTicketLabelAttribute(): string
    {
        return '#'.$this->ticket_number;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function getSelectedServicesAttribute(): Collection
    {
        if ($this->relationLoaded('services') && $this->services->isNotEmpty()) {
            return $this->services;
        }

        $service = $this->service;

        return $service ? new Collection([$service]) : new Collection;
    }

    public function getServicesSummaryAttribute(): string
    {
        return $this->selectedServices
            ->map(fn (Service $service) => $service->name)
            ->implode(', ');
    }

    public function getOnlineTotalAttribute(): ?float
    {
        if ($this->base_price === null) {
            return null;
        }

        $discount = (float) config('legal_aid.online_discount_percent', 10);

        return round((float) $this->base_price * (1 - $discount / 100), 2);
    }

    public function getBankTotalAttribute(): ?float
    {
        if ($this->base_price === null) {
            return null;
        }

        $fee = (float) config('legal_aid.bank_admin_fee_percent', 10);

        return round((float) $this->base_price * (1 + $fee / 100), 2);
    }
}
