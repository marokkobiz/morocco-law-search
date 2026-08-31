<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('stripe_price_id', 64);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->timestamps();

            $table->index(['order_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
