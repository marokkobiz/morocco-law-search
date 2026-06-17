<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('crawler_fingerprints');
        Schema::dropIfExists('crawl_logs');
        Schema::dropIfExists('crawler_sources');
    }

    public function down(): void
    {
    }
};
