<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company',
        'phone',
        'email',
        'bar',
        'role',
        'password',
        'access_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAdvisor(): bool
    {
        return $this->role === 'advisor';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isAdvisor();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'advisor' => 'Advisor',
            default => 'User',
        };
    }
}
