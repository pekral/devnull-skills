<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * Matches posts against topic keywords: whole words only, case-insensitive, Unicode-aware, without ext-mbstring.
 *
 * A topic has strong and weak keywords. A strong keyword is specific enough to keep a post on its own ("laravel").
 * A weak keyword is too generic for that ("release", "skills"): it adds to the score of a post that a strong keyword
 * already keeps, and a post with weak hits only is dropped.
 *
 * A strong keyword found only in text the author did not write — the link preview or the quoted post — counts as weak:
 * "This sums me up!" quoting a Laravel post is not a post about Laravel.
 */
final readonly class TopicMatcher
{
    public const int StrongWeight = 2;

    public const int WeakWeight = 1;

    /** @param array<string, array{strong: array<string, string>, weak: array<string, string>}> $patterns topic => strength => keyword => regex */
    private function __construct(private array $patterns)
    {
    }

    /**
     * @param  array<string, list<string>|array{strong?: list<string>, weak?: list<string>}>  $topics  a plain list is all strong
     */
    public static function fromTopics(array $topics): self
    {
        $patterns = [];
        foreach (self::normalize($topics) as $topic => $keywords) {
            foreach ($keywords as $strength => $list) {
                $patterns[$topic][$strength] = [];
                foreach ($list as $keyword) {
                    $patterns[$topic][$strength][$keyword] = '/(?<![\p{L}\p{N}_])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}_])/iu';
                }
            }
        }

        return new self($patterns);
    }

    /**
     * @param  array<string, mixed>  $topics
     * @return array<string, array{strong: list<string>, weak: list<string>}>
     */
    public static function normalize(array $topics): array
    {
        $clean = static fn (mixed $list): array => array_values(array_unique(array_filter(
            array_map(static fn (mixed $keyword): string => trim((string) $keyword), is_array($list) ? $list : [$list]),
            static fn (string $keyword): bool => $keyword !== '',
        )));

        $normalized = [];
        foreach ($topics as $topic => $keywords) {
            $isSplit = is_array($keywords) && (array_key_exists('strong', $keywords) || array_key_exists('weak', $keywords));
            $normalized[(string) $topic] = [
                'strong' => $clean($isSplit ? ($keywords['strong'] ?? []) : $keywords),
                'weak' => $clean($isSplit ? ($keywords['weak'] ?? []) : []),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $commit
     * @return array{topics: array<string, list<string>>, strong: int, weak: int, score: int}
     */
    public function match(array $commit): array
    {
        $own = (string) ($commit['body'] ?? '');
        $context = self::context($commit);
        $topics = [];
        // Keyed by lower-cased keyword: a keyword listed under two topics counts once.
        $found = ['strong' => [], 'weak' => []];

        foreach ($this->patterns as $topic => $strengths) {
            foreach ($strengths as $strength => $keywordPatterns) {
                foreach ($keywordPatterns as $keyword => $pattern) {
                    $inOwn = preg_match($pattern, $own) === 1;
                    if ($inOwn || preg_match($pattern, $context) === 1) {
                        $topics[$topic][] = (string) $keyword;
                        $found[$strength === 'strong' && $inOwn ? 'strong' : 'weak'][strtolower((string) $keyword)] = true;
                    }
                }
            }
        }

        $strong = count($found['strong']);
        $weak = count(array_diff_key($found['weak'], $found['strong']));

        return ['topics' => $topics, 'strong' => $strong, 'weak' => $weak, 'score' => $strong * self::StrongWeight + $weak * self::WeakWeight];
    }

    /** Text the post shows but its author did not write. @param array<string, mixed> $commit */
    private static function context(array $commit): string
    {
        return implode("\n", array_filter([
            $commit['link_preview']['url'] ?? '',
            $commit['link_preview']['title'] ?? '',
            $commit['link_preview']['description'] ?? '',
            $commit['quoted_commit']['body'] ?? '',
        ], 'is_string'));
    }
}

