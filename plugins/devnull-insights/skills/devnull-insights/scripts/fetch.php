<?php

declare(strict_types=1);

/**
 * Reads the public "latest" log of https://dev-null.sh for the last N hours and keeps the posts that match the topics.
 *
 * Runs unchanged on Windows, macOS and Linux with PHP 8.3+ and ext-openssl (no ext-curl, no ext-mbstring, no shell tools).
 *
 * Usage: php fetch.php [--hours=24] [--topics=topics.json] [--keywords=a,b] [--weak-keywords=c,d] [--min-score=2] [--max-pages=100]
 *                      [--no-replies] [--trending=10] [--output=file.json]
 *        php fetch.php --profile=name[,name…]|all [--hours=N] …   (saved profiles, see profiles.php; one fetch for all)
 * Output: UTF-8 JSON to --output (or stdout), progress on stderr. Exit code 1 with an "error:" line on any failure.
 * Prefer --output: Windows PowerShell 5.1 re-encodes `>` redirects to UTF-16.
 *
 * Environment (for tests and the contract smoke test): DEVNULL_BASE_URL (default https://dev-null.sh),
 * DEVNULL_PAGE_DELAY_MS (default 400), DEVNULL_RETRY_DELAY seconds (default 5).
 */

use DevnullInsights\DevnullClient;
use DevnullInsights\DevnullException;
use DevnullInsights\FetchCommand;
use DevnullInsights\HttpClient;
use DevnullInsights\ProfileStore;

require __DIR__.'/src/autoload.php';

$env = static fn (string $name, string $default): string => is_string($value = getenv($name)) && $value !== '' ? $value : $default;

$client = new DevnullClient(
    new HttpClient('devnull-insights-skill/1.0', (float) $env('DEVNULL_RETRY_DELAY', '5')),
    rtrim($env('DEVNULL_BASE_URL', 'https://dev-null.sh'), '/'),
    (int) $env('DEVNULL_PAGE_DELAY_MS', '400'),
);

try {
    $profiles = ProfileStore::fromEnvironment(getenv());
} catch (DevnullException) {
    $profiles = null; // Only --profile needs it, and it reports the missing store itself.
}

exit((new FetchCommand($client, __DIR__.'/../topics.json', STDOUT, STDERR, $profiles))->run(getopt('', FetchCommand::Options)));
