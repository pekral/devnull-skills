<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * Collapses a run of near-identical posts by one author ("Merged in reelhouse: feat: …" thirty times) into its most
 * engaging post, so one bot or one busy changelog does not flood the report.
 *
 * Two posts belong to one series when they have the same author and the same first three words.
 */
final class Series
{
    public const int MinSize = 3;

    private const int PrefixWords = 3;

    /**
     * @param  list<array<string, mixed>>  $summaries  ranked best first, as Posts::byEngagement returns them
     * @return array{entries: list<array<string, mixed>>, merged: int}  merged = posts folded into another entry
     */
    public static function collapse(array $summaries): array
    {
        $groups = [];
        foreach ($summaries as $index => $summary) {
            $groups[self::key($summary) ?? "single:{$index}"][] = $index;
        }

        $entries = [];
        $merged = 0;
        foreach ($groups as $indexes) {
            $first = $summaries[$indexes[0]];
            if (count($indexes) >= self::MinSize) {
                $first['series_count'] = count($indexes);
                $first['series_ids'] = array_values(array_filter(array_map(static fn (int $index): ?string => $summaries[$index]['id'] ?? null, $indexes)));
                $merged += count($indexes) - 1;
                $entries[] = $first;
                continue;
            }
            foreach ($indexes as $index) {
                $entries[] = $summaries[$index];
            }
        }

        return ['entries' => Posts::byEngagement($entries), 'merged' => $merged];
    }

    /** @param array<string, mixed> $summary */
    private static function key(array $summary): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', strtolower((string) ($summary['body'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) >= self::PrefixWords
            ? $summary['author'].'|'.implode(' ', array_slice($words, 0, self::PrefixWords))
            : null;
    }
}
