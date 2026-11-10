<?php

declare(strict_types=1);

use DevnullInsights\DevnullException;
use DevnullInsights\JsonFile;

test('a file is written into a new directory as readable UTF-8 JSON, without leftovers', function (): void {
    $path = profileHome().'/nested/state.json';

    JsonFile::write($path, ['name' => 'příspěvek', 'url' => 'https://x/y']);

    expect(JsonFile::read($path))->toBe(['name' => 'příspěvek', 'url' => 'https://x/y'])
        ->and((string) file_get_contents($path))->toContain('"příspěvek"')->toContain('https://x/y')
        ->and(glob(dirname($path).'/*.tmp'))->toBe([]);
});

test('a missing file reads as null; broken JSON and a scalar fail loudly', function (): void {
    $directory = profileHome();
    mkdir($directory);

    expect(JsonFile::read("{$directory}/missing.json"))->toBeNull();

    file_put_contents("{$directory}/broken.json", '{');
    file_put_contents("{$directory}/scalar.json", '42');

    expect(fn () => JsonFile::read("{$directory}/broken.json"))->toThrow(DevnullException::class, 'is not valid JSON')
        ->and(fn () => JsonFile::read("{$directory}/scalar.json"))->toThrow(DevnullException::class, 'does not hold a JSON object');
});
