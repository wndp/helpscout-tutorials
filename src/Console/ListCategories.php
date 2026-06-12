<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class ListCategories extends Command
{
    protected $signature = 'tutorials:categories
        {collection : Collection name or slug (resolved via the local manifest)}
        {--path= : Subdirectory under storage_path() containing the manifest (defaults to config helpscout-tutorials.storage_path)}';

    protected $description = 'List the categories in a HelpScout Docs collection (with their IDs).';

    public function handle(HelpScoutDocsClient $client): int
    {
        $manifestPath = storage_path($this->resolveStoragePath().'/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error("Manifest not found at {$manifestPath}. Run tutorials:download first so the collection ID can be resolved.");

            return self::FAILURE;
        }

        $collectionName = (string) $this->argument('collection');
        $collectionId = $this->resolveCollectionId(
            json_decode(File::get($manifestPath), true) ?? [],
            $collectionName,
        );

        if ($collectionId === null) {
            $this->error("Collection '{$collectionName}' not found in manifest. Try running tutorials:download.");

            return self::FAILURE;
        }

        $rows = [];

        foreach ($client->categories($collectionId) as $category) {
            $rows[] = [
                $category['id'] ?? '',
                $category['name'] ?? '',
                $category['slug'] ?? '',
                $category['visibility'] ?? '',
                $category['articleCount'] ?? '',
            ];
        }

        if ($rows === []) {
            $this->info("Collection '{$collectionName}' has no categories.");

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Slug', 'Visibility', 'Articles'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function resolveCollectionId(array $manifest, string $collectionName): ?string
    {
        $needle = strtolower($collectionName);

        foreach ($manifest['collections'] ?? [] as $collection) {
            $name = strtolower((string) ($collection['name'] ?? ''));
            $slug = strtolower((string) ($collection['slug'] ?? ''));

            if ($needle === $name || $needle === $slug) {
                return $collection['id'] ?? null;
            }
        }

        return null;
    }

    private function resolveStoragePath(): string
    {
        return (string) ($this->option('path') ?: config('helpscout-tutorials.storage_path', 'helpscout-tutorials'));
    }
}
