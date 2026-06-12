<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class ListCategoriesTest extends TestCase
{
    private string $relativePath = 'helpscout-tutorials-test';

    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->absolutePath = storage_path($this->relativePath);
        File::deleteDirectory($this->absolutePath);
        File::ensureDirectoryExists($this->absolutePath);

        File::put(
            $this->absolutePath.'/manifest.json',
            json_encode([
                'downloaded_at' => '2026-05-01T00:00:00Z',
                'collections' => [[
                    'id' => 'c1',
                    'name' => 'Getting Started',
                    'slug' => 'getting-started',
                    'articles' => [],
                ]],
            ]),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->absolutePath);

        parent::tearDown();
    }

    public function test_lists_categories_for_a_collection(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections/c1/categories*' => Http::response([
                'categories' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'cat1', 'name' => 'How-tos', 'slug' => 'how-tos', 'visibility' => 'public', 'articleCount' => 3],
                    ],
                ],
            ]),
        ]);

        $this->artisan('tutorials:categories', [
            'collection' => 'getting-started',
            '--path' => $this->relativePath,
        ])
            ->expectsTable(
                ['ID', 'Name', 'Slug', 'Visibility', 'Articles'],
                [['cat1', 'How-tos', 'how-tos', 'public', 3]],
            )
            ->assertSuccessful();
    }

    public function test_fails_when_collection_not_in_manifest(): void
    {
        Http::fake();

        $this->artisan('tutorials:categories', [
            'collection' => 'nope',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_manifest_missing(): void
    {
        File::delete($this->absolutePath.'/manifest.json');

        Http::fake();

        $this->artisan('tutorials:categories', [
            'collection' => 'getting-started',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }
}
