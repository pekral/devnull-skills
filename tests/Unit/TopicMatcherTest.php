<?php

declare(strict_types=1);

use DevnullInsights\TopicMatcher;

test('keywords match whole words, case-insensitively', function (string $body, bool $matches): void {
    $hits = TopicMatcher::fromTopics(['php' => ['php']])->match(['body' => $body]);

    expect($hits !== [])->toBe($matches);
})->with([
    ['I love PHP', true],
    ['php-fpm tuning', true],
    ['#php', true],
    ['phpstan level 9', false],
    ['cakephp', false],
]);

test('multi-word keywords and non-ASCII text match without ext-mbstring', function (): void {
    $matcher = TopicMatcher::fromTopics(['ai' => ['claude code'], 'cz' => ['příspěvek']]);

    expect($matcher->match(['body' => 'Using CLAUDE CODE daily']))->toBe(['ai' => ['claude code']])
        ->and($matcher->match(['body' => 'Nový PŘÍSPĚVEK']))->toBe(['cz' => ['příspěvek']]);
});

test('the link preview and the quoted post count as post text', function (): void {
    $matcher = TopicMatcher::fromTopics(['laravel' => ['laravel']]);

    expect($matcher->match(['body' => 'look', 'link_preview' => ['title' => 'Laravel 13']]))->toBe(['laravel' => ['laravel']])
        ->and($matcher->match(['body' => 'this', 'quoted_commit' => ['body' => 'laravel tip']]))->toBe(['laravel' => ['laravel']])
        ->and($matcher->match(['body' => 'nothing here', 'link_preview' => null]))->toBe([]);
});

test('blank keywords are ignored', function (): void {
    expect(TopicMatcher::fromTopics(['x' => ['', '  ']])->match(['body' => 'anything']))->toBe([]);
});
