<?php

declare(strict_types=1);

$setUp = static function (): array {
    $env = ['DEVNULL_INSIGHTS_HOME' => profileHome()];
    runScript(PROFILES_SCRIPT, ['save', '--name=demo', '--repository=x', '--keywords=queues,coding standard'], $env);

    return $env;
};

$fetchTo = static function (string $scenario, array $arguments, array $env): array {
    $file = $env['DEVNULL_INSIGHTS_HOME'].'/fetch-'.bin2hex(random_bytes(4)).'.json';
    $run = runFetch($scenario, [...$arguments, "--output={$file}"], $env);
    expect($run['exit'])->toBe(0, $run['stderr']);

    return ['file' => $file, 'json' => json_decode((string) file_get_contents($file), true)];
};

test('a second run over the same window reports no post twice', function () use ($setUp, $fetchTo): void {
    $env = $setUp();

    $first = $fetchTo('ok', ['--profile=demo', '--no-replies'], $env);
    $recorded = runScript(STATE_SCRIPT, ['record', '--profile=demo', "--input={$first['file']}"], $env);
    $second = $fetchTo('ok', ['--profile=demo', '--no-replies'], $env)['json']['projects'][0];
    $again = $fetchTo('ok', ['--profile=demo', '--no-replies', '--include-seen'], $env)['json']['projects'][0];

    expect(array_column($first['json']['projects'][0]['matched'], 'author'))->toBe(['@alice', '@carol'])
        ->and($recorded['exit'])->toBe(0, $recorded['stderr'])
        ->and($recorded['json']['recorded'])->toBe(2)
        ->and($recorded['json']['last_run_at'])->toBe($first['json']['window']['to'])
        ->and($second['matched'])->toBe([])
        ->and($second['filtered_out']['already_seen'])->toBe(2)
        ->and($second['last_run_at'])->toBe($first['json']['window']['to'])
        ->and(array_column($again['matched'], 'author'))->toBe(['@alice', '@carol']);
});

test('every post of a merged series is remembered', function () use ($fetchTo): void {
    $env = ['DEVNULL_INSIGHTS_HOME' => profileHome()];
    runScript(PROFILES_SCRIPT, ['save', '--name=reel', '--repository=x', '--keywords=pull request'], $env);

    $first = $fetchTo('noisy', ['--profile=reel', '--no-replies'], $env);
    runScript(STATE_SCRIPT, ['record', '--profile=reel', "--input={$first['file']}"], $env);
    $state = runScript(STATE_SCRIPT, ['show', '--profile=reel'], $env)['json'];

    $series = array_values(array_filter($first['json']['projects'][0]['matched'], fn (array $entry): bool => $entry['author'] === '@reelbot'));

    expect($series[0]['series_count'])->toBe(5)
        ->and(array_keys($state['seen']))->toContain('r1', 'r2', 'r3', 'r4', 'r5');
});

test('--since-last-run reads the window since the recorded run', function () use ($setUp, $fetchTo): void {
    $env = $setUp();
    $lastRun = gmdate(DATE_ATOM, time() - 25 * 60);
    file_put_contents($env['DEVNULL_INSIGHTS_HOME'].'/report.json', json_encode(['window' => ['to' => $lastRun], 'projects' => [['name' => 'demo', 'matched' => []]]]));
    runScript(STATE_SCRIPT, ['record', '--profile=demo', '--input='.$env['DEVNULL_INSIGHTS_HOME'].'/report.json'], $env);

    $project = $fetchTo('ok', ['--profile=demo', '--no-replies', '--since-last-run'], $env)['json']['projects'][0];

    // @alice (10 minutes old) is newer than the last run; @carol (30 minutes old) is older.
    expect($project['hours'])->toBeGreaterThan(0.4)->toBeLessThan(0.45)
        ->and(array_column($project['matched'], 'author'))->toBe(['@alice']);
});

test('proposals are added, decided, and listed in the next fetch', function () use ($setUp, $fetchTo): void {
    $env = $setUp();

    $added = runScript(STATE_SCRIPT, ['proposal-add', '--profile=demo', '--title=Add a verify command', '--sources=https://dev-null.sh/@a/commits/1'], $env);
    $rejected = runScript(STATE_SCRIPT, ['proposal-set', '--profile=demo', '--id=P1', '--status=rejected', '--note=Out of scope'], $env);
    $project = $fetchTo('ok', ['--profile=demo', '--no-replies'], $env)['json']['projects'][0];

    expect($added['json']['id'])->toBe('P1')
        ->and($rejected['json']['status'])->toBe('rejected')
        ->and($project['proposals'])->toHaveCount(1)
        ->and($project['proposals'][0])->toMatchArray(['id' => 'P1', 'title' => 'Add a verify command', 'status' => 'rejected', 'note' => 'Out of scope']);
});

test('state.php and the memory options fail clearly on wrong input', function (string $script, array $arguments, string $message) use ($setUp): void {
    $env = $setUp();
    $run = $script === 'state' ? runScript(STATE_SCRIPT, $arguments, $env) : runFetch('ok', $arguments, $env);

    expect($run['exit'])->toBe(1)
        ->and($run['stderr'])->toContain($message);
})->with([
    ['state', ['show', '--profile=missing'], 'No profile "missing"'],
    ['state', ['record', '--profile=demo', '--input=nope.json'], 'Cannot read nope.json'],
    ['state', ['proposal-add', '--profile=demo', '--title=X'], 'needs at least one source post URL'],
    ['state', ['proposal-set', '--profile=demo', '--id=P1', '--status=done'], 'No proposal "P1"'],
    ['fetch', ['--since-last-run'], '--since-last-run and --include-seen need --profile'],
]);
