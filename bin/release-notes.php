<?php

declare(strict_types=1);

/**
 * Prints the CHANGELOG.md section of one version, for `gh release create v<version> --notes-file -`.
 * Exits 1 when the section is missing or empty, so a release cannot go out without notes.
 *
 * Usage: php bin/release-notes.php 0.2.0 [CHANGELOG.md]
 */

$version = $argv[1] ?? '';
$file = $argv[2] ?? dirname(__DIR__).'/CHANGELOG.md';

if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
    fwrite(STDERR, "error: give a version like 0.2.0\n");
    exit(1);
}

$changelog = is_file($file) ? (string) file_get_contents($file) : '';
$pattern = '/^## \['.preg_quote($version, '/').'\][^\n]*\n(.*?)(?=^## \[|\z)/ms';

if (preg_match($pattern, $changelog, $match) !== 1 || trim($match[1]) === '') {
    fwrite(STDERR, "error: {$file} has no notes under ## [{$version}]\n");
    exit(1);
}

echo trim($match[1]).PHP_EOL;
