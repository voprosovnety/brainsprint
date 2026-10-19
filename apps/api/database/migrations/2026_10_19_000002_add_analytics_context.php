<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnel_runs', function (Blueprint $t) {
            $t->timestampTz('created_at', 6)->change();
            $t->string('experiment_id', 40)->nullable();
            $t->string('variant', 1)->nullable();
            $t->jsonb('attribution')->nullable();
        });
        Schema::table('events', function (Blueprint $t) {
            $t->uuid('run_id')->nullable()->change();
            $t->timestampTz('created_at', 6)->change();
            $t->string('visitor_id', 64)->nullable();
            $t->string('funnel_version_id')->nullable();
            $t->string('experiment_id', 40)->nullable();
            $t->string('variant', 1)->nullable();
            $t->jsonb('attribution')->nullable();
            $t->uuid('client_event_id')->nullable();
            $t->string('semantic_key', 180)->nullable()->unique();
            $t->jsonb('properties')->nullable();
            $t->unique(['visitor_id', 'client_event_id']);
            $t->index(['is_synthetic', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->dropUnique(['visitor_id', 'client_event_id']);
            $t->dropUnique(['semantic_key']);
            $t->dropIndex(['is_synthetic', 'created_at']);
            $t->dropColumn(['visitor_id', 'funnel_version_id', 'experiment_id', 'variant', 'attribution', 'client_event_id', 'semantic_key', 'properties']);
        });
        Schema::table('funnel_runs', fn (Blueprint $t) => $t->dropColumn(['experiment_id', 'variant', 'attribution']));
        // Nullable run_id is retained: reverting must not erase collected landing exposures.
    }
};
