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

test('every manifest of a plugin states the same version, and the changelog has a section for it', function (): void {
    $root = dirname(__DIR__, 2);
    $read = static fn (string $path): array => json_decode((string) file_get_contents("{$root}/{$path}"), true);
    $changelog = (string) file_get_contents("{$root}/CHANGELOG.md");

    foreach ($read('.claude-plugin/marketplace.json')['plugins'] as $entry) {
        $name = $entry['name'];
        $versions = [
            '.claude-plugin/marketplace.json' => $entry['version'] ?? null,
            "plugins/{$name}/.claude-plugin/plugin.json" => $read("plugins/{$name}/.claude-plugin/plugin.json")['version'] ?? null,
            "plugins/{$name}/.codex-plugin/plugin.json" => $read("plugins/{$name}/.codex-plugin/plugin.json")['version'] ?? null,
        ];

        expect(array_unique(array_values($versions)))->toHaveCount(1, "{$name}: ".json_encode($versions))
            ->and($versions['.claude-plugin/marketplace.json'])->toMatch('/^\d+\.\d+\.\d+$/')
            ->and($changelog)->toContain("## [{$versions['.claude-plugin/marketplace.json']}]");
    }
});

test('release-notes.php prints the changelog section of a version and refuses a missing one', function (): void {
    $root = dirname(__DIR__, 2);
    $version = json_decode((string) file_get_contents("{$root}/.claude-plugin/marketplace.json"), true)['plugins'][0]['version'];

    $notes = runScript("{$root}/bin/release-notes.php", [$version]);
    $missing = runScript("{$root}/bin/release-notes.php", ['99.0.0']);

    expect($notes['exit'])->toBe(0, $notes['stderr'])
        ->and($notes['stdout'])->toContain('### ')->not->toContain('## [')
        ->and($missing['exit'])->toBe(1)
        ->and($missing['stderr'])->toContain('no notes under ## [99.0.0]');
});
