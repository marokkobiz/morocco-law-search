<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('stripe_product_id', 64)->nullable()->after('allows_whatsapp')->index();
            $table->string('stripe_price_id', 64)->nullable()->after('stripe_product_id')->index();
            $table->boolean('is_active')->default(true)->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['stripe_product_id', 'stripe_price_id', 'is_active']);
        });
    }
};
