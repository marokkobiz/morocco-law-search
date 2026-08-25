<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id', 128)->nullable()->after('stripe_payment_intent_id')->index();
        });

        // Allow checkout sessions to reuse the intent column (cs_* vs pi_*) by making it nullable
        // The existing column is unique; keep it but allow null for checkout placeholder rows if needed.
        // No change to unique constraint — we keep it unique for pi_ ids.
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('stripe_checkout_session_id');
        });
    }
};
