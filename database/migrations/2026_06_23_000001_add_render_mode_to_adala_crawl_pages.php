<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('adala_crawl_pages', 'render_mode')) {
            Schema::table('adala_crawl_pages', function (Blueprint $table): void {
                $table->string('render_mode', 20)->nullable()->after('pdfs_found');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('adala_crawl_pages', 'render_mode')) {
            Schema::table('adala_crawl_pages', function (Blueprint $table): void {
                $table->dropColumn('render_mode');
            });
        }
    }
};
