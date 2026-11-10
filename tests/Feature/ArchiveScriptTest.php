<?php

declare(strict_types=1);

test('archive.php saves into the profile directory, lists and finds the latest report', function (): void {
    $env = ['DEVNULL_INSIGHTS_HOME' => $home = profileHome()];
    mkdir($home);
    file_put_contents("{$home}/report.md", "# Weekly\n");
    file_put_contents("{$home}/data.json", '{"topic_counts":{"laravel":4}}');

    $saved = runScript(ARCHIVE_SCRIPT, ['save', '--name=all-projects', '--kind=weekly', "--report={$home}/report.md", "--data={$home}/data.json", '--date=2026-11-10'], $env);
    $list = runScript(ARCHIVE_SCRIPT, ['list'], $env);
    $latest = runScript(ARCHIVE_SCRIPT, ['latest', '--name=all-projects', '--kind=weekly'], $env);
    $none = runScript(ARCHIVE_SCRIPT, ['latest', '--name=all-projects', '--kind=weekly', '--before=2026-11-10'], $env);

    expect($saved['exit'])->toBe(0, $saved['stderr'])
        ->and($saved['json']['report'])->toBe($home.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'2026-11-10-all-projects-weekly.md')
        ->and($list['json']['reports'])->toHaveCount(1)
        ->and($latest['json']['data'])->toEndWith('2026-11-10-all-projects-weekly.json')
        ->and($none['stdout'])->toBe("null\n");
});

test('archive.php writes today\'s date by default and accepts another directory', function (): void {
    $directory = profileHome();
    mkdir($directory);
    file_put_contents("{$directory}/report.md", '# Daily');

    $saved = runScript(ARCHIVE_SCRIPT, ['save', '--name=demo', "--report={$directory}/report.md", "--dir={$directory}/archive"]);

    expect($saved['exit'])->toBe(0, $saved['stderr'])
        ->and(basename($saved['json']['report']))->toBe(gmdate('Y-m-d').'-demo.md')
        ->and(dirname($saved['json']['report']))->toBe("{$directory}/archive");
});

test('fetch.php reports how many posts touch each topic', function (): void {
    $run = runFetch('ok', ['--hours=24', '--no-replies']);

    expect($run['json']['topic_counts'])->toBe(['laravel' => 2, 'php' => 1]);
});
