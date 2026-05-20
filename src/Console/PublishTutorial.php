<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class PublishTutorial extends Command
{
    protected $signature = 'tutorials:publish
        {article_id : HelpScout article ID to publish}
        {--path= : Subdirectory under storage_path() containing the manifest (defaults to config helpscout-tutorials.storage_path)}
        {--name= : Optional new name to send with the update}
        {--status= : Optional status to send with the update (published or notpublished)}
        {--dry-run : Print what would be sent without making the API call}';

    protected $description = 'Publish a single article\'s *.proposed.html back to HelpScout Docs.';

    public function handle(HelpScoutDocsClient $client): int
    {
        $root = storage_path($this->resolveStoragePath());
        $manifestPath = $root.DIRECTORY_SEPARATOR.'manifest.json';

        if (! File::exists($manifestPath)) {
            $this->error("Manifest not found at {$manifestPath}. Run tutorials:download first.");

            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $articleId = $this->argument('article_id');
        $entry = $this->findArticle($manifest, $articleId);

        if (! $entry) {
            $this->error("Article {$articleId} not found in manifest.");

            return self::FAILURE;
        }

        $status = $this->option('status');

        if ($status !== null && ! in_array($status, ['published', 'notpublished'], true)) {
            $this->error("Invalid --status value '{$status}'. Must be 'published' or 'notpublished'.");

            return self::FAILURE;
        }

        $originalPath = $root.DIRECTORY_SEPARATOR.$entry['file'];
        $proposedPath = str_replace('.original.html', '.proposed.html', $originalPath);
        $proposedText = File::exists($proposedPath) ? File::get($proposedPath) : null;
        $statusOnly = $status !== null && $proposedText === null;

        if ($proposedText === null && $status === null) {
            $this->error("Proposed file not found: {$proposedPath}");

            return self::FAILURE;
        }

        $payload = [];

        if ($proposedText !== null) {
            $payload['text'] = $proposedText;
        }

        if ($status !== null) {
            $payload['status'] = $status;
        }

        if ($name = $this->option('name')) {
            $payload['name'] = $name;
        }

        if ($this->option('dry-run')) {
            $summary = $statusOnly
                ? "status='{$status}' (no text)"
                : strlen($proposedText).' bytes of text'.($status ? " + status='{$status}'" : '');

            $this->info("[dry-run] Would PUT /articles/{$articleId} with {$summary}.");

            return self::SUCCESS;
        }

        $updated = $client->updateArticle($articleId, $payload);

        $this->updateManifest($manifestPath, $manifest, $articleId, $updated, $proposedText, $status);

        if ($statusOnly && $status === 'notpublished') {
            $this->info("Unpublished article {$articleId} ({$entry['name']}).");
        } else {
            $this->info("Published article {$articleId} ({$entry['name']}).");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null
     */
    private function findArticle(array $manifest, string $articleId): ?array
    {
        foreach ($manifest['collections'] ?? [] as $collection) {
            foreach ($collection['articles'] ?? [] as $article) {
                if (($article['id'] ?? null) === $articleId) {
                    return $article;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $updated
     */
    private function updateManifest(string $manifestPath, array $manifest, string $articleId, array $updated, ?string $publishedText, ?string $status): void
    {
        foreach ($manifest['collections'] as $cIndex => $collection) {
            foreach ($collection['articles'] as $aIndex => $article) {
                if (($article['id'] ?? null) !== $articleId) {
                    continue;
                }

                $manifest['collections'][$cIndex]['articles'][$aIndex]['updated_at'] = $updated['updatedAt'] ?? $article['updated_at'] ?? null;

                if ($publishedText !== null) {
                    $manifest['collections'][$cIndex]['articles'][$aIndex]['original_hash'] = hash('sha256', $publishedText);
                }

                if ($status !== null) {
                    $manifest['collections'][$cIndex]['articles'][$aIndex]['status'] = $status;
                }

                $manifest['collections'][$cIndex]['articles'][$aIndex]['last_published_at'] = now()->toIso8601String();
            }
        }

        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function resolveStoragePath(): string
    {
        return (string) ($this->option('path') ?: config('helpscout-tutorials.storage_path', 'helpscout-tutorials'));
    }
}
