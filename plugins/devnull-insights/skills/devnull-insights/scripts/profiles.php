<?php

declare(strict_types=1);

/**
 * Manages the saved project profiles that `fetch.php --profile=…` reads. Output is JSON on stdout.
 *
 * Usage:
 *   php profiles.php list
 *   php profiles.php show --name=ai-olympus
 *   php profiles.php save --name=ai-olympus --repository=https://github.com/pekral/ai-olympus \
 *        --keywords="ai-olympus,claude code" [--weak-keywords="skills,rules"] [--exclude-keywords="hiring"] \
 *        [--topics=php,laravel,ai-coding] [--hours=24]
 *   php profiles.php remove --name=ai-olympus
 *
 * The file lives in $DEVNULL_INSIGHTS_HOME, else %APPDATA%\devnull-insights (Windows), else $XDG_CONFIG_HOME/devnull-insights,
 * else ~/.config/devnull-insights. `list` prints its path.
 */

use DevnullInsights\Arguments;
use DevnullInsights\DevnullException;
use DevnullInsights\ProfileStore;

require __DIR__.'/src/autoload.php';

$print = static fn (mixed $data): int => fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL) !== false ? 0 : 1;

try {
    $arguments = Arguments::parse(array_slice($argv, 1), ['name', 'repository', 'keywords', 'weak-keywords', 'exclude-keywords', 'topics', 'hours'], 'list');
    $store = ProfileStore::fromEnvironment(getenv());
    $name = $arguments->get('name');

    exit(match ($arguments->command) {
        'list' => $print(['path' => $store->path(), 'projects' => array_values($store->all())]),
        'show' => $print($store->get($name)),
        'save' => $print($store->save([
            'name' => $name,
            'repository' => $arguments->get('repository'),
            'keywords' => $arguments->get('keywords'),
            'weak_keywords' => $arguments->get('weak-keywords'),
            'exclude_keywords' => $arguments->get('exclude-keywords'),
            'topics' => $arguments->get('topics'),
            'hours' => $arguments->get('hours', (string) ProfileStore::DefaultHours),
        ])),
        'remove' => (static function () use ($store, $name, $print): int {
            $store->remove($name);

            return $print(['removed' => $name]);
        })(),
        default => throw new DevnullException("Unknown command \"{$arguments->command}\": use list, show, save or remove"),
    });
} catch (DevnullException $exception) {
    fwrite(STDERR, "error: {$exception->getMessage()}\n");
    exit(1);
}
