<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;

class ManageCategory extends Command
{
    protected $signature = 'tutorials:category
        {action : One of create, update, delete}
        {--id= : Category ID (required for update and delete)}
        {--collection= : Collection name or slug for create (falls back to config helpscout-tutorials.default_collection; resolved via the local manifest)}
        {--name= : Category name (required for create; optional new name for update)}
        {--slug= : Optional SEO-friendly slug}
        {--visibility= : public or private}
        {--order= : Integer display order}
        {--default-sort= : popularity or name}
        {--path= : Subdirectory under storage_path() containing the manifest (defaults to config helpscout-tutorials.storage_path)}
        {--dry-run : Print what would be sent without making the API call}';

    protected $description = 'Create, update, or delete a HelpScout Docs category.';

    public function handle(HelpScoutDocsClient $client): int
    {
        return match ($this->argument('action')) {
            'create' => $this->create($client),
            'update' => $this->update($client),
            'delete' => $this->delete($client),
            default => $this->invalidAction(),
        };
    }

    private function create(HelpScoutDocsClient $client): int
    {
        $name = (string) ($this->option('name') ?? '');
        $collectionName = (string) ($this->option('collection') ?: config('helpscout-tutorials.default_collection', ''));

        if ($name === '') {
            $this->error('--name is required for create.');

            return self::FAILURE;
        }

        if ($collectionName === '') {
            $this->error('--collection is required for create (or set helpscout-tutorials.default_collection in config).');

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

        $optional = $this->optionalAttributes();

        if ($optional === null) {
            return self::FAILURE;
        }

        $payload = ['collectionId' => $collectionId, 'name' => $name, ...$optional];

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would POST /categories with name='{$name}', collectionId={$collectionId}.");

            return self::SUCCESS;
        }

        $created = $client->createCategory($payload);

        $this->info("Created category {$created['id']} ({$name}) in collection {$collectionName}.");

        return self::SUCCESS;
    }

    private function update(HelpScoutDocsClient $client): int
    {
        $id = (string) ($this->option('id') ?? '');

        if ($id === '') {
            $this->error('--id is required for update.');

            return self::FAILURE;
        }

        $optional = $this->optionalAttributes();

        if ($optional === null) {
            return self::FAILURE;
        }

        // The API requires `name` on every update, so fall back to the current
        // name when the caller is only changing other attributes.
        $name = $this->option('name');

        if ($name === null) {
            $current = $client->getCategory($id);
            $name = $current['name'] ?? null;

            if ($name === null) {
                $this->error("Could not determine the current name of category {$id}; pass --name explicitly.");

                return self::FAILURE;
            }
        }

        $payload = ['name' => (string) $name, ...$optional];

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would PUT /categories/{$id} with ".json_encode($payload).'.');

            return self::SUCCESS;
        }

        $client->updateCategory($id, $payload);

        $this->info("Updated category {$id}.");

        return self::SUCCESS;
    }

    private function delete(HelpScoutDocsClient $client): int
    {
        $id = (string) ($this->option('id') ?? '');

        if ($id === '') {
            $this->error('--id is required for delete.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would DELETE /categories/{$id}.");

            return self::SUCCESS;
        }

        $client->deleteCategory($id);

        $this->info("Deleted category {$id}. Articles that were in it fall back to \"Uncategorized\".");

        return self::SUCCESS;
    }

    /**
     * Collect the optional category attributes, validating enum-style options.
     *
     * @return array<string, mixed>|null Null signals a validation failure.
     */
    private function optionalAttributes(): ?array
    {
        $attributes = [];

        if (($slug = $this->option('slug')) !== null) {
            $attributes['slug'] = $slug;
        }

        if (($visibility = $this->option('visibility')) !== null) {
            if (! in_array($visibility, ['public', 'private'], true)) {
                $this->error("Invalid --visibility value '{$visibility}'. Must be 'public' or 'private'.");

                return null;
            }

            $attributes['visibility'] = $visibility;
        }

        if (($order = $this->option('order')) !== null) {
            $attributes['order'] = (int) $order;
        }

        if (($defaultSort = $this->option('default-sort')) !== null) {
            if (! in_array($defaultSort, ['popularity', 'name'], true)) {
                $this->error("Invalid --default-sort value '{$defaultSort}'. Must be 'popularity' or 'name'.");

                return null;
            }

            $attributes['defaultSort'] = $defaultSort;
        }

        return $attributes;
    }

    private function invalidAction(): int
    {
        $this->error("Invalid action '{$this->argument('action')}'. Must be one of: create, update, delete.");

        return self::FAILURE;
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
