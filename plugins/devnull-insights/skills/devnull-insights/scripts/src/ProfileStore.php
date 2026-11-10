<?php

declare(strict_types=1);

namespace DevnullInsights;

use JsonException;

/**
 * Saved project profiles in `projects.json`, outside the plugin so an update never overwrites them.
 *
 * Location: $DEVNULL_INSIGHTS_HOME, else %APPDATA%\devnull-insights on Windows, else $XDG_CONFIG_HOME/devnull-insights,
 * else ~/.config/devnull-insights.
 *
 * @phpstan-type Profile array{name: string, repository: string, keywords: list<string>, weak_keywords: list<string>, exclude_keywords: list<string>, topics: list<string>, hours: float}
 */
final readonly class ProfileStore
{
    public const float DefaultHours = 24.0;

    private const string File = 'projects.json';

    public function __construct(private string $directory)
    {
    }

    /** @param array<string, string> $env */
    public static function fromEnvironment(array $env, string $osFamily = PHP_OS_FAMILY): self
    {
        $value = static fn (string $name): ?string => isset($env[$name]) && $env[$name] !== '' ? rtrim($env[$name], '/\\') : null;

        $directory = $value('DEVNULL_INSIGHTS_HOME')
            ?? ($osFamily === 'Windows' && $value('APPDATA') !== null ? $value('APPDATA').'\\devnull-insights' : null)
            ?? ($value('XDG_CONFIG_HOME') !== null ? $value('XDG_CONFIG_HOME').'/devnull-insights' : null)
            ?? (($value('HOME') ?? $value('USERPROFILE')) !== null ? ($value('HOME') ?? $value('USERPROFILE')).'/.config/devnull-insights' : null);

        if ($directory === null) {
            throw new DevnullException('Cannot find a config directory: set DEVNULL_INSIGHTS_HOME');
        }

        return new self($directory);
    }

    public function path(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.self::File;
    }

    /** @return array<string, Profile> by name */
    public function all(): array
    {
        if (! is_file($this->path())) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($this->path()), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DevnullException("{$this->path()} is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        $profiles = [];
        foreach ((array) ($data['projects'] ?? []) as $profile) {
            $profile = self::validate((array) $profile);
            $profiles[$profile['name']] = $profile;
        }
        ksort($profiles);

        return $profiles;
    }

    /** @return Profile */
    public function get(string $name): array
    {
        return $this->all()[$name] ?? throw new DevnullException("No profile \"{$name}\" in {$this->path()}");
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return Profile the saved profile
     */
    public function save(array $profile): array
    {
        $profile = self::validate($profile);
        $profiles = $this->all();
        $profiles[$profile['name']] = $profile;
        $this->write($profiles);

        return $profile;
    }

    public function remove(string $name): void
    {
        $profiles = $this->all();
        if (! isset($profiles[$name])) {
            throw new DevnullException("No profile \"{$name}\" in {$this->path()}");
        }

        unset($profiles[$name]);
        $this->write($profiles);
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return Profile
     */
    public static function validate(array $profile): array
    {
        $name = trim((string) ($profile['name'] ?? ''));
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name) !== 1) {
            throw new DevnullException("Invalid profile name \"{$name}\": use lower-case letters, digits, '.', '_' and '-'");
        }
        if ($name === 'all') {
            throw new DevnullException('"all" is reserved: fetch.php --profile=all selects every profile');
        }

        $repository = trim((string) ($profile['repository'] ?? ''));
        if ($repository === '') {
            throw new DevnullException("Profile \"{$name}\" needs a repository (URL or local path)");
        }

        $hours = $profile['hours'] ?? self::DefaultHours;
        if (! is_numeric($hours) || (float) $hours <= 0) {
            throw new DevnullException("Profile \"{$name}\": hours must be a positive number");
        }

        $list = static fn (mixed $value): array => array_values(array_unique(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), is_array($value) ? $value : explode(',', (string) $value)),
            static fn (string $item): bool => $item !== '',
        )));

        $keywords = $list($profile['keywords'] ?? []);
        if ($keywords === []) {
            throw new DevnullException("Profile \"{$name}\" needs at least one strong keyword");
        }

        return [
            'name' => $name,
            'repository' => $repository,
            'keywords' => $keywords,
            'weak_keywords' => $list($profile['weak_keywords'] ?? []),
            'exclude_keywords' => $list($profile['exclude_keywords'] ?? []),
            'topics' => $list($profile['topics'] ?? []),
            'hours' => (float) $hours,
        ];
    }

    /** @param array<string, Profile> $profiles */
    private function write(array $profiles): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o777, true) && ! is_dir($this->directory)) {
            throw new DevnullException("Cannot create {$this->directory}");
        }

        ksort($profiles);
        $json = json_encode(['projects' => array_values($profiles)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

        // Write a sibling file and rename it over the old one, so a crash never leaves half a file.
        $temporary = $this->path().'.'.getmypid().'.tmp';
        if (@file_put_contents($temporary, $json) === false || ! @rename($temporary, $this->path())) {
            @unlink($temporary);

            throw new DevnullException("Cannot write {$this->path()}");
        }
    }
}
