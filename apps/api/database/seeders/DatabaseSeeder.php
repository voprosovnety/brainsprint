<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Funnel\Configuration;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $source = file_get_contents(resource_path('funnel/brainsprint-v2.json'));
        Configuration::validate(json_decode($source, true, flags: JSON_THROW_ON_ERROR));
        $id = 'brainsprint-'.substr(hash('sha256', $source), 0, 12);
        // Publishing inserts a new content-addressed version; existing versions never change.
        DB::table('funnel_versions')->insertOrIgnore([
            'id' => $id, 'configuration' => $source, 'published_at' => now(),
        ]);
    }
}
