<?php

declare(strict_types=1);

use DevnullInsights\DevnullException;
use DevnullInsights\ProfileStore;
use DevnullInsights\StateStore;

beforeEach(function (): void {
    $this->states = StateStore::forProfiles(new ProfileStore(profileHome()));
});

test('a profile without a state file starts empty', function (): void {
    expect($this->states->load('demo'))->toBe(['profile' => 'demo', 'last_run_at' => null, 'seen' => [], 'proposals' => []]);
});

test('recording keeps the first time a post was seen, moves the last run forward, and forgets posts after 30 days', function (): void {
    $this->states->record('demo', ['old', 'a'], '2026-10-01T10:00:00+00:00');
    $this->states->record('demo', ['a', 'b'], '2026-11-10T10:00:00+00:00');
    $state = $this->states->record('demo', ['c'], '2026-11-09T10:00:00+00:00');

    expect($state['seen'])->toBe(['b' => '2026-11-10T10:00:00+00:00', 'c' => '2026-11-09T10:00:00+00:00'])
        ->and($state['last_run_at'])->toBe('2026-11-10T10:00:00+00:00')
        ->and($this->states->load('demo'))->toBe($state);
});

test('proposals get stable ids and keep their history of decisions', function (): void {
    $first = $this->states->addProposal('demo', 'Add a verify command', ['https://dev-null.sh/@a/commits/1', ' ']);
    $second = $this->states->addProposal('demo', 'Model tier table', ['https://dev-null.sh/@b/commits/2']);
    $updated = $this->states->updateProposal('demo', 'P1', 'rejected', ['https://dev-null.sh/@c/commits/3'], 'Out of scope', null);
    $linked = $this->states->updateProposal('demo', 'P2', issue: 'https://github.com/x/y/issues/9');

    expect([$first['id'], $second['id']])->toBe(['P1', 'P2'])
        ->and($first['status'])->toBe('new')
        ->and($first['sources'])->toBe(['https://dev-null.sh/@a/commits/1'])
        ->and($updated['status'])->toBe('rejected')
        ->and($updated['sources'])->toBe(['https://dev-null.sh/@a/commits/1', 'https://dev-null.sh/@c/commits/3'])
        ->and($updated['note'])->toBe('Out of scope')
        ->and($linked['status'])->toBe('new')
        ->and($linked['issue'])->toBe('https://github.com/x/y/issues/9')
        ->and(array_column($this->states->load('demo')['proposals'], 'id'))->toBe(['P1', 'P2']);
});

test('a hand-edited state with a broken last run time fails with the file name', function (): void {
    $path = $this->states->path('demo');
    mkdir(dirname($path), 0o777, true);
    file_put_contents($path, json_encode(['last_run_at' => 'yesterday-ish']));

    expect(fn () => $this->states->load('demo'))->toThrow(DevnullException::class, 'last_run_at must be an ISO 8601 time or null');
});

test('invalid proposal changes fail loudly', function (callable $change, string $message): void {
    $this->states->addProposal('demo', 'Existing', ['https://dev-null.sh/@a/commits/1']);

    expect(fn () => $change($this->states))->toThrow(DevnullException::class, $message);
})->with([
    [fn (StateStore $states) => $states->addProposal('demo', ' ', ['https://x']), 'needs a title'],
    [fn (StateStore $states) => $states->addProposal('demo', 'No source', []), 'needs at least one source post URL'],
    [fn (StateStore $states) => $states->updateProposal('demo', 'P9', 'done'), 'No proposal "P9"'],
    [fn (StateStore $states) => $states->updateProposal('demo', 'P1', 'maybe'), 'Unknown status "maybe"'],
    [fn (StateStore $states) => $states->load('../escape'), 'Invalid profile name'],
]);
