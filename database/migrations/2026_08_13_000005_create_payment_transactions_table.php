<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores every Stripe PaymentIntent created for a legal aid request.
     * `amount_cents` is the integer amount in the smallest currency unit
     * (MAD is a 2-decimal currency, so 1 MAD = 100 cents).
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_aid_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('stripe_payment_intent_id', 64)->unique();
            $table->string('stripe_payment_method_id', 64)->nullable();
            $table->string('currency', 3)->default('mad');
            $table->string('country', 2)->default('MA');
            $table->unsignedBigInteger('amount_cents');
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('requires_payment_method');
            $table->string('payment_method_type', 30)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['legal_aid_request_id', 'status']);
            $table->index(['stripe_payment_intent_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
