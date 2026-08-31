<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('service_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('legal_aid_requests', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
