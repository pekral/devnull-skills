<?php

declare(strict_types=1);

// The plugin runs without Composer, so the scripts load their own classes.
spl_autoload_register(static function (string $class): void {
    $prefix = 'DevnullInsights\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($file)) {
            require $file;
        }
    }
});
