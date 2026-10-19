<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $source = file_get_contents(resource_path('funnel/memory-v1.json'));
        $id = 'memory-'.substr(hash('sha256', $source), 0, 12);
        // Publishing inserts a new content-addressed version; existing versions never change.
        DB::table('funnel_versions')->insertOrIgnore([
            'id' => $id, 'configuration' => $source, 'published_at' => now(),
        ]);
    }
}
