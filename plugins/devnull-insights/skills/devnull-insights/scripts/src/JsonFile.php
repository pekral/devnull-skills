<?php

declare(strict_types=1);

namespace DevnullInsights;

use JsonException;

/** Reads and writes the skill's own JSON files: profiles and run state. */
final class JsonFile
{
    /** @return array<mixed>|null null when the file does not exist */
    public static function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DevnullException("{$path} is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        return is_array($data) ? $data : throw new DevnullException("{$path} does not hold a JSON object");
    }

    /**
     * Writes a sibling file and renames it over the old one, so a crash never leaves half a file.
     *
     * @param  array<mixed>  $data
     */
    public static function write(string $path, array $data): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            throw new DevnullException("Cannot create {$directory}");
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        $temporary = $path.'.'.getmypid().'.tmp';

        if (@file_put_contents($temporary, $json) === false || ! @rename($temporary, $path)) {
            @unlink($temporary);

            throw new DevnullException("Cannot write {$path}");
        }
    }
}
