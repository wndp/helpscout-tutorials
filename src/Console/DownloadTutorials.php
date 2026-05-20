<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class DownloadTutorials extends Command
{
    protected $signature = 'tutorials:download
        {--path= : Subdirectory under storage_path() to write to (defaults to config helpscout-tutorials.storage_path)}
        {--collection=* : Filter to collections whose name or slug matches these values (case-insensitive). Repeat the flag for multiple values. If omitted, falls back to config helpscout-tutorials.default_collection or downloads every collection.}';

    protected $description = 'Download all published HelpScout Docs tutorials into local storage for review.';

    public function handle(HelpScoutDocsClient $client): int
    {
        $root = storage_path($this->resolveStoragePath());
        File::ensureDirectoryExists($root);

        $manifest = [
            'downloaded_at' => now()->toIso8601String(),
            'collections' => [],
        ];

        $totalArticles = 0;
        $filters = array_map('strtolower', $this->resolveCollectionFilters());

        foreach ($client->collections() as $collection) {
            if (! $this->collectionMatches($collection, $filters)) {
                continue;
            }

            $collectionSlug = $this->safeSlug($collection['slug'] ?? $collection['name'] ?? $collection['id']);
            $collectionDir = $root.DIRECTORY_SEPARATOR.$collectionSlug;
            File::ensureDirectoryExists($collectionDir);

            $this->line("Collection: {$collection['name']}");

            $articles = [];

            foreach ($client->articlesInCollection($collection['id']) as $articleRef) {
                $full = $client->getArticle($articleRef['id']);

                $articleSlug = $this->safeSlug($full['slug'] ?? $articleRef['slug'] ?? $full['id']);
                $filename = $articleSlug.'.original.html';
                $relativePath = $collectionSlug.'/'.$filename;

                File::put($collectionDir.DIRECTORY_SEPARATOR.$filename, (string) ($full['text'] ?? ''));

                $articles[] = [
                    'id' => $full['id'],
                    'number' => $full['number'] ?? null,
                    'name' => $full['name'] ?? null,
                    'slug' => $full['slug'] ?? null,
                    'status' => $full['status'] ?? null,
                    'updated_at' => $full['updatedAt'] ?? null,
                    'public_url' => $full['publicUrl'] ?? null,
                    'original_hash' => hash('sha256', (string) ($full['text'] ?? '')),
                    'file' => $relativePath,
                ];

                $totalArticles++;
                $this->line("  - {$full['name']}");
            }

            $manifest['collections'][] = [
                'id' => $collection['id'],
                'name' => $collection['name'] ?? null,
                'slug' => $collection['slug'] ?? null,
                'articles' => $articles,
            ];
        }

        File::put(
            $root.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $this->info("Downloaded {$totalArticles} articles across ".count($manifest['collections']).' collection(s).');
        $this->info("Manifest: {$root}/manifest.json");

        return self::SUCCESS;
    }

    private function safeSlug(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'untitled';
    }

    /**
     * @param  array<string, mixed>  $collection
     * @param  array<int, string>  $filters
     */
    private function collectionMatches(array $collection, array $filters): bool
    {
        if (empty($filters)) {
            return true;
        }

        $name = strtolower((string) ($collection['name'] ?? ''));
        $slug = strtolower((string) ($collection['slug'] ?? ''));

        foreach ($filters as $filter) {
            if ($filter === $name || $filter === $slug) {
                return true;
            }
        }

        return false;
    }

    private function resolveStoragePath(): string
    {
        return (string) ($this->option('path') ?: config('helpscout-tutorials.storage_path', 'helpscout-tutorials'));
    }

    /**
     * @return array<int, string>
     */
    private function resolveCollectionFilters(): array
    {
        $filters = (array) $this->option('collection');

        if (! empty($filters)) {
            return $filters;
        }

        $default = config('helpscout-tutorials.default_collection');

        return $default ? [$default] : [];
    }
}
