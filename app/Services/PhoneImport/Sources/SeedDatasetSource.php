<?php

namespace App\Services\PhoneImport\Sources;

use App\Enums\SourceTypeEnum;
use Illuminate\Support\Facades\File;

/**
 * Reads the hand-curated bootstrap dataset from every *.json file in
 * database/seed-data/ (split into multiple files by brand group for
 * manageability - the pipeline doesn't care how the source data is
 * organized on disk, it just sees one merged list of raw records).
 * This is a manual/editorial source (not a live feed), used to give the
 * pipeline a real, working dataset to normalize/dedupe/score end-to-end.
 * See config/phone_sources.php for why every record from this source is
 * routed to the review queue rather than auto-approved.
 */
class SeedDatasetSource extends AbstractPhoneSource
{
    public function type(): SourceTypeEnum
    {
        return SourceTypeEnum::MANUAL;
    }

    public function fetchBatch(?array $cursor, int $limit): array
    {
        $items = $this->allItems();
        $offset = $cursor['offset'] ?? 0;

        $batch = array_slice($items, $offset, $limit);
        $nextOffset = $offset + count($batch);
        $done = $nextOffset >= count($items);

        return [
            'items' => $batch,
            'cursor' => ['offset' => $nextOffset],
            'done' => $done,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function allItems(): array
    {
        // Single-file override, kept for backward compatibility.
        if (! empty($this->config['file'])) {
            return $this->readFile($this->config['file']);
        }

        $directory = $this->config['directory'] ?? database_path('seed-data');

        if (! File::isDirectory($directory)) {
            return [];
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file) => $file->getExtension() === 'json')
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        return $files->flatMap(fn ($file) => $this->readFile($file->getPathname()))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function readFile(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode(File::get($path), true);

        return $decoded['phones'] ?? [];
    }
}
