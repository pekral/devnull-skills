<?php

declare(strict_types=1);

const FETCH_SCRIPT = __DIR__.'/../plugins/devnull-insights/skills/devnull-insights/scripts/fetch.php';
const PROFILES_SCRIPT = __DIR__.'/../plugins/devnull-insights/skills/devnull-insights/scripts/profiles.php';
const STATE_SCRIPT = __DIR__.'/../plugins/devnull-insights/skills/devnull-insights/scripts/state.php';

/** A fresh, empty profile directory for one test. */
function profileHome(): string
{
    $directory = sys_get_temp_dir().'/devnull-profiles-'.bin2hex(random_bytes(6));
    register_shutdown_function(static function () use ($directory): void {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($directory);
    });

    return $directory;
}

/**
 * Starts the dev-null.sh stand-in once per test process and returns its base URL.
 * `php -S` and proc_open with an argument list work the same on Windows, macOS and Linux.
 */
function fixtureServer(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $port = random_int(20000, 45000);
        foreach (glob(sys_get_temp_dir()."/devnull-fixture-{$port}-*") ?: [] as $stale) {
            unlink($stale);
        }

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/Fixtures/server.php'],
            [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir().'/devnull-fixture-server.log', 'a'], 2 => ['file', sys_get_temp_dir().'/devnull-fixture-server.log', 'a']],
            $pipes,
        );

        for ($wait = 0; $wait < 50; $wait++) {
            // A refused connection while the server starts is expected; keep it out of the test's warnings.
            set_error_handler(static fn (): bool => true);
            $socket = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            restore_error_handler();
            if (is_resource($socket)) {
                fclose($socket);
                register_shutdown_function(static function () use ($process, $port): void {
                    proc_terminate($process);
                    foreach (glob(sys_get_temp_dir()."/devnull-fixture-{$port}-*") ?: [] as $counter) {
                        unlink($counter);
                    }
                });

                return $base = "http://127.0.0.1:{$port}";
            }
            usleep(100_000);
        }

        proc_terminate($process);
    }

    throw new RuntimeException('Cannot start the fixture server');
}

/**
 * Runs fetch.php as the skill does, against a fixture scenario, with ext-curl and ext-mbstring functions disabled so a
 * call to either fails the run on every OS.
 *
 * @param  list<string>  $arguments
 * @return array{exit: int, stdout: string, stderr: string, json: ?array<mixed>}
 */
function runFetch(string $scenario, array $arguments = [], array $env = []): array
{
    return runScript(FETCH_SCRIPT, $arguments, ['DEVNULL_BASE_URL' => fixtureServer()."/{$scenario}", ...$env]);
}

/** How many /log/latest requests a scenario received. */
function logRequests(string $scenario): int
{
    return json_decode((string) file_get_contents(fixtureServer()."/__hits/{$scenario}"), true)['count'];
}

/**
 * @param  list<string>  $arguments
 * @param  array<string, string>  $env
 * @return array{exit: int, stdout: string, stderr: string, json: ?array<mixed>}
 */
function runScript(string $script, array $arguments = [], array $env = []): array
{
    $disabled = [];
    foreach (['curl', 'mbstring'] as $extension) {
        $disabled = [...$disabled, ...(extension_loaded($extension) ? get_extension_funcs($extension) ?: [] : [])];
    }

    $command = [PHP_BINARY, '-d', 'disable_functions='.implode(',', $disabled), $script, ...$arguments];
    $env = [
        ...getenv(),
        'DEVNULL_PAGE_DELAY_MS' => '0',
        'DEVNULL_RETRY_DELAY' => '0',
        ...$env,
    ];

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    $json = json_decode((string) $stdout, true);

    return ['exit' => $exit, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr, 'json' => is_array($json) ? $json : null];
}
