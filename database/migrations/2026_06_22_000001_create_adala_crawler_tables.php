<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adala_crawl_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 30)->default('pending')->index();
            $table->json('seed_urls')->nullable();
            $table->unsignedInteger('documents_discovered')->default(0);
            $table->unsignedInteger('documents_completed')->default(0);
            $table->unsignedInteger('documents_failed')->default(0);
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('adala_crawl_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adala_crawl_run_id')->constrained('adala_crawl_runs')->cascadeOnDelete();
            $table->string('page_url', 2048);
            $table->string('url_hash', 64);
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('pdfs_found')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->unique(['adala_crawl_run_id', 'url_hash'], 'uniq_adala_run_page_hash');
            $table->index(['adala_crawl_run_id', 'status'], 'idx_adala_run_page_status');
        });

        Schema::create('adala_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adala_crawl_run_id')->constrained('adala_crawl_runs')->cascadeOnDelete();
            $table->string('source_url', 2048);
            $table->string('normalized_url', 2048);
            $table->string('url_hash', 64)->unique();
            $table->string('title')->nullable();
            $table->string('language', 10)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('document_type', 100)->nullable();
            $table->date('publication_date')->nullable();
            $table->string('local_path', 1024)->nullable();
            $table->string('file_checksum', 64)->nullable()->index();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('status', 30)->default('discovered')->index();
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('last_attempt_at')->nullable()->index();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->unsignedInteger('processing_duration_ms')->nullable();
            $table->unsignedInteger('laws_imported_count')->default(0);
            $table->unsignedInteger('chunks_created')->default(0);
            $table->unsignedInteger('chunks_embedded')->default(0);
            $table->unsignedInteger('chunks_vectorized')->default(0);
            $table->foreignId('legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['adala_crawl_run_id', 'status'], 'idx_adala_run_document_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adala_documents');
        Schema::dropIfExists('adala_crawl_pages');
        Schema::dropIfExists('adala_crawl_runs');
    }
};
