<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            $table->binary('embedding_binary')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            $table->dropColumn('embedding_binary');
        });
    }
};
