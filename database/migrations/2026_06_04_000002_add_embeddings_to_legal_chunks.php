<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            if (!Schema::hasColumn('legal_chunks', 'embedding')) {
                $table->json('embedding')->nullable()->after('metadata');
            }

            if (!Schema::hasColumn('legal_chunks', 'embedding_model')) {
                $table->string('embedding_model', 120)->nullable()->index()->after('embedding');
            }

            if (!Schema::hasColumn('legal_chunks', 'embedding_checksum')) {
                $table->string('embedding_checksum', 64)->nullable()->index()->after('embedding_model');
            }

            if (!Schema::hasColumn('legal_chunks', 'embedded_at')) {
                $table->timestamp('embedded_at')->nullable()->index()->after('embedding_checksum');
            }
        });
    }

    public function down(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            foreach (['embedded_at', 'embedding_checksum', 'embedding_model', 'embedding'] as $column) {
                if (Schema::hasColumn('legal_chunks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
