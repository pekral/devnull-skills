<?php

declare(strict_types=1);

namespace DevnullInsights;

/** The compact shape of a post in the report, and its ranking. */
final class Posts
{
    /**
     * @param  array<string, mixed>  $commit
     * @return array<string, mixed>
     */
    public static function summarize(array $commit, string $baseUrl): array
    {
        return array_filter([
            'id' => $commit['id'],
            'url' => $baseUrl.'/@'.$commit['author']['handle'].'/commits/'.$commit['id'],
            'author' => '@'.$commit['author']['handle'],
            'author_bio' => $commit['author']['bio'] ?? null,
            'created_at' => $commit['created_at'],
            'stars' => (int) ($commit['stars_count'] ?? 0),
            'replies_count' => (int) ($commit['replies_count'] ?? 0),
            'body' => $commit['body'] ?? '',
            'link' => $commit['link_preview']['url'] ?? null,
            'link_title' => $commit['link_preview']['title'] ?? null,
            'quoted' => $commit['quoted_commit']['body'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** @param array<string, mixed> $summary */
    public static function engagement(array $summary): int
    {
        return $summary['stars'] + 2 * $summary['replies_count'];
    }

    /**
     * @param  list<array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    public static function byEngagement(array $summaries): array
    {
        usort($summaries, fn (array $a, array $b): int => self::engagement($b) <=> self::engagement($a));

        return $summaries;
    }
}
