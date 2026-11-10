<?php

declare(strict_types=1);

test('profiles.php saves, lists, shows and removes profiles', function (): void {
    $env = ['DEVNULL_INSIGHTS_HOME' => $home = profileHome()];

    $saved = runScript(PROFILES_SCRIPT, ['save', '--name=demo', '--repository=https://github.com/x/demo', '--keywords=demo,cli', '--weak-keywords=skills'], $env);
    $list = runScript(PROFILES_SCRIPT, ['list'], $env);
    $show = runScript(PROFILES_SCRIPT, ['show', '--name=demo'], $env);
    $removed = runScript(PROFILES_SCRIPT, ['remove', '--name=demo'], $env);
    $empty = runScript(PROFILES_SCRIPT, ['list'], $env);

    expect($saved['exit'])->toBe(0, $saved['stderr'])
        ->and($saved['json']['keywords'])->toBe(['demo', 'cli'])
        ->and($list['json']['path'])->toBe($home.DIRECTORY_SEPARATOR.'projects.json')
        ->and(array_column($list['json']['projects'], 'name'))->toBe(['demo'])
        ->and($show['json']['weak_keywords'])->toBe(['skills'])
        ->and($removed['json'])->toBe(['removed' => 'demo'])
        ->and($empty['json']['projects'])->toBe([]);
});

test('profiles.php reports errors with exit code 1', function (array $arguments, string $message): void {
    $run = runScript(PROFILES_SCRIPT, $arguments, ['DEVNULL_INSIGHTS_HOME' => profileHome()]);

    expect($run['exit'])->toBe(1)
        ->and($run['stderr'])->toContain($message);
})->with([
    [['show', '--name=nope'], 'No profile "nope"'],
    [['save', '--name=x'], 'needs a repository'],
    [['explode'], 'Unknown command "explode"'],
]);
