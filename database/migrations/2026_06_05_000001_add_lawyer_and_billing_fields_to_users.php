<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('company')->nullable()->after('name');
            $table->string('phone', 40)->nullable()->after('company');
            $table->string('bar')->nullable()->after('phone');
            $table->string('access_status')->default('pending_payment')->index()->after('password');
            $table->string('stripe_customer_id')->nullable()->index()->after('access_status');
            $table->string('stripe_subscription_id')->nullable()->index()->after('stripe_customer_id');
            $table->timestamp('trial_ends_at')->nullable()->after('stripe_subscription_id');
            $table->timestamp('billing_active_at')->nullable()->after('trial_ends_at');
            $table->timestamp('billing_ends_at')->nullable()->after('billing_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'company',
                'phone',
                'bar',
                'access_status',
                'stripe_customer_id',
                'stripe_subscription_id',
                'trial_ends_at',
                'billing_active_at',
                'billing_ends_at',
            ]);
        });
    }
};
