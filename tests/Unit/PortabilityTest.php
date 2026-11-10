<?php

declare(strict_types=1);

/**
 * The scripts must run on a stock PHP on Windows, macOS and Linux, where ext-curl and ext-mbstring are often disabled
 * and no Unix shell exists. A token scan keeps calls to them out of the plugin's PHP files.
 */
test('plugin scripts call no ext-curl, ext-mbstring or shell function', function (): void {
    $root = dirname(__DIR__, 2).'/plugins';
    $forbidden = '/^(curl_|mb_)|^(exec|shell_exec|system|passthru|popen|proc_open)$/i';
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $tokens = token_get_all((string) file_get_contents($file->getPathname()));
        foreach ($tokens as $index => $token) {
            if ($token === '`') {
                $violations[] = "{$file->getFilename()} backtick";
            }
            if (is_array($token) && $token[0] === T_STRING && preg_match($forbidden, $token[1]) === 1) {
                $next = $tokens[$index + 1] ?? null;
                if ($next === '(') {
                    $violations[] = "{$file->getFilename()}:{$token[2]} {$token[1]}()";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});
