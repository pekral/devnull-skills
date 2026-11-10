<?php

declare(strict_types=1);

namespace DevnullInsights;

use DateTimeImmutable;
use Exception;

/**
 * Reads the public "latest" log of dev-null.sh.
 *
 * dev-null.sh has no API keys: its /api/v1 is either anonymous (single users/commits) or signed by the mobile app's
 * SSH device key, and commit search requires a GitHub session. The public log is served by Inertia, so asking for it
 * with the X-Inertia header returns the JSON the web app renders, newest first, with a cursor.
 *
 * Every response is checked against the shape this class relies on. A changed shape throws instead of returning an
 * empty result, so a site change can never pass as "nothing was posted".
 */
final readonly class DevnullClient
{
    public function __construct(private HttpClient $http, private string $baseUrl, private int $pageDelayMs = 400)
    {
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** The asset version changes with each deploy; a request without it gets 409 with the current one in a header. */
    public function inertiaVersion(): string
    {
        $response = $this->http->get($this->baseUrl.'/log/latest', ['X-Inertia: true', 'X-Requested-With: XMLHttpRequest']);
        $version = $response->headers['x-inertia-version'] ?? '';

        if ($version === '') {
            throw new DevnullException("dev-null.sh changed: no x-inertia-version header on /log/latest (HTTP {$response->status})");
        }

        return $version;
    }

    /**
     * @param  callable(int $page, int $posts): void  $progress
     * @return array{posts: array<string, array<string, mixed>>, complete: bool}
     */
    public function window(DateTimeImmutable $cutoff, int $maxPages, callable $progress): array
    {
        $version = $this->inertiaVersion();
        $url = $this->baseUrl.'/log/latest';
        $posts = [];

        for ($page = 1; $page <= $maxPages && $url !== null; $page++) {
            $response = $this->http->get($url, $this->inertiaHeaders($version));

            if ($response->status === 409) {
                // A deploy during the run changed the version; take the new one and read the same page again.
                $version = $response->headers['x-inertia-version'] ?? $this->inertiaVersion();
                $response = $this->http->get($url, $this->inertiaHeaders($version));
            }

            $commits = $this->commits($this->ok($response, $url));
            $reachedCutoff = false;

            foreach ($commits['data'] as $commit) {
                if ($this->createdAt($commit) < $cutoff) {
                    $reachedCutoff = true;
                    break;
                }
                $posts[$commit['id']] = $commit;
            }

            $progress($page, count($posts));
            $url = $reachedCutoff ? null : $commits['next_page_url'];

            if ($url !== null && $this->pageDelayMs > 0) {
                usleep($this->pageDelayMs * 1000);
            }
        }

        return ['posts' => $posts, 'complete' => $url === null];
    }

    /** @return list<array{author: string, body: string, stars: int}> */
    public function replies(string $id): array
    {
        $url = $this->baseUrl."/commits/{$id}/replies?limit=50";
        $data = $this->ok($this->http->get($url, ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']), $url);

        if (! isset($data['data']) || ! is_array($data['data'])) {
            throw new DevnullException("dev-null.sh changed: replies of {$id} have no data list");
        }

        return array_map(fn (array $reply): array => [
            'author' => '@'.($reply['author']['handle'] ?? '?'),
            'body' => (string) ($reply['body'] ?? ''),
            'stars' => (int) ($reply['stars_count'] ?? 0),
        ], array_values(array_filter($data['data'], 'is_array')));
    }

    /** @return list<string> */
    private function inertiaHeaders(string $version): array
    {
        return ['X-Inertia: true', "X-Inertia-Version: {$version}", 'X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
    }

    /** @return array<mixed> */
    private function ok(HttpResponse $response, string $url): array
    {
        if ($response->status === 0) {
            throw new DevnullException("Cannot reach {$url}");
        }

        if ($response->status !== 200) {
            throw new DevnullException("HTTP {$response->status} for {$url}");
        }

        return $response->json();
    }

    /**
     * @param  array<mixed>  $data
     * @return array{data: list<array<string, mixed>>, next_page_url: ?string}
     */
    private function commits(array $data): array
    {
        $commits = $data['props']['commits'] ?? null;

        if (! is_array($commits) || ! isset($commits['data']) || ! is_array($commits['data'])) {
            throw new DevnullException('dev-null.sh changed: the log response has no props.commits.data list');
        }

        foreach ($commits['data'] as $commit) {
            if (! is_array($commit) || ! is_string($commit['id'] ?? null) || ! is_string($commit['created_at'] ?? null) || ! is_string($commit['author']['handle'] ?? null)) {
                throw new DevnullException('dev-null.sh changed: a log post lacks id, created_at or author.handle');
            }
        }

        $next = $commits['next_page_url'] ?? null;

        return ['data' => array_values($commits['data']), 'next_page_url' => is_string($next) && $next !== '' ? $next : null];
    }

    /** @param array<string, mixed> $commit */
    private function createdAt(array $commit): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($commit['created_at']);
        } catch (Exception $exception) {
            throw new DevnullException("dev-null.sh changed: unreadable created_at \"{$commit['created_at']}\"", previous: $exception);
        }
    }
}
