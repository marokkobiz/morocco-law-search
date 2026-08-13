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
            $table->string('ticket_pdf_path')->nullable()->after('receipt_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropColumn('ticket_pdf_path');
        });
    }
};
