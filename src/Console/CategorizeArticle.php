<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class CategorizeArticle extends Command
{
    protected $signature = 'tutorials:categorize
        {article_id : HelpScout article ID to categorize}
        {--category=* : Category name, slug, or ID to assign. Repeat the flag for multiple. The article is set to exactly these categories, replacing any existing ones.}
        {--path= : Subdirectory under storage_path() containing the manifest (defaults to config helpscout-tutorials.storage_path)}
        {--dry-run : Print what would be sent without making the API call}';

    protected $description = 'Set the categories an article belongs to within its collection (replaces existing).';

    public function handle(HelpScoutDocsClient $client): int
    {
        $requested = array_values(array_filter((array) $this->option('category'), fn ($v) => $v !== null && $v !== ''));

        if ($requested === []) {
            $this->error('At least one --category is required.');

            return self::FAILURE;
        }

        $manifestPath = storage_path($this->resolveStoragePath().'/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error("Manifest not found at {$manifestPath}. Run tutorials:download first.");

            return self::FAILURE;
        }

        $articleId = (string) $this->argument('article_id');
        $located = $this->locateArticle(json_decode(File::get($manifestPath), true) ?? [], $articleId);

        if ($located === null) {
            $this->error("Article {$articleId} not found in manifest.");

            return self::FAILURE;
        }

        [$collectionId, $article] = $located;

        $categories = iterator_to_array($client->categories($collectionId), false);
        $resolved = $this->resolveCategoryIds($requested, $categories);

        if ($resolved === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would PUT /articles/{$articleId} with categories=[".implode(', ', $resolved).'].');

            return self::SUCCESS;
        }

        $client->updateArticle($articleId, ['categories' => $resolved]);

        $this->info("Set categories for article {$articleId} ({$article['name']}) to ".count($resolved).' categor'.(count($resolved) === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }

    /**
     * Resolve the requested name/slug/ID values to concrete category IDs.
     *
     * @param  array<int, string>  $requested
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, string>|null Null signals an unresolved value.
     */
    private function resolveCategoryIds(array $requested, array $categories): ?array
    {
        $ids = [];

        foreach ($requested as $value) {
            $needle = strtolower($value);
            $match = null;

            foreach ($categories as $category) {
                $id = (string) ($category['id'] ?? '');

                if ($needle === strtolower($id)
                    || $needle === strtolower((string) ($category['name'] ?? ''))
                    || $needle === strtolower((string) ($category['slug'] ?? ''))) {
                    $match = $id;
                    break;
                }
            }

            if ($match === null) {
                $this->error("Category '{$value}' not found in the article's collection. Run tutorials:categories to list valid categories.");

                return null;
            }

            if (! in_array($match, $ids, true)) {
                $ids[] = $match;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{0: string, 1: array<string, mixed>}|null [collectionId, article]
     */
    private function locateArticle(array $manifest, string $articleId): ?array
    {
        foreach ($manifest['collections'] ?? [] as $collection) {
            foreach ($collection['articles'] ?? [] as $article) {
                if (($article['id'] ?? null) === $articleId) {
                    return [(string) ($collection['id'] ?? ''), $article];
                }
            }
        }

        return null;
    }

    private function resolveStoragePath(): string
    {
        return (string) ($this->option('path') ?: config('helpscout-tutorials.storage_path', 'helpscout-tutorials'));
    }
}
