<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * Matches posts against topic keywords: whole words only, case-insensitive, Unicode-aware, without ext-mbstring.
 */
final readonly class TopicMatcher
{
    /** @param array<string, array<string, string>> $patterns topic => keyword => regex */
    private function __construct(private array $patterns)
    {
    }

    /** @param array<string, list<string>> $topics */
    public static function fromTopics(array $topics): self
    {
        $patterns = [];
        foreach ($topics as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                $keyword = trim((string) $keyword);
                if ($keyword !== '') {
                    $patterns[(string) $topic][$keyword] = '/(?<![\p{L}\p{N}_])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}_])/iu';
                }
            }
        }

        return new self($patterns);
    }

    /**
     * @param  array<string, mixed>  $commit
     * @return array<string, list<string>> topic => matched keywords
     */
    public function match(array $commit): array
    {
        $text = self::text($commit);
        $hits = [];

        foreach ($this->patterns as $topic => $keywordPatterns) {
            foreach ($keywordPatterns as $keyword => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $hits[$topic][] = (string) $keyword;
                }
            }
        }

        return $hits;
    }

    /** @param array<string, mixed> $commit */
    private static function text(array $commit): string
    {
        return implode("\n", array_filter([
            $commit['body'] ?? '',
            $commit['link_preview']['url'] ?? '',
            $commit['link_preview']['title'] ?? '',
            $commit['link_preview']['description'] ?? '',
            $commit['quoted_commit']['body'] ?? '',
        ], 'is_string'));
    }
}
