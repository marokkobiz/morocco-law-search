<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'advisor_id',
        'case_status',
        'closed_at',
        'first_contact_at',
        'last_touched_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_contact_at' => 'datetime',
            'last_touched_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const PAYMENT_METHOD_GOOGLE_PAY = 'google_pay';

    public const PAYMENT_METHOD_STRIPE = 'stripe';

    public const PAYMENT_METHOD_BANK = 'bank';

    public const CASE_OPEN = 'open';

    public const CASE_CLOSED = 'closed';

    /**
     * Statuses under which the case is visible to advisors: paid via
     * Google Pay, confirmed by the admin, or a free consultation.
     */
    public function scopeVisibleToAdvisors(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PAID,
            self::STATUS_CONFIRMED,
            self::STATUS_PENDING,
        ]);
    }

    public function scopeCaseStatus(Builder $query, string $caseStatus): Builder
    {
        return $query->where('case_status', $caseStatus);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_CONFIRMED], true);
    }

    public function isFree(): bool
    {
        return $this->base_price !== null && (float) $this->base_price === 0.0;
    }

    public function isVisibleToAdvisors(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_CONFIRMED, self::STATUS_PENDING], true);
    }

    public function isCaseOpen(): bool
    {
        return $this->case_status === self::CASE_OPEN;
    }

    public function isCaseClosed(): bool
    {
        return $this->case_status === self::CASE_CLOSED;
    }

    public function isFullyCompleted(): bool
    {
        if ($this->selectedServices->isEmpty()) {
            return false;
        }

        return $this->selectedServices->every(fn (Service $service) => $this->serviceIsCompleted($service));
    }

    public function serviceIsCompleted(Service $service): bool
    {
        $match = $this->services->firstWhere('id', $service->id);

        return $match !== null && $match->pivot->completed_at !== null;
    }

    public function completedServices(): Collection
    {
        return $this->selectedServices->filter(fn (Service $service) => $this->serviceIsCompleted($service));
    }

    public function missingServices(): Collection
    {
        return $this->selectedServices->filter(fn (Service $service) => ! $this->serviceIsCompleted($service));
    }

    public function touchCase(): static
    {
        $this->forceFill(['last_touched_at' => now()])->save();

        return $this;
    }

    public function isOnlinePayment(): bool
    {
        return in_array($this->payment_method, [self::PAYMENT_METHOD_STRIPE, self::PAYMENT_METHOD_GOOGLE_PAY], true);
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
        return $this->belongsToMany(Service::class)
            ->using(LegalAidRequestService::class)
            ->withPivot('completed_at');
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function caseNotes(): HasMany
    {
        return $this->hasMany(LegalAidCaseNote::class)->latest();
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
