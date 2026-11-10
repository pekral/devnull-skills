<?php

declare(strict_types=1);

$saveProfiles = static function (string $home): void {
    foreach ([
        ['--name=queues', '--repository=https://github.com/x/queues', '--keywords=queues', '--topics=php'],
        ['--name=pint', '--repository=/code/pint', '--keywords=coding standard', '--topics=laravel', '--exclude-keywords=hiking', '--hours=0.4'],
    ] as $arguments) {
        $run = runScript(PROFILES_SCRIPT, ['save', ...$arguments], ['DEVNULL_INSIGHTS_HOME' => $home]);
        expect($run['exit'])->toBe(0, $run['stderr']);
    }
};

test('several profiles share one fetch of the log', function () use ($saveProfiles): void {
    $saveProfiles($home = profileHome());

    $single = runFetch('count-single', ['--hours=24', '--no-replies']);
    $multi = runFetch('count-multi', ['--profile=all', '--no-replies'], ['DEVNULL_INSIGHTS_HOME' => $home]);

    expect($single['exit'])->toBe(0, $single['stderr'])
        ->and($multi['exit'])->toBe(0, $multi['stderr'])
        ->and(logRequests('count-multi'))->toBe(logRequests('count-single'))
        ->and(array_column($multi['json']['projects'], 'name'))->toBe(['pint', 'queues'])
        ->and($multi['json'])->not->toHaveKey('matched');
});

test('each profile applies its own keywords, topics, exclusions and time window', function () use ($saveProfiles): void {
    $saveProfiles($home = profileHome());

    $run = runFetch('ok', ['--profile=queues,pint'], ['DEVNULL_INSIGHTS_HOME' => $home]);
    [$queues, $pint] = $run['json']['projects'];

    expect($run['exit'])->toBe(0, $run['stderr'])
        ->and($run['json']['window']['hours'])->toEqual(24)
        ->and($queues['topics'])->toBe(['php', 'repository'])
        ->and(array_column($queues['matched'], 'author'))->toBe(['@alice', '@carol'])
        ->and($queues['matched'][0]['replies'])->toHaveCount(2)
        ->and($pint['hours'])->toEqual(0.4)
        ->and($pint['topics'])->toBe(['laravel', 'repository'])
        // @carol (30 minutes old) matches the profile's keyword but lies outside its 24-minute window.
        ->and(array_column($pint['matched'], 'author'))->toBe(['@alice'])
        // The same post in two projects carries the replies of one request.
        ->and($pint['matched'][0]['replies'])->toBe($queues['matched'][0]['replies']);
});

test('an excluded keyword drops the post and is counted', function (): void {
    $env = ['DEVNULL_INSIGHTS_HOME' => $home = profileHome()];
    runScript(PROFILES_SCRIPT, ['save', '--name=laravel', '--repository=x', '--keywords=laravel', '--exclude-keywords=queues'], $env);

    $project = runFetch('ok', ['--profile=laravel', '--no-replies'], $env)['json']['projects'][0];

    expect(array_column($project['matched'], 'author'))->toBe(['@carol'])
        ->and($project['filtered_out']['excluded'])->toBe(1);
});

test('profile runs fail clearly on conflicting or unknown input', function (array $arguments, string $message): void {
    $env = ['DEVNULL_INSIGHTS_HOME' => $home = profileHome()];
    runScript(PROFILES_SCRIPT, ['save', '--name=demo', '--repository=x', '--keywords=demo', '--topics=nonexistent'], $env);

    $run = runFetch('ok', $arguments, $env);

    expect($run['exit'])->toBe(1)
        ->and($run['stderr'])->toContain($message);
})->with([
    [['--profile=demo', '--keywords=x'], '--keywords and --weak-keywords cannot be combined with --profile'],
    [['--profile=missing'], 'No profile "missing"'],
    [['--profile=demo'], 'Profile "demo" names unknown topics: nonexistent'],
]);
