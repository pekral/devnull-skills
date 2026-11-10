<?php

declare(strict_types=1);

use DevnullInsights\Arguments;
use DevnullInsights\DevnullException;

test('a command and --name=value options are read in any order', function (): void {
    $arguments = Arguments::parse(['--name=demo', 'save', '--keywords=a,b', '--note='], ['name', 'keywords', 'note'], 'list');

    expect($arguments->command)->toBe('save')
        ->and($arguments->get('name'))->toBe('demo')
        ->and($arguments->get('keywords'))->toBe('a,b')
        ->and($arguments->has('note'))->toBeTrue()
        ->and($arguments->get('missing', 'fallback'))->toBe('fallback');
});

test('the default command applies when none is given', function (): void {
    expect(Arguments::parse([], [], 'list')->command)->toBe('list');
});

test('unknown options, bare flags and a second command are rejected', function (array $argv, string $message): void {
    expect(fn () => Arguments::parse($argv, ['name'], 'list'))->toThrow(DevnullException::class, $message);
})->with([
    [['--colour=red'], 'Unknown option --colour'],
    [['--name'], 'Unexpected argument "--name"'],
    [['save', 'list'], 'Unexpected argument "list"'],
]);
