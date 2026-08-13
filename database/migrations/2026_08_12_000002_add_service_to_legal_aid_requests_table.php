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
            $table->foreignId('service_id')
                ->nullable()
                ->after('case_description')
                ->constrained()
                ->nullOnDelete();
            $table->decimal('base_price', 10, 2)->nullable()->after('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('base_price');
        });
    }
};
