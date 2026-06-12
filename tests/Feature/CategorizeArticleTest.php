<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class CategorizeArticleTest extends TestCase
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
                    'articles' => [[
                        'id' => 'a1',
                        'name' => 'Welcome',
                        'slug' => 'welcome',
                        'status' => 'published',
                        'file' => 'getting-started/welcome.original.html',
                    ]],
                ]],
            ]),
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->absolutePath);

        parent::tearDown();
    }

    private function fakeCategories(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections/c1/categories*' => Http::response([
                'categories' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'cat1', 'name' => 'How-tos', 'slug' => 'how-tos'],
                        ['id' => 'cat2', 'name' => 'FAQ', 'slug' => 'faq'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/articles/a1*' => Http::response([
                'article' => ['id' => 'a1', 'name' => 'Welcome'],
            ]),
        ]);
    }

    public function test_resolves_names_and_slugs_to_ids_and_replaces_categories(): void
    {
        $this->fakeCategories();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--category' => ['How-tos', 'faq'],
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/articles/a1')
                && $request->data() === ['categories' => ['cat1', 'cat2']];
        });
    }

    public function test_accepts_category_ids_directly_and_dedupes(): void
    {
        $this->fakeCategories();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--category' => ['cat1', 'how-tos'],
        ])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->data() === ['categories' => ['cat1']]);
    }

    public function test_fails_when_no_category_given(): void
    {
        Http::fake();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_category_unresolved(): void
    {
        $this->fakeCategories();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--category' => ['nonexistent'],
        ])->assertFailed();

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }

    public function test_fails_when_article_not_in_manifest(): void
    {
        Http::fake();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'missing',
            '--path' => $this->relativePath,
            '--category' => ['How-tos'],
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_put(): void
    {
        $this->fakeCategories();

        $this->artisan('tutorials:categorize', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--category' => ['How-tos'],
            '--dry-run' => true,
        ])->assertSuccessful();

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT');
    }
}
