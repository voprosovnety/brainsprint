<?php

namespace Tests\Fixtures;

use App\Funnel\Configuration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Existing regression suites explicitly exercise immutable v2, independent of latest publication. */
class LegacyFunnelSeeder extends Seeder
{
    public function run(): void
    {
        $source = file_get_contents(resource_path('funnel/brainsprint-v2.json'));
        Configuration::validate(json_decode($source, true, flags: JSON_THROW_ON_ERROR));
        DB::table('funnel_versions')->insertOrIgnore([
            'id' => 'brainsprint-'.substr(hash('sha256', $source), 0, 12),
            'configuration' => $source, 'published_at' => now(),
        ]);
    }
}
