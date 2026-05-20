<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class CreateTutorial extends Command
{
    protected $signature = 'tutorials:create
        {file : Path to the .draft.html file (absolute, or relative to storage/{--path})}
        {--name= : Article title (required, must be unique within the collection)}
        {--collection= : Collection name or slug (falls back to config helpscout-tutorials.default_collection)}
        {--status=published : Initial status (published or notpublished)}
        {--slug= : Optional slug (HelpScout generates one from the name if omitted)}
        {--path= : Subdirectory under storage_path() containing the manifest (defaults to config helpscout-tutorials.storage_path)}
        {--dry-run : Print what would be sent without making the API call}';

    protected $description = 'Create a new HelpScout Docs article from a local *.draft.html file.';

    public function handle(HelpScoutDocsClient $client): int
    {
        $name = (string) ($this->option('name') ?? '');
        $collectionName = (string) ($this->option('collection') ?: config('helpscout-tutorials.default_collection', ''));
        $status = (string) ($this->option('status') ?? 'published');

        if ($name === '') {
            $this->error('--name is required.');

            return self::FAILURE;
        }

        if ($collectionName === '') {
            $this->error('--collection is required (or set helpscout-tutorials.default_collection in config).');

            return self::FAILURE;
        }

        if (! in_array($status, ['published', 'notpublished'], true)) {
            $this->error("Invalid --status value '{$status}'. Must be 'published' or 'notpublished'.");

            return self::FAILURE;
        }

        $resolvedFile = $this->resolveFilePath($this->argument('file'));

        if (! File::exists($resolvedFile)) {
            $this->error("Draft file not found: {$resolvedFile}");

            return self::FAILURE;
        }

        $manifestPath = storage_path($this->resolveStoragePath().'/manifest.json');

        if (! File::exists($manifestPath)) {
            $this->error("Manifest not found at {$manifestPath}. Run tutorials:download first so the collection ID can be resolved.");

            return self::FAILURE;
        }

        $collectionId = $this->resolveCollectionId(
            json_decode(File::get($manifestPath), true) ?? [],
            $collectionName,
        );

        if ($collectionId === null) {
            $this->error("Collection '{$collectionName}' not found in manifest. Try running tutorials:download.");

            return self::FAILURE;
        }

        $text = File::get($resolvedFile);

        $payload = [
            'collectionId' => $collectionId,
            'name' => $name,
            'text' => $text,
            'status' => $status,
        ];

        if ($slug = $this->option('slug')) {
            $payload['slug'] = $slug;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would POST /articles with name='{$name}', collectionId={$collectionId}, ".strlen($text)." bytes of text, status={$status}.");

            return self::SUCCESS;
        }

        $created = $client->createArticle($payload);

        $newId = $created['id'] ?? '(unknown)';
        $this->info("Created article {$newId} ({$name}) in collection {$collectionName}.");
        $this->info('Re-run tutorials:download to refresh the local manifest so future edits can use tutorials:publish.');

        return self::SUCCESS;
    }

    private function resolveFilePath(string $file): string
    {
        if (str_starts_with($file, '/')) {
            return $file;
        }

        return storage_path($this->resolveStoragePath().'/'.$file);
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
