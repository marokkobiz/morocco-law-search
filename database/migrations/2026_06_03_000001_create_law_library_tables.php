<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laws', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('article_number', 100);
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->string('document_title')->nullable();
            $table->string('law_reference', 100)->nullable();
            $table->string('category', 100)->nullable()->index();
            $table->string('source_name')->nullable();
            $table->string('source_url', 512)->nullable();
            $table->string('language', 10)->default('fr');
            $table->timestamp('imported_at')->useCurrent();

            $table->unique(['source_url', 'article_number'], 'uniq_law_source_article');
            $table->index('document_title', 'idx_laws_document_title');
        });

        Schema::create('law_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('law_id')->constrained('laws')->cascadeOnDelete();
            $table->string('source_language', 10);
            $table->string('target_language', 10);
            $table->text('translated_title');
            $table->longText('translated_content');
            $table->string('provider', 100)->nullable();
            $table->timestamps();

            $table->unique(['law_id', 'target_language'], 'uniq_law_translation');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE FULLTEXT INDEX ft_laws_search ON laws (title, document_title, law_reference, content)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('law_translations');
        Schema::dropIfExists('laws');
    }
};
