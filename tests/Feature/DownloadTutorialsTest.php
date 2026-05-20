<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class DownloadTutorialsTest extends TestCase
{
    private string $relativePath = 'helpscout-tutorials-test';

    private string $absolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->absolutePath = storage_path($this->relativePath);
        File::deleteDirectory($this->absolutePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->absolutePath);

        parent::tearDown();
    }

    public function test_downloads_articles_and_writes_manifest(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections?page=1' => Http::response([
                'collections' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'c1', 'name' => 'Getting Started', 'slug' => 'getting-started'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/collections/c1/articles*' => Http::response([
                'articles' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'a1', 'name' => 'Welcome', 'slug' => 'welcome', 'status' => 'published'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/articles/a1' => Http::response([
                'article' => [
                    'id' => 'a1',
                    'number' => 42,
                    'name' => 'Welcome',
                    'slug' => 'welcome',
                    'status' => 'published',
                    'text' => '<p>Hello world</p>',
                    'updatedAt' => '2025-12-01T00:00:00Z',
                    'publicUrl' => 'https://docs.example.com/article/42-welcome',
                ],
            ]),
        ]);

        $this->artisan('tutorials:download', ['--path' => $this->relativePath])
            ->assertSuccessful();

        $articlePath = $this->absolutePath.'/getting-started/welcome.original.html';
        $this->assertFileExists($articlePath);
        $this->assertSame('<p>Hello world</p>', File::get($articlePath));

        $manifest = json_decode(File::get($this->absolutePath.'/manifest.json'), true);

        $this->assertCount(1, $manifest['collections']);
        $this->assertSame('Getting Started', $manifest['collections'][0]['name']);
        $this->assertSame('a1', $manifest['collections'][0]['articles'][0]['id']);
        $this->assertSame(
            'getting-started/welcome.original.html',
            $manifest['collections'][0]['articles'][0]['file'],
        );
        $this->assertSame(
            hash('sha256', '<p>Hello world</p>'),
            $manifest['collections'][0]['articles'][0]['original_hash'],
        );
    }

    public function test_collection_filter_excludes_non_matching_collections(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections?page=1' => Http::response([
                'collections' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'c1', 'name' => 'Alpha', 'slug' => 'alpha'],
                        ['id' => 'c2', 'name' => 'Beta', 'slug' => 'beta'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/collections/c1/articles*' => Http::response([
                'articles' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'a1', 'name' => 'Intro', 'slug' => 'intro'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/articles/a1' => Http::response([
                'article' => ['id' => 'a1', 'name' => 'Intro', 'slug' => 'intro', 'text' => 'hi'],
            ]),
        ]);

        $this->artisan('tutorials:download', [
            '--path' => $this->relativePath,
            '--collection' => ['Alpha'],
        ])->assertSuccessful();

        $manifest = json_decode(File::get($this->absolutePath.'/manifest.json'), true);

        $this->assertCount(1, $manifest['collections']);
        $this->assertSame('Alpha', $manifest['collections'][0]['name']);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/collections/c2/articles'));
    }

    public function test_default_collection_config_is_used_when_no_flag(): void
    {
        config()->set('helpscout-tutorials.default_collection', 'Beta');

        Http::fake([
            'docsapi.helpscout.net/v1/collections?page=1' => Http::response([
                'collections' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'c1', 'name' => 'Alpha', 'slug' => 'alpha'],
                        ['id' => 'c2', 'name' => 'Beta', 'slug' => 'beta'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/collections/c2/articles*' => Http::response([
                'articles' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'b1', 'name' => 'B Intro', 'slug' => 'b-intro'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/articles/b1' => Http::response([
                'article' => ['id' => 'b1', 'name' => 'B Intro', 'slug' => 'b-intro', 'text' => 'hi'],
            ]),
        ]);

        $this->artisan('tutorials:download', ['--path' => $this->relativePath])
            ->assertSuccessful();

        $manifest = json_decode(File::get($this->absolutePath.'/manifest.json'), true);

        $this->assertCount(1, $manifest['collections']);
        $this->assertSame('Beta', $manifest['collections'][0]['name']);
    }
}
