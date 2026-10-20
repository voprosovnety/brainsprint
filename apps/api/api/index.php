<?php

// Vercel PHP is ordinary per-request PHP. Only temporary compiled views use disk.
$storage = sys_get_temp_dir().'/brainsprint';
foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    $path = $storage.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
        http_response_code(503);
        exit('The demo is temporarily unavailable.');
    }
}
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
putenv('LARAVEL_STORAGE_PATH='.$storage);
putenv('APP_CONFIG_CACHE='.$storage.'/config.php');
// The PHP function's base image can use either Red Hat or Debian CA paths.
foreach (['/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/certs/ca-certificates.crt', '/etc/ssl/cert.pem'] as $certificate) {
    if (is_readable($certificate)) {
        putenv('PGSSLROOTCERT='.$certificate);
        break;
    }
}

// Generated Filament assets are bundled with the function. Never serve application
// source, configuration, dotfiles, arbitrary extensions, or files outside public/.
$public = realpath(__DIR__.'/../public');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestPath === '/release.json') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['service' => 'api', 'commit' => getenv('VERCEL_GIT_COMMIT_SHA') ?: null]);
    exit;
}
if (is_string($requestPath) && preg_match('~^/(?:css|js|fonts)/[a-zA-Z0-9_./-]+\.(?:css|js|woff2?|ttf|svg)$~D', $requestPath)) {
    $file = realpath($public.$requestPath);
    if ($file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
        $types = ['css' => 'text/css', 'js' => 'application/javascript', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'svg' => 'image/svg+xml'];
        header('Content-Type: '.$types[pathinfo($file, PATHINFO_EXTENSION)]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        readfile($file);
        exit;
    }
}

require __DIR__.'/../public/index.php';
