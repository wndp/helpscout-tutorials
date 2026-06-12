<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class ManageCategoryTest extends TestCase
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

    public function test_creates_category_with_collection_lookup(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/categories*' => Http::response([
                'category' => ['id' => 'new-cat', 'name' => 'How-tos'],
            ], 201),
        ]);

        $this->artisan('tutorials:category', [
            'action' => 'create',
            '--path' => $this->relativePath,
            '--name' => 'How-tos',
            '--collection' => 'getting-started',
            '--visibility' => 'public',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/categories')
                && $request->data() === [
                    'collectionId' => 'c1',
                    'name' => 'How-tos',
                    'visibility' => 'public',
                ];
        });
    }

    public function test_create_fails_when_name_missing(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'create',
            '--path' => $this->relativePath,
            '--collection' => 'getting-started',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_create_fails_when_collection_not_in_manifest(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'create',
            '--path' => $this->relativePath,
            '--name' => 'How-tos',
            '--collection' => 'nope',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_updates_category_with_explicit_name(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/categories/cat1*' => Http::response([
                'category' => ['id' => 'cat1', 'name' => 'Renamed'],
            ]),
        ]);

        $this->artisan('tutorials:category', [
            'action' => 'update',
            '--path' => $this->relativePath,
            '--id' => 'cat1',
            '--name' => 'Renamed',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/categories/cat1')
                && $request->data() === ['name' => 'Renamed'];
        });
    }

    public function test_update_fetches_current_name_when_only_changing_other_fields(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/categories/cat1*' => Http::response([
                'category' => ['id' => 'cat1', 'name' => 'Existing Name'],
            ]),
        ]);

        $this->artisan('tutorials:category', [
            'action' => 'update',
            '--path' => $this->relativePath,
            '--id' => 'cat1',
            '--visibility' => 'private',
        ])->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/categories/cat1');
        });

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->data() === ['name' => 'Existing Name', 'visibility' => 'private'];
        });
    }

    public function test_update_fails_without_id(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'update',
            '--path' => $this->relativePath,
            '--name' => 'Renamed',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_deletes_category(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/categories/cat1' => Http::response([], 200),
        ]);

        $this->artisan('tutorials:category', [
            'action' => 'delete',
            '--path' => $this->relativePath,
            '--id' => 'cat1',
        ])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/categories/cat1'));
    }

    public function test_delete_fails_without_id(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'delete',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_rejects_invalid_action(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'frobnicate',
            '--path' => $this->relativePath,
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_rejects_invalid_visibility(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'create',
            '--path' => $this->relativePath,
            '--name' => 'How-tos',
            '--collection' => 'getting-started',
            '--visibility' => 'secret',
        ])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_call_api(): void
    {
        Http::fake();

        $this->artisan('tutorials:category', [
            'action' => 'delete',
            '--path' => $this->relativePath,
            '--id' => 'cat1',
            '--dry-run' => true,
        ])->assertSuccessful();

        Http::assertNothingSent();
    }
}
