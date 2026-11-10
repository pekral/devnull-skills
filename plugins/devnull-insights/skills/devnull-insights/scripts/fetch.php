<?php

declare(strict_types=1);

/**
 * Reads the public "latest" log of https://dev-null.sh for the last N hours and keeps the posts that match the topics.
 *
 * dev-null.sh has no API keys: its /api/v1 is either anonymous (single users/commits) or signed by the mobile app's
 * SSH device key, and commit search requires a GitHub session. The public log is served by Inertia, so asking for it
 * with the X-Inertia header returns the same JSON the web app renders, newest first with a cursor.
 *
 * Runs unchanged on Windows, macOS and Linux with PHP 8.3+ and ext-openssl (no ext-curl, no shell tools).
 *
 * Usage: php fetch.php [--hours=24] [--topics=topics.json] [--keywords=a,b,c] [--max-pages=100] [--no-replies] [--trending=10] [--output=file.json]
 * Output: UTF-8 JSON to --output (or stdout), progress on stderr. Prefer --output: Windows PowerShell 5.1 re-encodes `>` redirects to UTF-16.
 */

const BASE = 'https://dev-null.sh';
const USER_AGENT = 'devnull-insights-skill/1.0';

$options = getopt('', ['hours::', 'topics::', 'keywords::', 'max-pages::', 'no-replies', 'trending::', 'output::']);
$hours = (float) ($options['hours'] ?? 24);
$maxPages = (int) ($options['max-pages'] ?? 100);
$trendingCount = (int) ($options['trending'] ?? 10);
$withReplies = ! isset($options['no-replies']);
$topicsFile = $options['topics'] ?? __DIR__.'/../topics.json';

$topics = json_decode((string) @file_get_contents($topicsFile), true);
if (! is_array($topics)) {
    fail("Cannot read topics from {$topicsFile}");
}
if (isset($options['keywords']) && $options['keywords'] !== '') {
    $topics['repository'] = array_values(array_filter(array_map('trim', explode(',', (string) $options['keywords']))));
}

$patterns = [];
foreach ($topics as $topic => $keywords) {
    foreach ((array) $keywords as $keyword) {
        $patterns[$topic][$keyword] = '/(?<![\p{L}\p{N}_])'.preg_quote((string) $keyword, '/').'(?![\p{L}\p{N}_])/iu';
    }
}

$cutoff = new DateTimeImmutable("-{$hours} hours", new DateTimeZone('UTC'));
$version = inertiaVersion();
$url = BASE.'/log/latest';
$posts = [];

for ($page = 1; $page <= $maxPages && $url !== null; $page++) {
    $data = request($url, inertiaHeaders($version));
    $commits = $data['props']['commits'] ?? fail('Unexpected log response: no commits prop');
    $reachedCutoff = false;

    foreach ($commits['data'] as $commit) {
        if (new DateTimeImmutable($commit['created_at']) < $cutoff) {
            $reachedCutoff = true;
            break;
        }
        $posts[$commit['id']] = $commit;
    }

    fwrite(STDERR, sprintf("page %d: %d posts so far\n", $page, count($posts)));
    $url = $reachedCutoff ? null : ($commits['next_page_url'] ?? null);
    usleep(400_000);
}

if ($url !== null) {
    fwrite(STDERR, "warning: stopped at --max-pages={$maxPages} before reaching the {$hours}h cutoff\n");
}

$matched = [];
foreach ($posts as $commit) {
    $text = implode("\n", array_filter([
        $commit['body'] ?? '',
        $commit['link_preview']['url'] ?? '',
        $commit['link_preview']['title'] ?? '',
        $commit['link_preview']['description'] ?? '',
        $commit['quoted_commit']['body'] ?? '',
    ]));

    $hits = [];
    foreach ($patterns as $topic => $keywordPatterns) {
        foreach ($keywordPatterns as $keyword => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $hits[$topic][] = $keyword;
            }
        }
    }

    if ($hits !== []) {
        $entry = summarize($commit);
        $entry['topics'] = $hits;
        if ($withReplies && ($commit['replies_count'] ?? 0) > 0) {
            $entry['replies'] = replies($commit['id']);
            usleep(300_000);
        }
        $matched[] = $entry;
    }
}

usort($matched, fn (array $a, array $b): int => engagement($b) <=> engagement($a));

$all = array_map(summarize(...), array_values($posts));
usort($all, fn (array $a, array $b): int => engagement($b) <=> engagement($a));

$json = json_encode([
    'source' => BASE.'/log/latest',
    'window' => ['from' => $cutoff->format(DATE_ATOM), 'to' => gmdate(DATE_ATOM), 'hours' => $hours],
    'scanned_posts' => count($posts),
    'complete_window' => $url === null,
    'topics' => array_keys($topics),
    'matched_count' => count($matched),
    'matched' => $matched,
    'trending_overall' => array_slice($all, 0, $trendingCount),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

if (isset($options['output']) && $options['output'] !== '') {
    file_put_contents((string) $options['output'], $json) !== false || fail("Cannot write {$options['output']}");
    fwrite(STDERR, "written to {$options['output']}\n");
} else {
    echo $json;
}

function summarize(array $commit): array
{
    return array_filter([
        'url' => BASE.'/@'.$commit['author']['handle'].'/commits/'.$commit['id'],
        'author' => '@'.$commit['author']['handle'],
        'author_bio' => $commit['author']['bio'] ?? null,
        'created_at' => $commit['created_at'],
        'stars' => $commit['stars_count'] ?? 0,
        'replies_count' => $commit['replies_count'] ?? 0,
        'body' => $commit['body'] ?? '',
        'link' => $commit['link_preview']['url'] ?? null,
        'link_title' => $commit['link_preview']['title'] ?? null,
        'quoted' => $commit['quoted_commit']['body'] ?? null,
    ], fn (mixed $value): bool => $value !== null && $value !== '');
}

function engagement(array $entry): int
{
    return $entry['stars'] + 2 * $entry['replies_count'];
}

/** @return list<array{author: string, body: string, stars: int}> */
function replies(string $id): array
{
    try {
        $data = request(BASE."/commits/{$id}/replies?limit=50", ['Accept: application/json', 'X-Requested-With: XMLHttpRequest']);
    } catch (RuntimeException $exception) {
        fwrite(STDERR, "warning: replies of {$id}: {$exception->getMessage()}\n");

        return [];
    }

    return array_map(fn (array $reply): array => [
        'author' => '@'.$reply['author']['handle'],
        'body' => $reply['body'] ?? '',
        'stars' => $reply['stars_count'] ?? 0,
    ], $data['data'] ?? []);
}

/** The asset version changes with each deploy; a request without it gets 409 with the current one in a header. */
function inertiaVersion(): string
{
    ['headers' => $headers] = http(BASE.'/log/latest', ['X-Inertia: true', 'X-Requested-With: XMLHttpRequest']);

    return $headers['x-inertia-version'] ?? fail('Cannot detect the Inertia version of dev-null.sh');
}

/**
 * A GET over PHP's own HTTPS stream wrapper, so the script needs no ext-curl and runs the same on Windows, macOS and
 * Linux: only ext-openssl, which Composer itself requires.
 *
 * @param  list<string>  $headers
 * @return array{status: int, headers: array<string, string>, body: string}
 */
function http(string $url, array $headers): array
{
    if (! extension_loaded('openssl')) {
        fail('PHP extension "openssl" is required for HTTPS (enable extension=openssl in php.ini)');
    }

    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers),
        'user_agent' => USER_AGENT,
        'timeout' => 30,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $context);
    $lines = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);

    $status = 0;
    $parsed = [];
    foreach ($lines as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
            $status = (int) $match[1];
            $parsed = [];
        } elseif (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))] = trim($value);
        }
    }

    return ['status' => $status, 'headers' => $parsed, 'body' => is_string($body) ? $body : ''];
}

/** @return list<string> */
function inertiaHeaders(string $version): array
{
    return ['X-Inertia: true', "X-Inertia-Version: {$version}", 'X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
}

function request(string $url, array $headers): array
{
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        ['status' => $status, 'body' => $body] = http($url, $headers);

        if ($status === 200) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                return $data;
            }
        }
        if ($status !== 0 && $status !== 429 && $status < 500) {
            throw new RuntimeException("HTTP {$status} for {$url}");
        }
        sleep(5 * $attempt);
    }

    throw new RuntimeException("Giving up on {$url} after retries");
}

function fail(string $message): never
{
    fwrite(STDERR, "error: {$message}\n");
    exit(1);
}
