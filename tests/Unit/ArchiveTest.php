<?php

declare(strict_types=1);

use DevnullInsights\Archive;
use DevnullInsights\DevnullException;

beforeEach(function (): void {
    $this->directory = profileHome();
    $this->archive = new Archive($this->directory.'/reports');
    mkdir($this->directory);
    file_put_contents($this->report = $this->directory.'/report.md', "# Report\n");
    file_put_contents($this->data = $this->directory.'/devnull.json', json_encode(['topic_counts' => ['php' => 3]]));
});

test('reports are saved under their date and never overwritten', function (): void {
    $first = $this->archive->save('ai-olympus', 'daily', $this->report, $this->data, '2026-11-10');
    $second = $this->archive->save('ai-olympus', 'daily', $this->report, null, '2026-11-10');
    $weekly = $this->archive->save('ai-olympus', 'weekly', $this->report, $this->data, '2026-11-10');

    expect(basename($first['report']))->toBe('2026-11-10-ai-olympus.md')
        ->and(basename((string) $first['data']))->toBe('2026-11-10-ai-olympus.json')
        ->and(basename($second['report']))->toBe('2026-11-10-ai-olympus-2.md')
        ->and($second['data'])->toBeNull()
        ->and(basename($weekly['report']))->toBe('2026-11-10-ai-olympus-weekly.md')
        ->and(file_get_contents($first['report']))->toBe("# Report\n")
        ->and(json_decode((string) file_get_contents((string) $first['data']), true))->toBe(['topic_counts' => ['php' => 3]]);
});

test('entries are listed newest first and filtered by name and kind', function (): void {
    $this->archive->save('ai-olympus', 'daily', $this->report, null, '2026-11-09');
    $this->archive->save('ai-olympus', 'daily', $this->report, null, '2026-11-10');
    $this->archive->save('ai-olympus', 'daily', $this->report, null, '2026-11-10');
    $this->archive->save('ai-olympus', 'weekly', $this->report, $this->data, '2026-11-03');
    $this->archive->save('other', 'daily', $this->report, null, '2026-11-11');

    $daily = $this->archive->entries('ai-olympus', 'daily');

    expect(array_map(fn (array $entry): string => basename($entry['report']), $daily))
        ->toBe(['2026-11-10-ai-olympus-2.md', '2026-11-10-ai-olympus.md', '2026-11-09-ai-olympus.md'])
        ->and($this->archive->entries())->toHaveCount(5)
        ->and($this->archive->entries(kind: 'weekly')[0])->toMatchArray(['date' => '2026-11-03', 'name' => 'ai-olympus', 'kind' => 'weekly']);
});

test('latest finds the previous weekly report before a date', function (): void {
    $this->archive->save('ai-olympus', 'weekly', $this->report, $this->data, '2026-11-03');
    $this->archive->save('ai-olympus', 'weekly', $this->report, $this->data, '2026-11-10');

    expect($this->archive->latest('ai-olympus', 'weekly')['date'])->toBe('2026-11-10')
        ->and($this->archive->latest('ai-olympus', 'weekly', '2026-11-10')['date'])->toBe('2026-11-03')
        ->and($this->archive->latest('ai-olympus', 'weekly', '2026-11-03'))->toBeNull()
        ->and($this->archive->latest('nothing', 'daily'))->toBeNull();
});

test('invalid input fails loudly', function (array $call, string $message): void {
    $arguments = array_replace(['ai-olympus', 'daily', $this->report, null, '2026-11-10'], $call);

    expect(fn () => $this->archive->save(...$arguments))->toThrow(DevnullException::class, $message);
})->with([
    [[0 => '../x'], 'Invalid report name'],
    [[0 => 'demo-weekly'], 'must not end with -weekly or -<number>'],
    [[0 => 'api-2'], 'must not end with -weekly or -<number>'],
    [[1 => 'monthly'], 'Unknown kind "monthly"'],
    [[4 => '10.11.2026'], 'Invalid date'],
    [[2 => '/nonexistent/report.md'], 'Cannot read the report'],
    [[3 => '/nonexistent/data.json'], 'Cannot read the data'],
]);
