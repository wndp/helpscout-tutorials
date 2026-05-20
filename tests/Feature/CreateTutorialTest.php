<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class CreateTutorialTest extends TestCase
{
    private string $relativePath = 'helpscout-tutorials-test';

    private string $absolutePath;

    private string $draftRelative = 'sample/new/sample.draft.html';

    protected function setUp(): void
    {
        parent::setUp();

        $this->absolutePath = storage_path($this->relativePath);
        File::deleteDirectory($this->absolutePath);
        File::ensureDirectoryExists($this->absolutePath.'/sample/new');

        File::put(
            $this->absolutePath.'/manifest.json',
            json_encode([
                'downloaded_at' => '2026-05-01T00:00:00Z',
                'collections' => [[
                    'id' => 'c1',
                    'name' => 'Sample Collection',
                    'slug' => 'sample',
                    'articles' => [],
                ]],
            ]),
        );

        File::put(
            $this->absolutePath.'/'.$this->draftRelative,
            '<p>Hello new article</p>',
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->absolutePath);

        parent::tearDown();
    }

    public function test_creates_article_with_collection_lookup(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => [
                    'id' => 'new-article-id',
                    'name' => 'New Tutorial',
                    'collectionId' => 'c1',
                ],
            ], 201),
        ]);

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'New Tutorial',
            '--collection' => 'Sample Collection',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/articles')
                && str_contains($request->url(), 'reload=true')
                && $request->data() === [
                    'collectionId' => 'c1',
                    'name' => 'New Tutorial',
                    'text' => '<p>Hello new article</p>',
                    'status' => 'published',
                ];
        });
    }

    public function test_collection_lookup_matches_slug_case_insensitively(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => ['id' => 'a', 'name' => 'X'],
            ], 201),
        ]);

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'sample',
        ])->assertSuccessful();

        Http::assertSent(fn ($r) => ($r->data()['collectionId'] ?? null) === 'c1');
    }

    public function test_default_collection_config_is_used_when_no_flag(): void
    {
        config()->set('helpscout-tutorials.default_collection', 'Sample Collection');

        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => ['id' => 'a', 'name' => 'X'],
            ], 201),
        ]);

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
        ])->assertSuccessful();

        Http::assertSent(fn ($r) => ($r->data()['collectionId'] ?? null) === 'c1');
    }

    public function test_includes_slug_and_status_when_provided(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => ['id' => 'a', 'name' => 'X'],
            ], 201),
        ]);

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
            '--slug' => 'my-slug',
            '--status' => 'notpublished',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->data() === [
                'collectionId' => 'c1',
                'name' => 'X',
                'text' => '<p>Hello new article</p>',
                'status' => 'notpublished',
                'slug' => 'my-slug',
            ];
        });
    }

    public function test_dry_run_does_not_call_api(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
            '--dry-run' => true,
        ])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_fails_when_name_is_missing(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--collection' => 'Sample Collection',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_collection_is_missing(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_file_missing(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => 'sample/new/does-not-exist.draft.html',
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_manifest_missing(): void
    {
        File::delete($this->absolutePath.'/manifest.json');

        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_collection_not_in_manifest(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'NonExistent',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_rejects_invalid_status(): void
    {
        Http::fake();

        $this->artisan('tutorials:create', [
            'file' => $this->draftRelative,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
            '--status' => 'pending',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_accepts_absolute_file_path(): void
    {
        $absoluteDraft = $this->absolutePath.'/'.$this->draftRelative;

        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => ['id' => 'a', 'name' => 'X'],
            ], 201),
        ]);

        $this->artisan('tutorials:create', [
            'file' => $absoluteDraft,
            '--path' => $this->relativePath,
            '--name' => 'X',
            '--collection' => 'Sample Collection',
        ])->assertSuccessful();

        Http::assertSent(fn ($r) => ($r->data()['text'] ?? null) === '<p>Hello new article</p>');
    }
}
