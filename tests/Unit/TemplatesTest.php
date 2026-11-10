<?php

declare(strict_types=1);

$template = static fn (string $name): string => (string) file_get_contents(dirname(__DIR__, 2)."/plugins/devnull-insights/skills/devnull-insights/templates/{$name}");

test('the issue template carries every section the skill promises', function () use ($template): void {
    expect($template('issue.md'))->toContain(
        '## Why',
        '**Sources on dev-null.sh**',
        '- [@author](url)',
        '## Proposed change',
        '## Expected effect',
        '## Effort and risk',
        '## Acceptance criteria',
        '- [ ]',
    );
});

test('every template link to a post uses the [@author](url) source format', function (string $name) use ($template): void {
    preg_match_all('/\[@[^\]]+\]\(([^)]+)\)/', $template($name), $links);

    expect($links[1])->not->toBeEmpty()
        ->each->toBe('url');
})->with(['report.md', 'issue.md']);

test('SKILL.md points at templates that exist', function (): void {
    $root = dirname(__DIR__, 2).'/plugins/devnull-insights/skills/devnull-insights';
    preg_match_all('#templates/([a-z-]+\.md)#', (string) file_get_contents("{$root}/SKILL.md"), $matches);

    expect(array_unique($matches[1]))->not->toBeEmpty();
    foreach (array_unique($matches[1]) as $file) {
        expect(is_file("{$root}/templates/{$file}"))->toBeTrue($file);
    }
});
