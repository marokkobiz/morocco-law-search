<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (used in tests) does not enforce VARCHAR length and does not
        // support MODIFY. Only run the MySQL-specific alter on MySQL.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Expand stripe_payment_intent_id from 64 to 128 to safely store Checkout
        // Session ids (cs_...) which are ~66 chars and prefixed placeholders.
        // Use raw SQL to avoid requiring doctrine/dbal.
        DB::statement('ALTER TABLE payment_transactions MODIFY stripe_payment_intent_id VARCHAR(128) NOT NULL');
        DB::statement('ALTER TABLE payment_transactions MODIFY stripe_checkout_session_id VARCHAR(128) NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE payment_transactions MODIFY stripe_payment_intent_id VARCHAR(64) NOT NULL');
        DB::statement('ALTER TABLE payment_transactions MODIFY stripe_checkout_session_id VARCHAR(128) NULL');
    }
};
