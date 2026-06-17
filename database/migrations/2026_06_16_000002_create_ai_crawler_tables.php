<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crawl_sessions') || Schema::hasTable('crawl_pages')) {
            return;
        }

        Schema::create('crawl_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('root_url', 1024);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('pages_discovered')->default(0);
            $table->unsignedInteger('pdfs_downloaded')->default(0);
            $table->unsignedInteger('laws_stored')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crawl_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('crawl_sessions')->cascadeOnDelete();
            $table->string('url', 1024);
            $table->string('url_hash', 64)->unique();
            $table->unsignedInteger('depth')->default(0);
            $table->string('content_type', 20)->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->longText('raw_text')->nullable();
            $table->json('ai_json')->nullable();
            $table->string('domain', 100)->nullable()->index();
            $table->string('status', 30)->default('discovered')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_pages');
        Schema::dropIfExists('crawl_sessions');
    }
};
