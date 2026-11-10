<?php

declare(strict_types=1);

test('every manifest and topics file is valid JSON', function (string $path): void {
    json_decode((string) file_get_contents(dirname(__DIR__, 2).'/'.$path), flags: JSON_THROW_ON_ERROR);

    expect(true)->toBeTrue();
})->with([
    '.claude-plugin/marketplace.json',
    '.agents/plugins/marketplace.json',
    'plugins/devnull-insights/.claude-plugin/plugin.json',
    'plugins/devnull-insights/.codex-plugin/plugin.json',
    'plugins/devnull-insights/skills/devnull-insights/topics.json',
]);

test('both marketplaces list a plugin directory that exists', function (string $path): void {
    $root = dirname(__DIR__, 2);
    $marketplace = json_decode((string) file_get_contents("{$root}/{$path}"), true);

    foreach ($marketplace['plugins'] as $plugin) {
        $source = is_array($plugin['source']) ? $plugin['source']['path'] : $plugin['source'];
        expect(is_dir("{$root}/{$source}"))->toBeTrue("{$plugin['name']} -> {$source}")
            ->and(is_file("{$root}/{$source}/skills/{$plugin['name']}/SKILL.md"))->toBeTrue();
    }
})->with(['.claude-plugin/marketplace.json', '.agents/plugins/marketplace.json']);
