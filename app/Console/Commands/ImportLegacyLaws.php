<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportLegacyLaws extends Command
{
    protected $signature = 'laws:import-legacy
        {--limit= : Maximum number of law rows to import}
        {--skip-translations : Import only laws and skip cached translations}';

    protected $description = 'Import laws and cached translations from the existing Node/MySQL database into Laravel.';

    public function handle(): int
    {
        $connectionName = 'legacy_mysql';
        $this->configureLegacyConnection($connectionName);

        try {
            if (!Schema::connection($connectionName)->hasTable('laws')) {
                $this->error('Legacy table "laws" was not found. Check LEGACY_DB_* values in .env.');

                return self::FAILURE;
            }

            $importedLaws = $this->importLaws($connectionName);
            $this->info("Imported or updated {$importedLaws} law rows.");

            if (!$this->option('skip-translations') && Schema::connection($connectionName)->hasTable('law_translations')) {
                $importedTranslations = $this->importTranslations($connectionName);
                $this->info("Imported or updated {$importedTranslations} translation rows.");
            }

            Cache::flush();

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } finally {
            DB::purge($connectionName);
        }
    }

    private function configureLegacyConnection(string $connectionName): void
    {
        config([
            "database.connections.{$connectionName}" => [
                'driver' => 'mysql',
                'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
                'port' => env('LEGACY_DB_PORT', '3306'),
                'database' => env('LEGACY_DB_DATABASE', 'morocco_law_search'),
                'username' => env('LEGACY_DB_USERNAME', 'root'),
                'password' => env('LEGACY_DB_PASSWORD', ''),
                'unix_socket' => env('LEGACY_DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ],
        ]);

        DB::purge($connectionName);
    }

    private function importLaws(string $connectionName): int
    {
        $limit = $this->normalizedLimit();
        $count = 0;
        $query = DB::connection($connectionName)
            ->table('laws')
            ->orderBy('id');

        if ($limit) {
            $query->limit($limit);
            $query->get()->chunk(500)->each(function (Collection $rows) use (&$count): void {
                $count += $this->upsertLawRows($rows);
            });

            return $count;
        }

        $query->chunkById(500, function (Collection $rows) use (&$count): void {
            $count += $this->upsertLawRows($rows);
        });

        return $count;
    }

    private function upsertLawRows(Collection $rows): int
    {
        $payload = $rows->map(fn (object $row) => [
            'id' => $row->id,
            'title' => $row->title,
            'article_number' => $row->article_number,
            'content' => $row->content,
            'tags' => $row->tags,
            'document_title' => $row->document_title,
            'law_reference' => $row->law_reference,
            'category' => $row->category,
            'source_name' => $row->source_name,
            'source_url' => $row->source_url,
            'language' => $row->language ?? 'fr',
            'imported_at' => $row->imported_at ?? Carbon::now(),
        ])->all();

        DB::table('laws')->upsert(
            $payload,
            ['id'],
            ['title', 'article_number', 'content', 'tags', 'document_title', 'law_reference', 'category', 'source_name', 'source_url', 'language', 'imported_at']
        );

        return count($payload);
    }

    private function importTranslations(string $connectionName): int
    {
        $count = 0;
        $existingLawIds = array_flip(
            DB::table('laws')
                ->pluck('id')
                ->map(fn (int|string $id) => (int) $id)
                ->all()
        );

        DB::connection($connectionName)
            ->table('law_translations')
            ->orderBy('id')
            ->chunkById(500, function (Collection $rows) use (&$count, $existingLawIds): void {
                $count += $this->upsertTranslationRows($rows, $existingLawIds);
            });

        return $count;
    }

    private function upsertTranslationRows(Collection $rows, array $existingLawIds): int
    {
        $payload = $rows
            ->filter(fn (object $row) => isset($existingLawIds[(int) $row->law_id]))
            ->map(fn (object $row) => [
                'id' => $row->id,
                'law_id' => $row->law_id,
                'source_language' => $row->source_language,
                'target_language' => $row->target_language,
                'translated_title' => $row->translated_title,
                'translated_content' => $row->translated_content,
                'provider' => $row->provider,
                'created_at' => $row->created_at ?? Carbon::now(),
                'updated_at' => $row->updated_at ?? Carbon::now(),
            ])
            ->values()
            ->all();

        if (!$payload) {
            return 0;
        }

        DB::table('law_translations')->upsert(
            $payload,
            ['id'],
            ['law_id', 'source_language', 'target_language', 'translated_title', 'translated_content', 'provider', 'created_at', 'updated_at']
        );

        return count($payload);
    }

    private function normalizedLimit(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }
}
