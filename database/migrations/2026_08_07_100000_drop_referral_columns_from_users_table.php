<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by')) {
                $table->dropForeign(['referred_by']);
                $table->dropColumn('referred_by');
            }

            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropUnique('users_referral_code_unique');
                $table->dropColumn('referral_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 8)->nullable()->unique()->after('bar');
            $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
        });
    }
};
