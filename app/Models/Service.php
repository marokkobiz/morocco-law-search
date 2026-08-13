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
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function getNameAttribute(): string
    {
        return $this->{'name_'.app()->getLocale()} ?? $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->{'description_'.app()->getLocale()} ?? $this->description_en;
    }

    public function getPriceLabelAttribute(): string
    {
        return number_format((float) $this->price, 0).' MAD';
    }

    public function legalAidRequests(): HasMany
    {
        return $this->hasMany(LegalAidRequest::class);
    }
}
