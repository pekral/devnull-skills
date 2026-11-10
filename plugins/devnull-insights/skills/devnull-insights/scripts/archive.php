<?php

declare(strict_types=1);

/**
 * Keeps dated copies of finished reports. Output is JSON on stdout. Nothing is overwritten.
 *
 * Usage:
 *   php archive.php save --name=ai-olympus --report=report.md [--data=devnull.json] [--kind=daily|weekly] [--date=YYYY-MM-DD] [--dir=PATH]
 *   php archive.php list [--name=ai-olympus] [--kind=weekly] [--dir=PATH]
 *   php archive.php latest --name=ai-olympus --kind=weekly [--before=YYYY-MM-DD] [--dir=PATH]
 *
 * The archive is <profile directory>/reports unless --dir names another directory. --date defaults to today (UTC).
 */

use DevnullInsights\Archive;
use DevnullInsights\Arguments;
use DevnullInsights\DevnullException;
use DevnullInsights\ProfileStore;

require __DIR__.'/src/autoload.php';

$print = static fn (mixed $data): int => fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL) !== false ? 0 : 1;

try {
    $arguments = Arguments::parse(array_slice($argv, 1), ['name', 'report', 'data', 'kind', 'date', 'dir', 'before'], 'list');
    $archive = $arguments->get('dir') !== '' ? new Archive($arguments->get('dir')) : Archive::forProfiles(ProfileStore::fromEnvironment(getenv()));
    $optional = static fn (string $name): ?string => $arguments->get($name) !== '' ? $arguments->get($name) : null;

    exit(match ($arguments->command) {
        'save' => $print($archive->save(
            $arguments->get('name'),
            $arguments->get('kind', 'daily'),
            $arguments->get('report'),
            $optional('data'),
            $arguments->get('date', gmdate('Y-m-d')),
        )),
        'list' => $print(['directory' => $archive->directory(), 'reports' => $archive->entries($optional('name'), $optional('kind'))]),
        'latest' => $print($archive->latest($arguments->get('name'), $arguments->get('kind', 'daily'), $optional('before'))),
        default => throw new DevnullException("Unknown command \"{$arguments->command}\": use save, list or latest"),
    });
} catch (DevnullException $exception) {
    fwrite(STDERR, "error: {$exception->getMessage()}\n");
    exit(1);
}
