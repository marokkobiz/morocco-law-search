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
            $table->string('consultation_mode', 20)->default('office')->after('case_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropColumn('consultation_mode');
        });
    }
};
