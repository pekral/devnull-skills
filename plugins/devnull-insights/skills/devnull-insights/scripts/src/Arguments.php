<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * Command-line arguments of the form `<command> --name=value …`. getopt() stops at the first word that is not an
 * option, so it cannot read options placed after a command.
 */
final readonly class Arguments
{
    /** @param array<string, string> $options */
    private function __construct(public string $command, private array $options)
    {
    }

    /**
     * @param  list<string>  $argv  without the script name
     * @param  list<string>  $allowed  option names without the leading dashes
     */
    public static function parse(array $argv, array $allowed, string $defaultCommand): self
    {
        $command = null;
        $options = [];

        foreach ($argv as $argument) {
            if (preg_match('/^--([a-z-]+)=(.*)$/s', $argument, $match) === 1) {
                $options[$match[1]] = $match[2];
            } elseif (! str_starts_with($argument, '-') && $command === null) {
                $command = $argument;
            } else {
                throw new DevnullException("Unexpected argument \"{$argument}\": give one command and options as --name=value");
            }
        }

        $unknown = array_diff(array_keys($options), $allowed);
        if ($unknown !== []) {
            throw new DevnullException('Unknown option --'.implode(', --', $unknown));
        }

        return new self($command ?? $defaultCommand, $options);
    }

    public function get(string $name, string $default = ''): string
    {
        return $this->options[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->options[$name]);
    }
}
