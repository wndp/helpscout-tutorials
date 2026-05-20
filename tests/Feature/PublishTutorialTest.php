<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class PublishTutorialTest extends TestCase
{
    private string $relativePath = 'helpscout-tutorials-test';

    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->absolutePath = storage_path($this->relativePath);
        File::deleteDirectory($this->absolutePath);
        File::ensureDirectoryExists($this->absolutePath.'/getting-started');

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
                        'updated_at' => '2025-12-01T00:00:00Z',
                        'original_hash' => hash('sha256', '<p>Hello</p>'),
                        'file' => 'getting-started/welcome.original.html',
                    ]],
                ]],
            ]),
        );

        File::put($this->absolutePath.'/getting-started/welcome.original.html', '<p>Hello</p>');
        File::put($this->absolutePath.'/getting-started/welcome.proposed.html', '<p>Updated</p>');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->absolutePath);

        parent::tearDown();
    }

    public function test_publishes_proposed_text_and_updates_manifest(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles/a1*' => Http::response([
                'article' => [
                    'id' => 'a1',
                    'text' => '<p>Updated</p>',
                    'updatedAt' => '2026-05-20T00:00:00Z',
                ],
            ]),
        ]);

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/articles/a1')
                && str_contains($request->url(), 'reload=true')
                && $request->data() === ['text' => '<p>Updated</p>'];
        });

        $manifest = json_decode(File::get($this->absolutePath.'/manifest.json'), true);
        $article = $manifest['collections'][0]['articles'][0];

        $this->assertSame('2026-05-20T00:00:00Z', $article['updated_at']);
        $this->assertSame(hash('sha256', '<p>Updated</p>'), $article['original_hash']);
        $this->assertArrayHasKey('last_published_at', $article);
    }

    public function test_dry_run_does_not_call_api(): void
    {
        Http::fake();

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--dry-run' => true,
        ])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_fails_when_proposed_file_missing(): void
    {
        File::delete($this->absolutePath.'/getting-started/welcome.proposed.html');

        Http::fake();

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_fails_when_article_id_not_in_manifest(): void
    {
        Http::fake();

        $this->artisan('tutorials:publish', [
            'article_id' => 'does-not-exist',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_includes_name_when_option_provided(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles/a1*' => Http::response([
                'article' => ['id' => 'a1', 'text' => '<p>Updated</p>'],
            ]),
        ]);

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--name' => 'New Title',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->data() === [
                'text' => '<p>Updated</p>',
                'name' => 'New Title',
            ];
        });
    }

    public function test_unpublish_without_proposed_file_sends_status_only(): void
    {
        File::delete($this->absolutePath.'/getting-started/welcome.proposed.html');

        Http::fake([
            'docsapi.helpscout.net/v1/articles/a1*' => Http::response([
                'article' => [
                    'id' => 'a1',
                    'status' => 'notpublished',
                    'updatedAt' => '2026-05-20T00:00:00Z',
                ],
            ]),
        ]);

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--status' => 'notpublished',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->data() === ['status' => 'notpublished'];
        });

        $manifest = json_decode(File::get($this->absolutePath.'/manifest.json'), true);
        $this->assertSame('notpublished', $manifest['collections'][0]['articles'][0]['status']);
    }

    public function test_rejects_invalid_status(): void
    {
        Http::fake();

        $this->artisan('tutorials:publish', [
            'article_id' => 'a1',
            '--path' => $this->relativePath,
            '--status' => 'pending',
        ])->assertFailed();

        Http::assertNothingSent();
    }
}
