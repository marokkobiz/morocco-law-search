<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name_en',
        'name_fr',
        'name_ar',
        'description_en',
        'description_fr',
        'description_ar',
        'price',
        'price_display_en',
        'price_display_fr',
        'price_display_ar',
        'notes_en',
        'notes_fr',
        'notes_ar',
        'additional_notes_en',
        'additional_notes_fr',
        'additional_notes_ar',
        'allows_office',
        'allows_whatsapp',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'allows_office' => 'boolean',
            'allows_whatsapp' => 'boolean',
        ];
    }

    public function getConsultationModesAttribute(): array
    {
        return collect(['office', 'whatsapp'])
            ->filter(fn (string $mode) => $this->{'allows_'.$mode})
            ->values()
            ->all();
    }

    public function allowsMode(?string $mode): bool
    {
        return $mode !== null && in_array($mode, $this->consultationModes, true);
    }

    public function getConsultationModesListAttribute(): string
    {
        return implode(',', $this->consultationModes);
    }

    public function getNameAttribute(): string
    {
        return $this->{'name_'.app()->getLocale()} ?? $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->{'description_'.app()->getLocale()} ?? $this->description_en;
    }

    public function getPriceDisplayAttribute(): ?string
    {
        return $this->{'price_display_'.app()->getLocale()} ?: $this->price_display_en;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->{'notes_'.app()->getLocale()} ?: $this->notes_en;
    }

    public function getAdditionalNotesAttribute(): ?string
    {
        return $this->{'additional_notes_'.app()->getLocale()} ?: $this->additional_notes_en;
    }

    public function getPriceLabelAttribute(): string
    {
        if ($this->price_display) {
            return $this->price_display;
        }

        if ($this->price === null || (float) $this->price === 0.0) {
            return __('legal_aid.free');
        }

        return number_format((float) $this->price, 0).' MAD';
    }

    public function legalAidRequests(): HasMany
    {
        return $this->hasMany(LegalAidRequest::class);
    }
}
