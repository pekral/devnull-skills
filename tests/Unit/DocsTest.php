<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

test('every file docs/scheduling.md links to exists', function () use ($root): void {
    preg_match_all('/\]\((examples\/[^)]+)\)/', (string) file_get_contents("{$root}/docs/scheduling.md"), $links);

    expect($links[1])->not->toBeEmpty();
    foreach ($links[1] as $link) {
        expect(is_file("{$root}/docs/{$link}"))->toBeTrue($link);
    }
});

test('every scheduler example runs unattended, cannot wait for a prompt, and allows the skill scripts', function (string $file) use ($root): void {
    expect((string) file_get_contents("{$root}/docs/examples/{$file}"))
        ->toContain('unattended')
        ->toContain('dontAsk')
        ->toContain('Bash(php *)');
})->with(['crontab.txt', 'cz.pekral.devnull-insights.plist', 'register-windows-task.ps1']);
