<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('article_number', 50);
            $table->longText('text');
            $table->integer('sort_key');
            $table->string('path', 500)->nullable();
            $table->string('slug', 500)->nullable();
            $table->string('chapter', 500)->nullable();
            $table->integer('depth')->nullable();
            $table->integer('page')->nullable();
            $table->json('footnotes')->nullable();
            $table->timestamps();

            $table->index('article_number');
            $table->index(['document_id', 'sort_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
