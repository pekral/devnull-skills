<?php

declare(strict_types=1);

use DevnullInsights\TopicMatcher;

test('keywords match whole words, case-insensitively', function (string $body, bool $matches): void {
    $match = TopicMatcher::fromTopics(['php' => ['php']])->match(['body' => $body]);

    expect($match['topics'] !== [])->toBe($matches);
})->with([
    ['I love PHP', true],
    ['php-fpm tuning', true],
    ['#php', true],
    ['phpstan level 9', false],
    ['cakephp', false],
]);

test('multi-word keywords and non-ASCII text match without ext-mbstring', function (): void {
    $matcher = TopicMatcher::fromTopics(['ai' => ['claude code'], 'cz' => ['příspěvek']]);

    expect($matcher->match(['body' => 'Using CLAUDE CODE daily'])['topics'])->toBe(['ai' => ['claude code']])
        ->and($matcher->match(['body' => 'Nový PŘÍSPĚVEK'])['topics'])->toBe(['cz' => ['příspěvek']]);
});

test('the link preview and the quoted post count as post text', function (): void {
    $matcher = TopicMatcher::fromTopics(['laravel' => ['laravel']]);

    expect($matcher->match(['body' => 'look', 'link_preview' => ['title' => 'Laravel 13']])['topics'])->toBe(['laravel' => ['laravel']])
        ->and($matcher->match(['body' => 'this', 'quoted_commit' => ['body' => 'laravel tip']])['topics'])->toBe(['laravel' => ['laravel']])
        ->and($matcher->match(['body' => 'nothing here', 'link_preview' => null])['topics'])->toBe([]);
});

test('a strong keyword found only in the link preview or the quoted post counts as weak', function (): void {
    $matcher = TopicMatcher::fromTopics(['laravel' => ['laravel']]);

    $quoting = $matcher->match(['body' => 'This sums me up', 'quoted_commit' => ['body' => 'Laravel tip']]);
    $writing = $matcher->match(['body' => 'My Laravel tip', 'link_preview' => ['title' => 'Laravel docs']]);

    expect($quoting['topics'])->toBe(['laravel' => ['laravel']])
        ->and([$quoting['strong'], $quoting['weak']])->toBe([0, 1])
        ->and([$writing['strong'], $writing['weak']])->toBe([1, 0]);
});

test('a plain keyword list is strong; the split form separates strong and weak keywords', function (): void {
    expect(TopicMatcher::normalize([
        'plain' => ['laravel', ' ', 'laravel'],
        'split' => ['strong' => ['mcp'], 'weak' => ['skills', '']],
        'weak only' => ['weak' => ['release']],
    ]))->toBe([
        'plain' => ['strong' => ['laravel'], 'weak' => []],
        'split' => ['strong' => ['mcp'], 'weak' => ['skills']],
        'weak only' => ['strong' => [], 'weak' => ['release']],
    ]);
});

test('the score weighs strong keywords above weak ones and counts a keyword once', function (string $body, int $strong, int $weak): void {
    $matcher = TopicMatcher::fromTopics([
        'ai' => ['strong' => ['claude'], 'weak' => ['skills', 'release']],
        'oss' => ['strong' => ['open source'], 'weak' => ['release']],
    ]);
    $match = $matcher->match(['body' => $body]);

    expect($match['strong'])->toBe($strong)
        ->and($match['weak'])->toBe($weak)
        ->and($match['score'])->toBe($strong * TopicMatcher::StrongWeight + $weak * TopicMatcher::WeakWeight);
})->with([
    'weak only' => ['I cracked the code with my skills', 0, 1],
    'one strong' => ['Claude helped', 1, 0],
    'strong and weak' => ['Claude skills release', 1, 2],
    'shared weak keyword counts once' => ['open source release', 1, 1],
]);
