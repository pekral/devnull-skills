<?php

declare(strict_types=1);

namespace DevnullInsights;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Builds the JSON the skill reads: the posts of the last N hours that match the topics, with their replies, plus the
 * most engaging posts overall.
 *
 * With --profile it reads the log once and selects the posts of each saved project profile from it.
 */
final readonly class FetchCommand
{
    /** --since-last-run never reaches further back than this, however old the last run is. */
    public const float MaxSinceLastRunHours = 168.0;

    public const array Options = ['hours::', 'topics::', 'keywords::', 'weak-keywords::', 'min-score::', 'profile::', 'since-last-run', 'include-seen', 'max-pages::', 'no-replies', 'trending::', 'output::'];

    /**
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    public function __construct(
        private DevnullClient $client,
        private string $defaultTopicsFile,
        private mixed $stdout,
        private mixed $stderr,
        private ?ProfileStore $profiles = null,
        private ?StateStore $states = null,
    ) {
    }

    /** @param array<string, string|false|list<string|false>> $options as returned by getopt() with self::Options */
    public function run(array $options): int
    {
        try {
            $this->execute($options);

            return 0;
        } catch (DevnullException $exception) {
            fwrite($this->stderr, "error: {$exception->getMessage()}\n");

            return 1;
        }
    }

    /** @param array<string, string|false|list<string|false>> $options */
    private function execute(array $options): void
    {
        $maxPages = (int) $this->number($options, 'max-pages', 100);
        $trending = (int) $this->number($options, 'trending', 10, allowZero: true);
        $minScore = (int) $this->number($options, 'min-score', TopicMatcher::StrongWeight);
        $topics = $this->topics($options);
        $profiles = $this->selectedProfiles($options);
        $now = time();

        // Each profile reads its own window and memory; the log is fetched once for the longest window.
        $plans = array_map(fn (array $profile): array => $this->plan($options, $profile, $now), $profiles);
        $hours = $this->number($options, 'hours', $plans === [] ? ProfileStore::DefaultHours : max(array_column($plans, 'hours')));
        $since = static fn (float $hours): DateTimeImmutable => (new DateTimeImmutable('@'.($now - (int) round($hours * 3600))))->setTimezone(new DateTimeZone('UTC'));

        $window = $this->client->window($since($hours), $maxPages, function (int $page, int $posts): void {
            fwrite($this->stderr, "page {$page}: {$posts} posts so far\n");
        });
        if (! $window['complete']) {
            fwrite($this->stderr, "warning: stopped at --max-pages={$maxPages} before reaching the {$hours}h cutoff\n");
        }

        $report = [
            'source' => $this->client->baseUrl().'/log/latest',
            'window' => ['from' => $since($hours)->format(DATE_ATOM), 'to' => gmdate(DATE_ATOM, $now), 'hours' => $hours],
            'scanned_posts' => count($window['posts']),
            'complete_window' => $window['complete'],
        ];

        $replies = [];
        if ($profiles === []) {
            $selected = (new Selection(TopicMatcher::fromTopics($topics), $minScore, $since($hours)))->select($window['posts'], $this->client->baseUrl());
            $report += [
                'topics' => array_keys($topics),
                'min_score' => $minScore,
                'matched_count' => count($selected['matched']),
                'filtered_out' => $selected['filtered'],
                'matched' => $this->withAllReplies($options, $selected['matched'], $replies),
            ];
        } else {
            $report['projects'] = [];
            foreach ($plans as ['profile' => $profile, 'hours' => $projectHours, 'state' => $state]) {
                $projectTopics = $this->projectTopics($topics, $profile);
                $exclude = $profile['exclude_keywords'] === [] ? null : TopicMatcher::fromTopics(['exclude' => $profile['exclude_keywords']]);
                $seen = isset($options['include-seen']) ? [] : $state['seen'];
                $selected = (new Selection(TopicMatcher::fromTopics($projectTopics), $minScore, $since($projectHours), $exclude, $seen))->select($window['posts'], $this->client->baseUrl());

                $report['projects'][] = [
                    'name' => $profile['name'],
                    'repository' => $profile['repository'],
                    'hours' => $projectHours,
                    'last_run_at' => $state['last_run_at'],
                    'topics' => array_keys($projectTopics),
                    'min_score' => $minScore,
                    'matched_count' => count($selected['matched']),
                    'filtered_out' => $selected['filtered'],
                    'matched' => $this->withAllReplies($options, $selected['matched'], $replies),
                    'proposals' => $state['proposals'],
                ];
            }
        }

        $all = array_map(fn (array $commit): array => Posts::summarize($commit, $this->client->baseUrl()), array_values($window['posts']));
        $report['trending_overall'] = array_slice(Series::collapse(Posts::byEngagement($all))['entries'], 0, $trending);

        $this->write($options, $report);
    }

    /**
     * The window and memory of one profile: --hours wins, then the time since its last run (with --since-last-run),
     * then its own hours.
     *
     * @param  array<string, string|false|list<string|false>>  $options
     * @param  array{name: string, hours: float}  $profile
     * @return array{profile: array<string, mixed>, hours: float, state: array{last_run_at: ?string, seen: array<string, string>, proposals: list<array<string, mixed>>}}
     */
    private function plan(array $options, array $profile, int $now): array
    {
        $state = $this->states?->load($profile['name']) ?? ['last_run_at' => null, 'seen' => [], 'proposals' => []];
        $hours = $profile['hours'];

        if ($this->string($options, 'hours') !== null) {
            $hours = $this->number($options, 'hours', $hours);
        } elseif (isset($options['since-last-run']) && $state['last_run_at'] !== null) {
            $sinceLastRun = ($now - (new DateTimeImmutable($state['last_run_at']))->getTimestamp()) / 3600;
            $hours = max(1 / 60, min(self::MaxSinceLastRunHours, $sinceLastRun));
            if ($sinceLastRun > self::MaxSinceLastRunHours) {
                fwrite($this->stderr, "warning: the last run of {$profile['name']} was ".round($sinceLastRun)."h ago; reading the last ".self::MaxSinceLastRunHours."h only\n");
            }
        }

        return ['profile' => $profile, 'hours' => round($hours, 4), 'state' => $state];
    }

    /**
     * @param  array<string, string|false|list<string|false>>  $options
     * @return list<array{name: string, repository: string, keywords: list<string>, weak_keywords: list<string>, exclude_keywords: list<string>, topics: list<string>, hours: float}>
     */
    private function selectedProfiles(array $options): array
    {
        $names = $this->string($options, 'profile');
        if ($names === null) {
            if (isset($options['since-last-run']) || isset($options['include-seen'])) {
                throw new DevnullException('--since-last-run and --include-seen need --profile: only profiles have a memory');
            }

            return [];
        }

        if ($this->string($options, 'keywords') !== null || $this->string($options, 'weak-keywords') !== null) {
            throw new DevnullException('--keywords and --weak-keywords cannot be combined with --profile: change the profile with profiles.php save');
        }

        $store = $this->profiles ?? throw new DevnullException('No profile store is configured');
        if ($names === 'all') {
            $profiles = array_values($store->all());

            return $profiles !== [] ? $profiles : throw new DevnullException("No profiles saved in {$store->path()}");
        }

        return array_map(
            static fn (string $name): array => $store->get($name),
            array_values(array_unique(array_filter(array_map('trim', explode(',', $names)), static fn (string $name): bool => $name !== ''))),
        );
    }

    /**
     * The profile's topics (all when it names none) plus its own keywords as topic "repository".
     *
     * @param  array<string, array{strong: list<string>, weak: list<string>}>  $topics
     * @param  array{name: string, keywords: list<string>, weak_keywords: list<string>, topics: list<string>}  $profile
     * @return array<string, array{strong: list<string>, weak: list<string>}>
     */
    private function projectTopics(array $topics, array $profile): array
    {
        $unknown = array_diff($profile['topics'], array_keys($topics));
        if ($unknown !== []) {
            throw new DevnullException("Profile \"{$profile['name']}\" names unknown topics: ".implode(', ', $unknown));
        }

        $chosen = $profile['topics'] === [] ? $topics : array_intersect_key($topics, array_flip($profile['topics']));

        return $chosen + ['repository' => ['strong' => $profile['keywords'], 'weak' => $profile['weak_keywords']]];
    }

    /**
     * Loads replies once per post, even when several projects list the same post.
     *
     * @param  array<string, string|false|list<string|false>>  $options
     * @param  list<array<string, mixed>>  $matched
     * @param  array<string, array<string, mixed>>  $cache  id => entry fields from the replies request
     * @return list<array<string, mixed>>
     */
    private function withAllReplies(array $options, array $matched, array &$cache): array
    {
        if (isset($options['no-replies'])) {
            return $matched;
        }

        return array_map(function (array $entry) use (&$cache): array {
            if ($entry['replies_count'] === 0) {
                return $entry;
            }

            $id = (string) $entry['id'];
            $cache[$id] ??= $this->withReplies([], $id);

            return $entry + $cache[$id];
        }, $matched);
    }

    /**
     * A failed replies request is reported on the entry and on stderr, so it is visible but does not drop the post.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function withReplies(array $entry, string $id): array
    {
        try {
            $entry['replies'] = $this->client->replies($id);
        } catch (DevnullException $exception) {
            fwrite($this->stderr, "warning: replies of {$id}: {$exception->getMessage()}\n");
            $entry['replies_error'] = $exception->getMessage();
        }

        return $entry;
    }

    /**
     * @param  array<string, string|false|list<string|false>>  $options
     * @return array<string, array{strong: list<string>, weak: list<string>}>
     */
    private function topics(array $options): array
    {
        $file = $this->string($options, 'topics') ?? $this->defaultTopicsFile;
        $json = is_file($file) ? file_get_contents($file) : false;

        try {
            $topics = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $topics = null;
        }

        if (! is_array($topics)) {
            throw new DevnullException("Cannot read topics from {$file}");
        }

        $strong = $this->string($options, 'keywords');
        $weak = $this->string($options, 'weak-keywords');
        if ($strong !== null || $weak !== null) {
            $topics['repository'] = ['strong' => explode(',', (string) $strong), 'weak' => explode(',', (string) $weak)];
        }

        return TopicMatcher::normalize($topics);
    }

    /**
     * @param  array<string, string|false|list<string|false>>  $options
     * @param  array<string, mixed>  $report
     */
    private function write(array $options, array $report): void
    {
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
        $output = $this->string($options, 'output');

        if ($output === null) {
            fwrite($this->stdout, $json);

            return;
        }

        // Written by PHP, not by a shell redirect: Windows PowerShell 5.1 re-encodes `>` output to UTF-16.
        if (@file_put_contents($output, $json) === false) {
            throw new DevnullException("Cannot write {$output}");
        }
        fwrite($this->stderr, "written to {$output}\n");
    }

    /** @param array<string, string|false|list<string|false>> $options */
    private function string(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;
        if (is_array($value)) {
            $value = end($value);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, string|false|list<string|false>> $options */
    private function number(array $options, string $name, float $default, bool $allowZero = false): float
    {
        $value = $this->string($options, $name);
        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value) || (float) $value < 0 || (! $allowZero && (float) $value == 0)) {
            throw new DevnullException("--{$name} must be a ".($allowZero ? 'non-negative' : 'positive')." number, got \"{$value}\"");
        }

        return (float) $value;
    }
}
