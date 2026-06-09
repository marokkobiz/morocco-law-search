<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table): void {
            if (!Schema::hasColumn('legal_documents', 'subdomain')) {
                $table->string('subdomain', 120)->nullable()->after('domain')->index();
            }

            if (!Schema::hasColumn('legal_documents', 'tags')) {
                $table->json('tags')->nullable()->after('subdomain');
            }
        });

        Schema::table('legal_articles', function (Blueprint $table): void {
            if (!Schema::hasColumn('legal_articles', 'domain')) {
                $table->string('domain', 100)->nullable()->after('language')->index();
            }

            if (!Schema::hasColumn('legal_articles', 'subdomain')) {
                $table->string('subdomain', 120)->nullable()->after('domain')->index();
            }

            if (!Schema::hasColumn('legal_articles', 'tags')) {
                $table->json('tags')->nullable()->after('subdomain');
            }
        });

        Schema::table('legal_chunks', function (Blueprint $table): void {
            if (!Schema::hasColumn('legal_chunks', 'domain')) {
                $table->string('domain', 100)->nullable()->after('token_count')->index();
            }

            if (!Schema::hasColumn('legal_chunks', 'subdomain')) {
                $table->string('subdomain', 120)->nullable()->after('domain')->index();
            }

            if (!Schema::hasColumn('legal_chunks', 'tags')) {
                $table->json('tags')->nullable()->after('subdomain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('legal_chunks', function (Blueprint $table): void {
            foreach (['tags', 'subdomain', 'domain'] as $column) {
                if (Schema::hasColumn('legal_chunks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('legal_articles', function (Blueprint $table): void {
            foreach (['tags', 'subdomain', 'domain'] as $column) {
                if (Schema::hasColumn('legal_articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('legal_documents', function (Blueprint $table): void {
            foreach (['tags', 'subdomain'] as $column) {
                if (Schema::hasColumn('legal_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
