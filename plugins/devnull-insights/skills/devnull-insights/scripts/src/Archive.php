<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * Dated copies of finished reports, with the fetch data they came from, so a run can be read again and a weekly
 * report can compare itself with the previous week. Nothing is ever overwritten: a second report on the same day
 * gets the suffix -2, -3 …
 *
 * File names: <YYYY-MM-DD>-<name>[-weekly][-<n>].md and the same name with .json for the data.
 */
final readonly class Archive
{
    public const array Kinds = ['daily', 'weekly'];

    public function __construct(private string $directory)
    {
    }

    public static function forProfiles(ProfileStore $profiles): self
    {
        return new self($profiles->directory().DIRECTORY_SEPARATOR.'reports');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /** @return array{report: string, data: ?string} the archived paths */
    public function save(string $name, string $kind, string $reportFile, ?string $dataFile, string $date): array
    {
        $name = self::name($name);
        self::kind($kind);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || strtotime($date) === false) {
            throw new DevnullException("Invalid date \"{$date}\": use YYYY-MM-DD");
        }

        $report = is_file($reportFile) ? file_get_contents($reportFile) : false;
        if ($report === false || trim($report) === '') {
            throw new DevnullException("Cannot read the report {$reportFile}");
        }
        $data = null;
        if ($dataFile !== null) {
            $data = JsonFile::read($dataFile) ?? throw new DevnullException("Cannot read the data {$dataFile}");
        }

        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0o777, true) && ! is_dir($this->directory)) {
            throw new DevnullException("Cannot create {$this->directory}");
        }

        $base = $date.'-'.$name.($kind === 'weekly' ? '-weekly' : '');
        for ($n = 1; ; $n++) {
            $stem = $this->directory.DIRECTORY_SEPARATOR.$base.($n > 1 ? "-{$n}" : '');
            if (is_file("{$stem}.md")) {
                continue;
            }
            // 'x' mode fails when the file exists, so two runs at the same moment never share a name.
            $handle = @fopen("{$stem}.md", 'x');
            if ($handle !== false) {
                break;
            }
            if (! is_file("{$stem}.md")) {
                throw new DevnullException("Cannot write {$stem}.md");
            }
        }

        fwrite($handle, $report);
        fclose($handle);
        if ($data !== null) {
            JsonFile::write("{$stem}.json", $data);
        }

        return ['report' => "{$stem}.md", 'data' => $data !== null ? "{$stem}.json" : null];
    }

    /** @return list<array{date: string, name: string, kind: string, report: string, data: ?string}> newest first */
    public function entries(?string $name = null, ?string $kind = null): array
    {
        $entries = [];
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*.md') ?: [] as $file) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})-(.+?)(-weekly)?(-\d+)?\.md$/', basename($file), $match) !== 1) {
                continue;
            }

            $entry = [
                'date' => $match[1],
                'name' => $match[2],
                'kind' => ($match[3] ?? '') === '-weekly' ? 'weekly' : 'daily',
                'report' => $file,
                'data' => is_file(substr($file, 0, -3).'.json') ? substr($file, 0, -3).'.json' : null,
            ];
            if (($name === null || $entry['name'] === $name) && ($kind === null || $entry['kind'] === $kind)) {
                $entries[] = $entry + ['order' => (int) ltrim($match[4] ?? '-1', '-')];
            }
        }

        usort($entries, static fn (array $a, array $b): int => [$b['date'], $b['order']] <=> [$a['date'], $a['order']]);

        return array_map(static function (array $entry): array {
            unset($entry['order']);

            return $entry;
        }, $entries);
    }

    /** @return array{date: string, name: string, kind: string, report: string, data: ?string}|null the newest entry before a date */
    public function latest(string $name, string $kind, ?string $before = null): ?array
    {
        foreach ($this->entries(self::name($name), self::kind($kind)) as $entry) {
            if ($before === null || $entry['date'] < $before) {
                return $entry;
            }
        }

        return null;
    }

    private static function name(string $name): string
    {
        $name = trim($name);
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name) !== 1) {
            throw new DevnullException("Invalid report name \"{$name}\": use lower-case letters, digits, '.', '_' and '-'");
        }
        // The archive appends "-weekly" and "-2", "-3" … to file names; a name ending like that would be read back wrong.
        if (preg_match('/-(weekly|\d+)$/', $name) === 1) {
            throw new DevnullException("Invalid report name \"{$name}\": it must not end with -weekly or -<number>");
        }

        return $name;
    }

    private static function kind(string $kind): string
    {
        return in_array($kind, self::Kinds, true) ? $kind : throw new DevnullException("Unknown kind \"{$kind}\": use daily or weekly");
    }
}
