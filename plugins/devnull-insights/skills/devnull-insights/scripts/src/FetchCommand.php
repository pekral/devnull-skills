<?php

declare(strict_types=1);

namespace DevnullInsights;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * Builds the JSON the skill reads: the posts of the last N hours that match the topics, with their replies, plus the
 * most engaging posts overall.
 */
final readonly class FetchCommand
{
    public const array Options = ['hours::', 'topics::', 'keywords::', 'weak-keywords::', 'min-score::', 'max-pages::', 'no-replies', 'trending::', 'output::'];

    /**
     * @param  resource  $stdout
     * @param  resource  $stderr
     */
    public function __construct(private DevnullClient $client, private string $defaultTopicsFile, private mixed $stdout, private mixed $stderr)
    {
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
        $hours = $this->number($options, 'hours', 24);
        $maxPages = (int) $this->number($options, 'max-pages', 100);
        $trending = (int) $this->number($options, 'trending', 10, allowZero: true);
        $minScore = (int) $this->number($options, 'min-score', TopicMatcher::StrongWeight);
        $matcher = TopicMatcher::fromTopics($topics = $this->topics($options));

        $cutoff = (new DateTimeImmutable('@'.(time() - (int) round($hours * 3600))))->setTimezone(new DateTimeZone('UTC'));
        $window = $this->client->window($cutoff, $maxPages, function (int $page, int $posts): void {
            fwrite($this->stderr, "page {$page}: {$posts} posts so far\n");
        });

        if (! $window['complete']) {
            fwrite($this->stderr, "warning: stopped at --max-pages={$maxPages} before reaching the {$hours}h cutoff\n");
        }

        $candidates = [];
        $filtered = ['weak_only' => 0, 'below_min_score' => 0, 'series_merged' => 0];
        foreach ($window['posts'] as $commit) {
            $match = $matcher->match($commit);
            if ($match['topics'] === []) {
                continue;
            }
            if ($match['strong'] === 0) {
                $filtered['weak_only']++;
                continue;
            }
            if ($match['score'] < $minScore) {
                $filtered['below_min_score']++;
                continue;
            }

            $candidates[] = Posts::summarize($commit, $this->client->baseUrl()) + ['topics' => $match['topics'], 'score' => $match['score']];
        }

        ['entries' => $matched, 'merged' => $filtered['series_merged']] = Series::collapse(Posts::byEngagement($candidates));

        if (! isset($options['no-replies'])) {
            $matched = array_map(fn (array $entry): array => $entry['replies_count'] > 0 ? $this->withReplies($entry, (string) $entry['id']) : $entry, $matched);
        }

        $all = array_map(fn (array $commit): array => Posts::summarize($commit, $this->client->baseUrl()), array_values($window['posts']));

        $this->write($options, [
            'source' => $this->client->baseUrl().'/log/latest',
            'window' => ['from' => $cutoff->format(DATE_ATOM), 'to' => gmdate(DATE_ATOM), 'hours' => $hours],
            'scanned_posts' => count($window['posts']),
            'complete_window' => $window['complete'],
            'topics' => array_keys($topics),
            'min_score' => $minScore,
            'matched_count' => count($matched),
            'filtered_out' => ['total' => array_sum($filtered)] + $filtered,
            'matched' => $matched,
            'trending_overall' => array_slice(Series::collapse(Posts::byEngagement($all))['entries'], 0, $trending),
        ]);
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
