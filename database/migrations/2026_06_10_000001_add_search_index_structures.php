<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            $table->binary('embedding_packed')->nullable();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS legal_chunks_fts');
            DB::statement(<<<'SQL'
                CREATE VIRTUAL TABLE legal_chunks_fts USING fts5(
                    chunk_id UNINDEXED,
                    chat_only UNINDEXED,
                    document_title,
                    article_title,
                    content,
                    tags,
                    tokenize = 'unicode61'
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            $table->dropColumn('embedding_packed');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS legal_chunks_fts');
        }
    }
};
