<?php

namespace App\Services;

use App\Models\ImportRun;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

class OfficialSourceUpdateService
{
    public function __construct(
        private readonly OfficialBulletinUpdateService $bulletins,
        private readonly LegacyLawCorpusImportService $corpusImporter,
    ) {
    }

    public function supportedSources(): array
    {
        return [
            'official-bulletins' => [
                'name' => 'Secretariat General du Gouvernement - Bulletin officiel',
                'sourceType' => 'BO',
                'baseUrl' => OfficialBulletinUpdateService::BASE_URL,
                'commandSource' => 'official-bulletins',
            ],
        ];
    }

    public function update(array $options = []): array
    {
        $source = (string) ($options['source'] ?? 'all');
        $this->ensureSupportedSource($source);

        $run = ImportRun::query()->create([
            'import_type' => 'official_sources_update',
            'source_url' => $source === 'all' ? 'official:all' : $this->sourceUrlFor($source),
            'started_at' => Carbon::now(),
            'status' => 'running',
            'metadata' => [
                'source' => $source,
                'supported_sources' => array_keys($this->supportedSources()),
                'options' => $this->cleanOptions($options),
            ],
        ]);

        try {
            $summary = $this->updateSources($source, $options);
            $sourceUrls = collect($summary['sources'] ?? [])
                ->pluck('sourceUrl')
                ->filter()
                ->unique()
                ->values()
                ->all();
            $corpusSummary = $sourceUrls
                ? $this->corpusImporter->import(null, $sourceUrls)
                : [
                    'documentsImported' => 0,
                    'articlesExtracted' => 0,
                    'chunksCreated' => 0,
                    'skippedVersions' => 0,
                    'errors' => [],
                    'importRunId' => null,
                ];
            $errors = array_values(array_filter([
                ...($summary['failures'] ?? []),
                ...($corpusSummary['errors'] ?? []),
            ]));

            $run->update([
                'finished_at' => Carbon::now(),
                'status' => $errors ? 'completed_with_errors' : 'completed',
                'documents_imported' => (int) ($corpusSummary['documentsImported'] ?? 0),
                'articles_extracted' => (int) ($summary['importedArticleCount'] ?? 0),
                'chunks_created' => (int) ($corpusSummary['chunksCreated'] ?? 0),
                'errors' => $errors,
                'metadata' => array_merge($run->metadata ?? [], [
                    'official_update' => $summary,
                    'corpus_import' => $corpusSummary,
                    'source_urls' => $sourceUrls,
                ]),
            ]);

            return array_merge($summary, [
                'importRunId' => $run->id,
                'source' => $source,
                'supportedSources' => $this->supportedSources(),
                'sourceUrls' => $sourceUrls,
                'corpus' => $corpusSummary,
                'errors' => $errors,
            ]);
        } catch (Throwable $error) {
            $run->update([
                'finished_at' => Carbon::now(),
                'status' => 'failed',
                'errors' => [['message' => $error->getMessage()]],
            ]);

            throw $error;
        }
    }

    private function updateSources(string $source, array $options): array
    {
        if ($source === 'all' || $source === 'official-bulletins') {
            return $this->bulletins->update($options);
        }

        throw new InvalidArgumentException("Unsupported official source: {$source}");
    }

    private function ensureSupportedSource(string $source): void
    {
        if ($source === 'all' || array_key_exists($source, $this->supportedSources())) {
            return;
        }

        throw new InvalidArgumentException('Supported sources are: all, '.implode(', ', array_keys($this->supportedSources())).'.');
    }

    private function sourceUrlFor(string $source): string
    {
        return match ($source) {
            'official-bulletins' => OfficialBulletinUpdateService::BASE_URL,
            default => 'official:'.$source,
        };
    }

    private function cleanOptions(array $options): array
    {
        return collect($options)
            ->except(['pdfContent', 'text'])
            ->all();
    }
}
