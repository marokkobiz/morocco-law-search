<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalAidConfirmation extends Model
{
    protected $fillable = [
        'token',
        'email',
        'payload',
        'expires_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}
