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

use DevnullInsights\DevnullException;
use DevnullInsights\ProfileStore;

require __DIR__.'/src/autoload.php';

$print = static fn (mixed $data): int => fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL) !== false ? 0 : 1;

try {
    // getopt() stops at the command word, so the arguments are read here: one command, then --key=value options.
    $command = 'list';
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--([a-z-]+)=(.*)$/s', $argument, $match) === 1) {
            $options[$match[1]] = $match[2];
        } elseif (! str_starts_with($argument, '-')) {
            $command = $argument;
        } else {
            throw new DevnullException("Unknown argument \"{$argument}\": options take the form --name=value");
        }
    }

    $allowed = ['name', 'repository', 'keywords', 'weak-keywords', 'exclude-keywords', 'topics', 'hours'];
    if (($unknown = array_diff(array_keys($options), $allowed)) !== []) {
        throw new DevnullException('Unknown option --'.implode(', --', $unknown));
    }
    $store = ProfileStore::fromEnvironment(getenv());
    $name = is_string($options['name'] ?? null) ? $options['name'] : '';

    exit(match ($command) {
        'list' => $print(['path' => $store->path(), 'projects' => array_values($store->all())]),
        'show' => $print($store->get($name)),
        'save' => $print($store->save([
            'name' => $name,
            'repository' => $options['repository'] ?? '',
            'keywords' => $options['keywords'] ?? '',
            'weak_keywords' => $options['weak-keywords'] ?? '',
            'exclude_keywords' => $options['exclude-keywords'] ?? '',
            'topics' => $options['topics'] ?? '',
            'hours' => $options['hours'] ?? ProfileStore::DefaultHours,
        ])),
        'remove' => (static function () use ($store, $name, $print): int {
            $store->remove($name);

            return $print(['removed' => $name]);
        })(),
        default => throw new DevnullException("Unknown command \"{$command}\": use list, show, save or remove"),
    });
} catch (DevnullException $exception) {
    fwrite(STDERR, "error: {$exception->getMessage()}\n");
    exit(1);
}
