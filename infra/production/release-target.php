<?php

// Explicit release jobs only. Test runners never receive these credentials.
$root = dirname(__DIR__, 2);
$url = parse_url(getenv('DB_URL') ?: '');
if (! is_array($url) || ! str_ends_with($url['host'] ?? '', '.neon.tech') || ($url['path'] ?? '') !== '/brainsprint' || getenv('APP_ENV') !== 'production') {
    fwrite(STDERR, "Release operations require the designated Neon brainsprint database.\n");
    exit(1);
}
parse_str($url['query'] ?? '', $query);
if (($query['sslmode'] ?? '') !== 'verify-full') {
    fwrite(STDERR, "Release operations require verified PostgreSQL TLS.\n");
    exit(1);
}

require $root.'/apps/api/vendor/autoload.php';
$app = require $root.'/apps/api/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = Illuminate\Support\Facades\DB::selectOne('select current_database() as name');
if ($database->name !== 'brainsprint') {
    fwrite(STDERR, "Unexpected database; release operation refused.\n");
    exit(1);
}
echo "Verified release database and TLS settings.\n";
