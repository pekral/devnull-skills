<?php

declare(strict_types=1);

namespace DevnullInsights;

use DateTimeImmutable;
use Exception;

/**
 * What the skill remembers between runs of one profile: when it last reported, which posts it already reported, and
 * the proposals it made with the user's decision on each. One readable JSON file per profile, editable by hand:
 * `<profile directory>/state/<profile>.json`.
 *
 * @phpstan-type Proposal array{id: string, title: string, status: string, sources: list<string>, note: string, issue: string, created_at: string, updated_at: string}
 * @phpstan-type State array{profile: string, last_run_at: ?string, seen: array<string, string>, proposals: list<Proposal>}
 */
final readonly class StateStore
{
    public const array Statuses = ['new', 'accepted', 'done', 'rejected'];

    /** A post older than this cannot reappear in any window, so its id is forgotten. */
    private const int SeenDays = 30;

    public function __construct(private string $directory)
    {
    }

    public static function forProfiles(ProfileStore $profiles): self
    {
        return new self($profiles->directory().DIRECTORY_SEPARATOR.'state');
    }

    public function path(string $profile): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.ProfileStore::validateName($profile).'.json';
    }

    /** @return State */
    public function load(string $profile): array
    {
        $data = JsonFile::read($this->path($profile)) ?? [];

        return [
            'profile' => $profile,
            'last_run_at' => is_string($data['last_run_at'] ?? null) ? $data['last_run_at'] : null,
            'seen' => array_map('strval', array_filter((array) ($data['seen'] ?? []), 'is_scalar')),
            'proposals' => array_values(array_map(self::proposal(...), array_filter((array) ($data['proposals'] ?? []), 'is_array'))),
        ];
    }

    /**
     * Marks posts as reported and moves the last run to the end of the reported window.
     *
     * @param  list<string>  $ids
     * @return State
     */
    public function record(string $profile, array $ids, string $runAt): array
    {
        $runAt = self::time($runAt)->format(DATE_ATOM);
        $state = $this->load($profile);

        foreach ($ids as $id) {
            $state['seen'][$id] ??= $runAt;
        }

        $forgetBefore = self::time($runAt)->modify('-'.self::SeenDays.' days');
        $state['seen'] = array_filter($state['seen'], static fn (string $at): bool => self::time($at) >= $forgetBefore);
        $state['last_run_at'] = $state['last_run_at'] === null || self::time($state['last_run_at']) < self::time($runAt) ? $runAt : $state['last_run_at'];

        $this->save($state);

        return $state;
    }

    /**
     * @param  list<string>  $sources  dev-null post URLs the proposal rests on
     * @return Proposal
     */
    public function addProposal(string $profile, string $title, array $sources, string $note = ''): array
    {
        $title = trim($title);
        $sources = array_values(array_unique(array_filter(array_map('trim', $sources), static fn (string $url): bool => $url !== '')));

        if ($title === '') {
            throw new DevnullException('A proposal needs a title');
        }
        if ($sources === []) {
            throw new DevnullException('A proposal needs at least one source post URL');
        }

        $state = $this->load($profile);
        $numbers = array_map(static fn (array $proposal): int => (int) substr($proposal['id'], 1), $state['proposals']);
        $now = gmdate(DATE_ATOM);

        $proposal = self::proposal([
            'id' => 'P'.(max([0, ...$numbers]) + 1),
            'title' => $title,
            'status' => 'new',
            'sources' => $sources,
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $state['proposals'][] = $proposal;
        $this->save($state);

        return $proposal;
    }

    /**
     * Changes a proposal's status, and optionally adds sources, a note, or the issue created from it.
     *
     * @param  list<string>  $addSources
     * @return Proposal
     */
    public function updateProposal(string $profile, string $id, ?string $status = null, array $addSources = [], ?string $note = null, ?string $issue = null): array
    {
        if ($status !== null && ! in_array($status, self::Statuses, true)) {
            throw new DevnullException("Unknown status \"{$status}\": use ".implode(', ', self::Statuses));
        }

        $state = $this->load($profile);
        foreach ($state['proposals'] as $index => $proposal) {
            if ($proposal['id'] !== $id) {
                continue;
            }

            $proposal['status'] = $status ?? $proposal['status'];
            $proposal['sources'] = array_values(array_unique([...$proposal['sources'], ...array_filter(array_map('trim', $addSources))]));
            $proposal['note'] = $note ?? $proposal['note'];
            $proposal['issue'] = $issue ?? $proposal['issue'];
            $proposal['updated_at'] = gmdate(DATE_ATOM);

            $state['proposals'][$index] = $proposal;
            $this->save($state);

            return $proposal;
        }

        throw new DevnullException("No proposal \"{$id}\" for profile \"{$profile}\"");
    }

    /** @param State $state */
    private function save(array $state): void
    {
        ksort($state['seen']);
        JsonFile::write($this->path($state['profile']), $state);
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return Proposal
     */
    private static function proposal(array $proposal): array
    {
        return [
            'id' => (string) ($proposal['id'] ?? ''),
            'title' => (string) ($proposal['title'] ?? ''),
            'status' => in_array($proposal['status'] ?? null, self::Statuses, true) ? $proposal['status'] : 'new',
            'sources' => array_values(array_map('strval', (array) ($proposal['sources'] ?? []))),
            'note' => (string) ($proposal['note'] ?? ''),
            'issue' => (string) ($proposal['issue'] ?? ''),
            'created_at' => (string) ($proposal['created_at'] ?? ''),
            'updated_at' => (string) ($proposal['updated_at'] ?? ''),
        ];
    }

    private static function time(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new DevnullException("Invalid time \"{$value}\"", previous: $exception);
        }
    }
}
