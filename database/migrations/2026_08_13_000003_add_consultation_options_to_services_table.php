<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('allows_office')->default(false)->after('additional_notes_ar');
            $table->boolean('allows_whatsapp')->default(false)->after('allows_office');
        });

        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->string('consultation_mode', 20)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->string('consultation_mode', 20)->default('office')->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['allows_office', 'allows_whatsapp']);
        });
    }
};
