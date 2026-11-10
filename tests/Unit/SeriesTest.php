<?php

declare(strict_types=1);

use DevnullInsights\Series;

$post = static fn (string $author, string $body, int $stars = 0): array => [
    'author' => "@{$author}", 'body' => $body, 'stars' => $stars, 'replies_count' => 0, 'url' => "https://x/@{$author}/".md5($body),
];

test('three posts by one author with the same first three words collapse into the most engaging one', function () use ($post): void {
    $result = Series::collapse([
        $post('bot', 'Merged in reelhouse: feat: downloads', 2),
        $post('bot', 'Merged in reelhouse: fix: player'),
        $post('bot', 'Merged in reelhouse: feat: search'),
        $post('alice', 'Laravel tip of the day', 1),
    ]);

    expect($result['merged'])->toBe(2)
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0]['body'])->toBe('Merged in reelhouse: feat: downloads')
        ->and($result['entries'][0]['series_count'])->toBe(3)
        ->and($result['entries'][1])->not->toHaveKey('series_count');
});

test('two similar posts, other authors, and short posts stay separate', function () use ($post): void {
    $result = Series::collapse([
        $post('bot', 'Merged in reelhouse: one'),
        $post('bot', 'Merged in reelhouse: two'),
        $post('carol', 'Merged in reelhouse: three'),
        $post('dave', 'hi'),
        $post('dave', 'hi'),
        $post('dave', 'hi'),
    ]);

    expect($result['merged'])->toBe(0)
        ->and($result['entries'])->toHaveCount(6);
});
