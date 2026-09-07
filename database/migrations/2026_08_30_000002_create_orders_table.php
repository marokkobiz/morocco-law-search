<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // CIN is now Ticket Number - stored verbatim, not unique to allow multiple orders per CIN
            $table->string('cin', 20);
            $table->string('ticket_number', 20);
            $table->string('email', 255);
            $table->string('full_name', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('currency', 10)->default('mad');
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('stripe_checkout_session_id', 128)->nullable()->unique();
            $table->string('stripe_payment_intent_id', 128)->nullable()->index();
            $table->string('locale', 10)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['cin', 'status']);
            $table->index(['email', 'status']);
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
