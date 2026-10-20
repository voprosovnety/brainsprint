<?php

// Called only by the pinned Vercel PHP builder, which installs composer.lock.
foreach (['pdo_pgsql', 'intl', 'mbstring', 'bcmath', 'zip'] as $extension) {
    if (! extension_loaded($extension)) {
        fwrite(STDERR, 'Missing required PHP extension: '.$extension."\n");
        exit(1);
    }
}
foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
}
foreach (['package:discover', 'filament:upgrade'] as $command) {
    passthru(escapeshellarg(PHP_BINARY).' artisan '.$command.' --no-interaction', $status);
    if ($status !== 0) {
        exit($status);
    }
}
