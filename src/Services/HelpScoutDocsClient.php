<?php

namespace Wndp\HelpScoutTutorials\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HelpScoutDocsClient
{
    private const BASE_URL = 'https://docsapi.helpscout.net/v1';

    public function __construct(private ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('helpscout-tutorials.api_key');

        if (empty($this->apiKey)) {
            throw new RuntimeException('HELPSCOUT_DOCS_API_KEY is not configured.');
        }
    }

    /**
     * Iterate every collection in the account.
     *
     * @return iterable<array{id: string, name: string, slug: string, visibility: string}>
     */
    public function collections(): iterable
    {
        yield from $this->paginate('collections', 'collections');
    }

    /**
     * Iterate every article in a collection.
     *
     * @return iterable<array{id: string, number: int, slug: string, name: string, status: string, updatedAt: string}>
     */
    public function articlesInCollection(string $collectionId, string $status = 'published'): iterable
    {
        yield from $this->paginate("collections/{$collectionId}/articles", 'articles', [
            'status' => $status,
            'pageSize' => 100,
        ]);
    }

    /**
     * Fetch a single article including its `text` body.
     *
     * @return array{id: string, number: int, slug: string, name: string, status: string, text: string, updatedAt: string, collectionId: string, categories: array<int, string>}
     */
    public function getArticle(string $articleId): array
    {
        $response = $this->request()->get(self::BASE_URL."/articles/{$articleId}");

        $this->ensureOk($response, "GET /articles/{$articleId}");

        return $response->json('article');
    }

    /**
     * Update an article. Returns the reloaded article body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateArticle(string $articleId, array $payload): array
    {
        $response = $this->request()->put(
            self::BASE_URL."/articles/{$articleId}?reload=true",
            $payload,
        );

        $this->ensureOk($response, "PUT /articles/{$articleId}");

        return $response->json('article') ?? [];
    }

    /**
     * Create a new article. Returns the reloaded article body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createArticle(array $payload): array
    {
        $response = $this->request()->post(
            self::BASE_URL.'/articles?reload=true',
            $payload,
        );

        $this->ensureOk($response, 'POST /articles');

        return $response->json('article') ?? [];
    }

    /**
     * Walk a paginated endpoint, yielding each item from the `items` array.
     *
     * @param  array<string, mixed>  $query
     * @return iterable<array<string, mixed>>
     */
    private function paginate(string $path, string $envelope, array $query = []): iterable
    {
        $page = 1;

        do {
            $response = $this->request()->get(self::BASE_URL.'/'.$path, [...$query, 'page' => $page]);

            $this->ensureOk($response, "GET /{$path}");

            $payload = $response->json($envelope);
            $items = $payload['items'] ?? [];

            foreach ($items as $item) {
                yield $item;
            }

            $pages = (int) ($payload['pages'] ?? 1);
            $page++;
        } while ($page <= $pages);
    }

    private function request(): PendingRequest
    {
        return Http::withBasicAuth($this->apiKey, 'X')
            ->acceptJson()
            ->retry(3, 1000, function (\Throwable $exception, PendingRequest $request) {
                if (! $exception instanceof RequestException) {
                    return false;
                }

                return $exception->response->status() === 429
                    || $exception->response->serverError();
            }, throw: false);
    }

    private function ensureOk(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(
            "HelpScout Docs API request failed ({$context}): HTTP {$response->status()} {$response->body()}",
        );
    }
}
