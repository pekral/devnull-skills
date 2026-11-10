<?php

declare(strict_types=1);

use DevnullInsights\DevnullException;
use DevnullInsights\ProfileStore;

test('the profile directory follows the platform convention', function (array $env, string $os, string $expected): void {
    expect(ProfileStore::fromEnvironment($env, $os)->path())->toBe($expected.DIRECTORY_SEPARATOR.'projects.json');
})->with([
    'override wins' => [['DEVNULL_INSIGHTS_HOME' => '/custom/', 'APPDATA' => 'C:\\Users\\a\\AppData\\Roaming', 'HOME' => '/home/a'], 'Linux', '/custom'],
    'Windows uses APPDATA' => [['APPDATA' => 'C:\\Users\\a\\AppData\\Roaming', 'USERPROFILE' => 'C:\\Users\\a'], 'Windows', 'C:\\Users\\a\\AppData\\Roaming\\devnull-insights'],
    'XDG config home' => [['XDG_CONFIG_HOME' => '/home/a/.cfg', 'HOME' => '/home/a'], 'Linux', '/home/a/.cfg/devnull-insights'],
    'home directory' => [['HOME' => '/Users/a'], 'Darwin', '/Users/a/.config/devnull-insights'],
    'APPDATA is ignored off Windows' => [['APPDATA' => '/weird', 'HOME' => '/home/a'], 'Linux', '/home/a/.config/devnull-insights'],
]);

test('without any known directory the store asks for DEVNULL_INSIGHTS_HOME', function (): void {
    ProfileStore::fromEnvironment([], 'Linux');
})->throws(DevnullException::class, 'set DEVNULL_INSIGHTS_HOME');

test('profiles are saved, listed by name, replaced, and removed', function (): void {
    $store = new ProfileStore(profileHome());

    expect($store->all())->toBe([]);

    $store->save(['name' => 'zeta', 'repository' => 'https://github.com/x/zeta', 'keywords' => 'zeta, zeta ,cli']);
    $store->save(['name' => 'alpha', 'repository' => '/code/alpha', 'keywords' => ['alpha'], 'weak_keywords' => 'skills', 'topics' => ['php'], 'hours' => '48']);
    $store->save(['name' => 'zeta', 'repository' => 'https://github.com/x/zeta', 'keywords' => 'zeta2']);

    expect(array_keys($store->all()))->toBe(['alpha', 'zeta'])
        ->and($store->get('zeta')['keywords'])->toBe(['zeta2'])
        ->and($store->get('alpha'))->toBe([
            'name' => 'alpha', 'repository' => '/code/alpha', 'keywords' => ['alpha'], 'weak_keywords' => ['skills'],
            'exclude_keywords' => [], 'topics' => ['php'], 'hours' => 48.0,
        ])
        ->and(glob(dirname($store->path()).'/*.tmp'))->toBe([]);

    $store->remove('zeta');

    expect(array_keys($store->all()))->toBe(['alpha']);
});

test('invalid profiles are rejected with a clear message', function (array $profile, string $message): void {
    expect(fn () => (new ProfileStore(profileHome()))->save($profile))->toThrow(DevnullException::class, $message);
})->with([
    [['name' => 'Bad Name', 'repository' => 'x', 'keywords' => 'a'], 'Invalid profile name'],
    [['name' => 'all', 'repository' => 'x', 'keywords' => 'a'], '"all" is reserved'],
    [['name' => 'api-2', 'repository' => 'x', 'keywords' => 'a'], 'must not end with -weekly or -<number>'],
    [['name' => 'ok', 'repository' => ' ', 'keywords' => 'a'], 'needs a repository'],
    [['name' => 'ok', 'repository' => 'x', 'keywords' => ' , '], 'needs at least one strong keyword'],
    [['name' => 'ok', 'repository' => 'x', 'keywords' => 'a', 'hours' => '0'], 'hours must be a positive number'],
]);

test('a broken profile file and an unknown profile fail loudly', function (): void {
    $directory = profileHome();
    $store = new ProfileStore($directory);

    expect(fn () => $store->get('missing'))->toThrow(DevnullException::class, 'No profile "missing"')
        ->and(fn () => $store->remove('missing'))->toThrow(DevnullException::class, 'No profile "missing"');

    mkdir($directory);
    file_put_contents($store->path(), '{broken');

    expect(fn () => $store->all())->toThrow(DevnullException::class, 'is not valid JSON');
});
