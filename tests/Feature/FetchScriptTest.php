<?php

declare(strict_types=1);

test('it reads the window page by page and stops at the cutoff', function (): void {
    $run = runFetch('ok', ['--hours=24']);

    expect($run['exit'])->toBe(0, $run['stderr'])
        ->and($run['json']['scanned_posts'])->toBe(3)
        ->and($run['json']['complete_window'])->toBeTrue()
        ->and($run['stderr'])->toContain('page 2: 3 posts so far');
});

test('it keeps posts that match a topic, ranked by engagement, with their replies', function (): void {
    $run = runFetch('ok', ['--hours=24']);
    $matched = $run['json']['matched'];

    expect(array_column($matched, 'author'))->toBe(['@alice', '@carol'])
        ->and($matched[0]['topics'])->toBe(['laravel' => ['laravel']])
        ->and($matched[0]['url'])->toEndWith('/ok/@alice/commits/p1')
        ->and($matched[0]['replies'])->toHaveCount(2)
        ->and($matched[0]['replies'][0])->toBe(['author' => '@erin', 'body' => 'Looks great, does it support Horizon?', 'stars' => 1])
        ->and($matched[1]['topics'])->toHaveKeys(['php', 'laravel'])
        ->and($matched[1]['link'])->toBe('https://example.com');
});

test('trending lists every scanned post by engagement, regardless of topics', function (): void {
    $run = runFetch('ok', ['--hours=24', '--trending=2']);

    expect(array_column($run['json']['trending_overall'], 'author'))->toBe(['@alice', '@bob']);
});

test('repository keywords become the "repository" topic', function (): void {
    $run = runFetch('ok', ['--hours=24', '--keywords=hiking, queues']);

    expect($run['json']['topics'])->toContain('repository')
        ->and(array_column($run['json']['matched'], 'author'))->toContain('@bob');
});

test('a page limit reports an incomplete window instead of pretending to be complete', function (): void {
    $run = runFetch('ok', ['--hours=24', '--max-pages=1']);

    expect($run['exit'])->toBe(0)
        ->and($run['json']['complete_window'])->toBeFalse()
        ->and($run['stderr'])->toContain('warning: stopped at --max-pages=1');
});

test('--output writes UTF-8 JSON to the file instead of stdout', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'devnull-output-');
    $run = runFetch('ok', ['--hours=24', "--output={$file}"]);
    $content = (string) file_get_contents($file);
    unlink($file);

    expect($run['exit'])->toBe(0)
        ->and($run['stdout'])->toBe('')
        ->and(substr($content, 0, 2))->not->toBe("\xFF\xFE")
        ->and(json_decode($content, true)['scanned_posts'])->toBe(3);
});

test('a 429 response is retried', function (): void {
    $run = runFetch('flaky', ['--hours=24']);

    expect($run['exit'])->toBe(0, $run['stderr'])
        ->and($run['json']['scanned_posts'])->toBe(3);
});

test('a version change during the run is followed', function (): void {
    $run = runFetch('redeploy', ['--hours=24']);

    expect($run['exit'])->toBe(0, $run['stderr'])
        ->and($run['json']['scanned_posts'])->toBe(3);
});

test('a failed replies request is reported on the post and does not stop the run', function (): void {
    $run = runFetch('badreplies', ['--hours=24']);

    expect($run['exit'])->toBe(0)
        ->and($run['json']['matched'][0]['replies_error'])->toContain('HTTP 500')
        ->and($run['stderr'])->toContain('warning: replies of p1');
});

test('a changed log response fails loudly instead of returning an empty result', function (): void {
    $run = runFetch('changed', ['--hours=24']);

    expect($run['exit'])->toBe(1)
        ->and($run['stdout'])->toBe('')
        ->and($run['stderr'])->toContain('error: dev-null.sh changed: the log response has no props.commits.data list');
});

test('a missing Inertia version fails loudly', function (): void {
    $run = runFetch('noversion', ['--hours=24']);

    expect($run['exit'])->toBe(1)
        ->and($run['stderr'])->toContain('error: dev-null.sh changed: no x-inertia-version header');
});

test('invalid options fail with a clear message', function (string $option, string $message): void {
    $run = runFetch('ok', [$option]);

    expect($run['exit'])->toBe(1)
        ->and($run['stderr'])->toContain($message);
})->with([
    ['--hours=abc', '--hours must be a positive number'],
    ['--hours=0', '--hours must be a positive number'],
    ['--max-pages=-1', '--max-pages must be a positive number'],
    ['--topics=missing.json', 'Cannot read topics from missing.json'],
]);
