<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('source_type', 40)->index();
            $table->string('source_url', 1024)->nullable();
            $table->string('official_domain')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('legal_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_source_id')->nullable()->constrained('legal_sources')->nullOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->string('document_title');
            $table->string('document_type', 40)->index();
            $table->string('law_reference', 150)->nullable()->index();
            $table->string('bo_number', 80)->nullable()->index();
            $table->date('publication_date')->nullable()->index();
            $table->date('effective_date')->nullable();
            $table->string('language', 10)->default('fr')->index();
            $table->string('domain', 100)->nullable()->index();
            $table->string('source_url', 1024)->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'status'], 'idx_legal_documents_type_status');
            $table->index(['domain', 'status'], 'idx_legal_documents_domain_status');
        });

        Schema::create('legal_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('source_url', 1024)->nullable();
            $table->string('source_file_path', 1024)->nullable();
            $table->string('checksum', 64)->index();
            $table->string('status', 30)->default('active')->index();
            $table->date('publication_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamp('imported_at')->nullable()->index();
            $table->longText('raw_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legal_document_id', 'version_number'], 'uniq_document_version_number');
            $table->unique(['legal_document_id', 'checksum'], 'uniq_document_version_checksum');
        });

        Schema::create('legal_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->cascadeOnDelete();
            $table->foreignId('legacy_law_id')->nullable()->constrained('laws')->nullOnDelete();
            $table->string('article_number', 100)->index();
            $table->string('article_title')->nullable();
            $table->longText('content');
            $table->string('language', 10)->default('fr');
            $table->string('checksum', 64)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['legal_document_version_id', 'article_number'], 'idx_version_article_number');
            $table->index(['legal_document_id', 'status'], 'idx_legal_articles_document_status');
        });

        Schema::create('legal_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_article_id')->constrained('legal_articles')->cascadeOnDelete();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->string('checksum', 64)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['legal_article_id', 'chunk_index'], 'uniq_article_chunk_index');
        });

        Schema::create('import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legal_source_id')->nullable()->constrained('legal_sources')->nullOnDelete();
            $table->string('import_type', 80)->index();
            $table->string('source_url', 1024)->nullable();
            $table->string('source_file_path', 1024)->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('documents_imported')->default(0);
            $table->unsignedInteger('articles_extracted')->default(0);
            $table->unsignedInteger('chunks_created')->default(0);
            $table->json('errors')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('document_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
            $table->foreignId('to_legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->foreignId('legal_document_version_id')->nullable()->constrained('legal_document_versions')->nullOnDelete();
            $table->string('relation_type', 40)->index();
            $table->date('relation_date')->nullable();
            $table->text('details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
        Schema::dropIfExists('import_runs');
        Schema::dropIfExists('legal_chunks');
        Schema::dropIfExists('legal_articles');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('legal_sources');
    }
};
