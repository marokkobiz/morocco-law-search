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
        Schema::create('legal_aid_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 10)->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('whatsapp', 40)->nullable();
            $table->text('case_description');
            $table->string('status', 20)->default('pending_payment');
            $table->string('locale', 10)->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_aid_requests');
    }
};
