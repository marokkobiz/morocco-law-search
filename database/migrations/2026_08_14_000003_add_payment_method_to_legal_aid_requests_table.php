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
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('google_pay')->after('consultation_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
