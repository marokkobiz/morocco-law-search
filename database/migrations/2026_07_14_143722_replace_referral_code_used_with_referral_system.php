<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Safely drop old column if it exists
            if (Schema::hasColumn('users', 'referral_code_used')) {
                $table->dropColumn('referral_code_used');
            }

            // Only create referral_code if base migration didn't already create it
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 8)
                    ->nullable()
                    ->unique()
                    ->after('bar');
            }

            // Only create referred_by if base migration didn't already create it
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->foreignId('referred_by')
                    ->nullable()
                    ->after('referral_code')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by')) {
                $table->dropForeign(['referred_by']);
                $table->dropColumn('referred_by');
            }

            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }

            if (!Schema::hasColumn('users', 'referral_code_used')) {
                $table->string('referral_code_used')
                    ->nullable()
                    ->after('bar');
            }
        });
    }
};
