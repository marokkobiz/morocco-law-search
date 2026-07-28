<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'referral_code_used')) {
                $table->dropColumn('referral_code_used');
            }

            $table->string('referral_code')
                ->nullable()
                ->unique()
                ->after('bar');

            $table->foreignId('referred_by')
                ->nullable()
                ->after('referral_code')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['referred_by']);

            $table->dropColumn([
                'referral_code',
                'referred_by',
            ]);

            $table->string('referral_code_used')
                ->nullable()
                ->after('bar');
        });
    }
};