<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('whatsapp', 40)->nullable()->after('phone');
            $table->text('case_description')->nullable()->after('full_name');
            $table->string('call_time', 20)->nullable()->after('case_description');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['phone', 'whatsapp', 'case_description', 'call_time']);
        });
    }
};
