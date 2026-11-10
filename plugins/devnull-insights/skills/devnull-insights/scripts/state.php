<?php

declare(strict_types=1);

/**
 * The memory of one profile between runs: reported posts, the last run, and proposals with the user's decisions.
 * Output is JSON on stdout. The state file is `<profile directory>/state/<profile>.json` and can be edited by hand.
 *
 * Usage:
 *   php state.php show --profile=ai-olympus
 *   php state.php record --profile=ai-olympus --input=devnull.json       (after the report: remember its posts and time)
 *   php state.php proposal-add --profile=ai-olympus --title="…" --sources=https://dev-null.sh/@a/commits/1,… [--note="…"]
 *   php state.php proposal-set --profile=ai-olympus --id=P3 [--status=new|accepted|done|rejected] [--sources=…] [--note="…"] [--issue=URL]
 */

use DevnullInsights\Arguments;
use DevnullInsights\DevnullException;
use DevnullInsights\JsonFile;
use DevnullInsights\ProfileStore;
use DevnullInsights\StateStore;

require __DIR__.'/src/autoload.php';

$print = static fn (mixed $data): int => fwrite(STDOUT, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL) !== false ? 0 : 1;
$list = static fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));

try {
    $arguments = Arguments::parse(array_slice($argv, 1), ['profile', 'input', 'title', 'sources', 'note', 'id', 'status', 'issue'], 'show');
    $profiles = ProfileStore::fromEnvironment(getenv());
    $profile = $profiles->get($arguments->get('profile'))['name'];
    $states = StateStore::forProfiles($profiles);

    exit(match ($arguments->command) {
        'show' => $print($states->load($profile)),
        'record' => (static function () use ($arguments, $states, $profile, $print): int {
            $input = $arguments->get('input');
            $report = JsonFile::read($input) ?? throw new DevnullException("Cannot read {$input}");
            $project = array_values(array_filter((array) ($report['projects'] ?? []), static fn (mixed $project): bool => is_array($project) && ($project['name'] ?? null) === $profile))[0]
                ?? throw new DevnullException("{$input} has no project \"{$profile}\": record the output of fetch.php --profile");

            $ids = [];
            foreach ((array) ($project['matched'] ?? []) as $entry) {
                $ids = [...$ids, (string) ($entry['id'] ?? ''), ...array_map('strval', (array) ($entry['series_ids'] ?? []))];
            }
            $state = $states->record($profile, array_values(array_unique(array_filter($ids))), (string) ($report['window']['to'] ?? gmdate(DATE_ATOM)));

            return $print(['profile' => $profile, 'recorded' => count(array_unique(array_filter($ids))), 'last_run_at' => $state['last_run_at'], 'seen' => count($state['seen'])]);
        })(),
        'proposal-add' => $print($states->addProposal($profile, $arguments->get('title'), $list($arguments->get('sources')), $arguments->get('note'))),
        'proposal-set' => $print($states->updateProposal(
            $profile,
            $arguments->get('id'),
            $arguments->has('status') ? $arguments->get('status') : null,
            $list($arguments->get('sources')),
            $arguments->has('note') ? $arguments->get('note') : null,
            $arguments->has('issue') ? $arguments->get('issue') : null,
        )),
        default => throw new DevnullException("Unknown command \"{$arguments->command}\": use show, record, proposal-add or proposal-set"),
    });
} catch (DevnullException $exception) {
    fwrite(STDERR, "error: {$exception->getMessage()}\n");
    exit(1);
}
