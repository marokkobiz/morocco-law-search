<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->string('call_time', 20)->nullable()->after('consultation_mode');
        });
    }

    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropColumn('call_time');
        });
    }
};
