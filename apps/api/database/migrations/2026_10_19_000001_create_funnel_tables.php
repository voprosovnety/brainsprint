<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_versions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->jsonb('configuration');
            $t->timestampTz('published_at');
        });
        Schema::create('funnel_runs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('visitor_id', 64)->unique();
            $t->string('funnel_version_id');
            $t->foreign('funnel_version_id')->references('id')->on('funnel_versions');
            $t->string('current_step');
            $t->jsonb('answers')->default('{}');
            $t->jsonb('result')->nullable();
            $t->boolean('is_synthetic')->default(false)->index();
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->uuid('run_id');
            $t->foreign('run_id')->references('id')->on('funnel_runs')->cascadeOnDelete();
            $t->string('name');
            $t->string('step_id')->default('');
            $t->boolean('is_synthetic')->default(false);
            $t->timestampTz('created_at');
            $t->unique(['run_id', 'name', 'step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('funnel_runs');
        Schema::dropIfExists('funnel_versions');
    }
};
