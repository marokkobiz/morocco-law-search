<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'password',
        'access_status',
        'stripe_customer_id',
        'stripe_subscription_id',
        'trial_ends_at',
        'billing_active_at',
        'billing_ends_at',
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
            'trial_ends_at' => 'datetime',
            'billing_active_at' => 'datetime',
            'billing_ends_at' => 'datetime',
        ];
    }

    public function hasBillableAccess(): bool
    {
        if ($this->access_status === 'active') {
            return true;
        }

        if ($this->trial_ends_at && $this->trial_ends_at->isFuture()) {
            return true;
        }

        return $this->billing_active_at !== null
            && ($this->billing_ends_at === null || $this->billing_ends_at->isFuture());
    }

    public function requiresPayment(): bool
    {
        return config('billing.require_payment') && !$this->hasBillableAccess();
    }

    public function markBillingActive(string $subscriptionId, ?string $customerId = null): void
    {
        $this->forceFill([
            'access_status' => 'active',
            'stripe_customer_id' => $customerId ?: $this->stripe_customer_id,
            'stripe_subscription_id' => $subscriptionId,
            'billing_active_at' => now(),
            'billing_ends_at' => null,
        ])->save();
    }
}
