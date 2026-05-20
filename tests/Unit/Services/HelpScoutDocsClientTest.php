<?php

namespace Wndp\HelpScoutTutorials\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Wndp\HelpScoutTutorials\Services\HelpScoutDocsClient;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class HelpScoutDocsClientTest extends TestCase
{
    public function test_throws_when_api_key_is_missing(): void
    {
        config()->set('helpscout-tutorials.api_key', null);

        $this->expectException(RuntimeException::class);

        new HelpScoutDocsClient;
    }

    public function test_collections_walks_paginated_endpoint_with_basic_auth(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections?page=1' => Http::response([
                'collections' => [
                    'page' => 1,
                    'pages' => 2,
                    'items' => [
                        ['id' => 'c1', 'name' => 'First', 'slug' => 'first'],
                    ],
                ],
            ]),
            'docsapi.helpscout.net/v1/collections?page=2' => Http::response([
                'collections' => [
                    'page' => 2,
                    'pages' => 2,
                    'items' => [
                        ['id' => 'c2', 'name' => 'Second', 'slug' => 'second'],
                    ],
                ],
            ]),
        ]);

        $client = new HelpScoutDocsClient;

        $ids = collect($client->collections())->pluck('id')->all();

        $this->assertSame(['c1', 'c2'], $ids);

        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization')[0] ?? '';
            $expected = 'Basic '.base64_encode('test-key:X');

            return $authHeader === $expected;
        });
    }

    public function test_articles_in_collection_passes_status_filter(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/collections/c1/articles*' => Http::response([
                'articles' => [
                    'page' => 1,
                    'pages' => 1,
                    'items' => [
                        ['id' => 'a1', 'name' => 'One', 'slug' => 'one', 'status' => 'published'],
                    ],
                ],
            ]),
        ]);

        $client = new HelpScoutDocsClient;

        $articles = iterator_to_array($client->articlesInCollection('c1'), false);

        $this->assertCount(1, $articles);
        $this->assertSame('a1', $articles[0]['id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'status=published')
                && str_contains($request->url(), 'pageSize=100');
        });
    }

    public function test_get_article_returns_article_body(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles/a1' => Http::response([
                'article' => [
                    'id' => 'a1',
                    'name' => 'My Article',
                    'text' => '<p>hello</p>',
                    'updatedAt' => '2026-01-01T00:00:00Z',
                ],
            ]),
        ]);

        $client = new HelpScoutDocsClient;

        $article = $client->getArticle('a1');

        $this->assertSame('My Article', $article['name']);
        $this->assertSame('<p>hello</p>', $article['text']);
    }

    public function test_update_article_puts_payload_with_reload(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles/a1*' => Http::response([
                'article' => [
                    'id' => 'a1',
                    'text' => '<p>updated</p>',
                    'updatedAt' => '2026-05-20T00:00:00Z',
                ],
            ]),
        ]);

        $client = new HelpScoutDocsClient;

        $result = $client->updateArticle('a1', ['text' => '<p>updated</p>']);

        $this->assertSame('<p>updated</p>', $result['text']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'reload=true')
                && $request->data() === ['text' => '<p>updated</p>'];
        });
    }

    public function test_create_article_posts_payload_with_reload(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles*' => Http::response([
                'article' => [
                    'id' => 'new-id',
                    'name' => 'Brand new',
                    'collectionId' => 'c1',
                ],
            ], 201),
        ]);

        $client = new HelpScoutDocsClient;

        $result = $client->createArticle([
            'collectionId' => 'c1',
            'name' => 'Brand new',
            'text' => '<p>body</p>',
            'status' => 'published',
        ]);

        $this->assertSame('new-id', $result['id']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/articles')
                && str_contains($request->url(), 'reload=true')
                && $request->data() === [
                    'collectionId' => 'c1',
                    'name' => 'Brand new',
                    'text' => '<p>body</p>',
                    'status' => 'published',
                ];
        });
    }

    public function test_throws_on_non_successful_response(): void
    {
        Http::fake([
            'docsapi.helpscout.net/v1/articles/missing' => Http::response(['error' => 'Not found'], 404),
        ]);

        $client = new HelpScoutDocsClient;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        $client->getArticle('missing');
    }
}
