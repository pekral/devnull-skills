<?php

declare(strict_types=1);

namespace DevnullInsights;

use DateTimeImmutable;

/**
 * Picks the posts of one project out of the fetched window: inside its own time window, matching its topics with
 * enough score, not hitting an excluded keyword, with series merged. Several projects share one fetched window.
 */
final readonly class Selection
{
    public function __construct(
        private TopicMatcher $matcher,
        private int $minScore,
        private DateTimeImmutable $since,
        private ?TopicMatcher $exclude = null,
    ) {
    }

    /**
     * @param  array<string, array<string, mixed>>  $posts  the fetched window
     * @return array{matched: list<array<string, mixed>>, filtered: array{total: int, weak_only: int, below_min_score: int, excluded: int, series_merged: int}}
     */
    public function select(array $posts, string $baseUrl): array
    {
        $filtered = ['weak_only' => 0, 'below_min_score' => 0, 'excluded' => 0, 'series_merged' => 0];
        $candidates = [];

        foreach ($posts as $commit) {
            if (new DateTimeImmutable((string) $commit['created_at']) < $this->since) {
                continue;
            }

            $match = $this->matcher->match($commit);
            if ($match['topics'] === []) {
                continue;
            }

            $reason = match (true) {
                $match['strong'] === 0 => 'weak_only',
                $match['score'] < $this->minScore => 'below_min_score',
                $this->isExcluded($commit) => 'excluded',
                default => null,
            };
            if ($reason !== null) {
                $filtered[$reason]++;
                continue;
            }

            $candidates[] = Posts::summarize($commit, $baseUrl) + ['topics' => $match['topics'], 'score' => $match['score']];
        }

        ['entries' => $matched, 'merged' => $filtered['series_merged']] = Series::collapse(Posts::byEngagement($candidates));

        return ['matched' => $matched, 'filtered' => ['total' => array_sum($filtered)] + $filtered];
    }

    /** @param array<string, mixed> $commit */
    private function isExcluded(array $commit): bool
    {
        return $this->exclude !== null && $this->exclude->match($commit)['topics'] !== [];
    }
}
