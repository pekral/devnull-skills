<?php

declare(strict_types=1);

/**
 * Reads the public "latest" log of https://dev-null.sh for the last N hours and keeps the posts that match the topics.
 *
 * dev-null.sh has no API keys: its /api/v1 is either anonymous (single users/commits) or signed by the mobile app's
 * SSH device key, and commit search requires a GitHub session. The public log is served by Inertia, so asking for it
 * with the X-Inertia header returns the same JSON the web app renders, newest first with a cursor.
 *
 * Usage: php fetch.php [--hours=24] [--topics=topics.json] [--keywords=a,b,c] [--max-pages=100] [--no-replies] [--trending=10]
 * Output: JSON on stdout, progress on stderr.
 */

const BASE = 'https://dev-null.sh';
const USER_AGENT = 'devnull-insights-skill/1.0';

$options = getopt('', ['hours::', 'topics::', 'keywords::', 'max-pages::', 'no-replies', 'trending::']);
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
        $patterns[$topic][$keyword] = '/(?<![\p{L}\p{N}_])'.preg_quote(mb_strtolower((string) $keyword), '/').'(?![\p{L}\p{N}_])/u';
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
    $text = mb_strtolower(implode("\n", array_filter([
        $commit['body'] ?? '',
        $commit['link_preview']['url'] ?? '',
        $commit['link_preview']['title'] ?? '',
        $commit['link_preview']['description'] ?? '',
        $commit['quoted_commit']['body'] ?? '',
    ])));

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

echo json_encode([
    'source' => BASE.'/log/latest',
    'window' => ['from' => $cutoff->format(DATE_ATOM), 'to' => gmdate(DATE_ATOM), 'hours' => $hours],
    'scanned_posts' => count($posts),
    'complete_window' => $url === null,
    'topics' => array_keys($topics),
    'matched_count' => count($matched),
    'matched' => $matched,
    'trending_overall' => array_slice($all, 0, $trendingCount),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;

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
    $headers = [];
    $curl = curl_init(BASE.'/log/latest');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_HTTPHEADER => ['X-Inertia: true', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }

            return strlen($line);
        },
    ]);
    curl_exec($curl);

    return $headers['x-inertia-version'] ?? fail('Cannot detect the Inertia version of dev-null.sh');
}

/** @return list<string> */
function inertiaHeaders(string $version): array
{
    return ['X-Inertia: true', "X-Inertia-Version: {$version}", 'X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
}

function request(string $url, array $headers): array
{
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($status === 200 && is_string($body)) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                return $data;
            }
        }
        if ($status !== 429 && $status < 500) {
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
