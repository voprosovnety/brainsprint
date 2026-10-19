<?php

namespace Tests\Feature;

use App\Funnel\Configuration;
use App\Funnel\VisitorContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsExperimentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['visitor_id' => 'analytics-owner']);
    }

    private function exposure(string $name = 'landing_viewed', ?array $run = null, ?string $step = null, ?string $id = null)
    {
        return $this->postJson('/api/v1/events', array_filter([
            'event_id' => $id ?? (string) Str::uuid(), 'name' => $name, 'run_id' => $run['id'] ?? null, 'step_id' => $step,
        ], fn ($v) => $v !== null));
    }

    public function test_assignment_is_stable_without_exposure_and_first_touch_survives_refresh_and_start(): void
    {
        $first = $this->getJson('/api/v1/bootstrap?utm_source=newsletter&utm_campaign=october')->assertOk()->json();
        $this->assertContains($first['experiment']['variant'], ['A', 'B']);
        $this->getJson('/api/v1/bootstrap?utm_source=overwrite')->assertJsonPath('experiment', $first['experiment']);
        $this->assertDatabaseCount('events', 0);
        $event = (string) Str::uuid();
        $this->exposure(id: $event)->assertOk();
        $this->exposure(id: $event)->assertOk();
        $this->exposure()->assertOk();
        $run = $this->postJson('/api/v1/runs', ['attribution' => ['utm_source' => 'overwrite']])->assertOk()->json();
        $this->postJson('/api/v1/runs', [])->assertJsonPath('id', $run['id']);
        $record = DB::table('funnel_runs')->first();
        $this->assertSame($first['experiment']['variant'], $record->variant);
        $this->assertSame(VisitorContext::EXPERIMENT, $record->experiment_id);
        $this->assertSame(['utm_source' => 'newsletter', 'utm_campaign' => 'october'], json_decode($record->attribution, true));
        $this->assertDatabaseCount('events', 2);
        $exposure = DB::table('events')->where('name', 'landing_viewed')->first();
        $this->assertNull($exposure->run_id);
        $this->assertSame('analytics-owner', $exposure->visitor_id);
        $this->assertTrue((bool) $exposure->is_synthetic);
        $this->assertLessThanOrEqual($record->created_at, $exposure->created_at);
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('run.id', $run['id']);
        $this->assertDatabaseCount('events', 2);
    }

    public function test_start_can_capture_campaign_without_prior_view_delivery(): void
    {
        $this->postJson('/api/v1/runs', ['attribution' => ['utm_content' => 'quick-start']])->assertOk();
        $this->assertSame(['utm_content' => 'quick-start'], json_decode(DB::table('funnel_runs')->value('attribution'), true));
        $this->assertDatabaseMissing('events', ['name' => 'landing_viewed']);
        $this->postJson('/api/v1/runs', ['variant' => 'B'])->assertStatus(422);
        $this->getJson('/api/v1/bootstrap?utm_source='.str_repeat('x', 121))->assertStatus(422);
        $this->postJson('/api/v1/runs', ['attribution' => ['url' => 'secret']])->assertStatus(422);
    }

    public function test_client_cannot_forge_milestones_context_future_screens_or_ownership(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertOk();
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        foreach (['funnel_started', 'step_completed', 'quiz_completed', 'demo_completed'] as $name) {
            $this->exposure($name, $run, 'welcome')->assertStatus(422);
        }
        foreach (['visitor_id', 'variant', 'is_synthetic', 'funnel_version_id', 'experiment_id', 'created_at', 'attribution'] as $field) {
            $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'landing_viewed', $field => 'forged'])->assertStatus(422);
        }
        $this->exposure('step_viewed', $run, 'memory-one')->assertStatus(409);
        $this->exposure('result_viewed', $run, 'welcome')->assertStatus(422);
        $this->exposure('step_viewed', $run, 'missing')->assertStatus(409);
        $this->exposure('landing_viewed', $run)->assertStatus(422);
        $this->postJson('/api/v1/events', ['event_id' => 'invalid', 'name' => 'landing_viewed'])->assertStatus(422);
        $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'landing_viewed', 'properties' => ['answer' => 'no']])->assertStatus(422);
        $this->withSession(['visitor_id' => 'another']);
        $this->exposure('step_viewed', $run, 'welcome')->assertNotFound();
        $this->assertDatabaseCount('events', 1);
    }

    public function test_actual_views_follow_branch_restore_and_retries_without_minting_milestones(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertOk();
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        $this->exposure('step_viewed', $run, 'welcome')->assertOk();
        $this->exposure('step_viewed', $run, 'welcome')->assertOk();
        $this->postJson('/api/v1/runs/'.$run['id'].'/steps/welcome/complete', ['answer' => ['acknowledged' => true]])->assertOk();
        $this->exposure('step_viewed', $run, 'welcome')->assertOk();
        $this->postJson('/api/v1/runs/'.$run['id'].'/steps/memory-tutorial/complete', ['answer' => ['cells' => [0,4,8]]])->assertOk();
        $this->exposure('step_viewed', $run, 'memory-guide')->assertStatus(409);
        $run = $this->getJson('/api/v1/runs/'.$run['id'])->json();
        while ($run['current_step'] !== 'done') {
            $step = $run['current_step'];
            $screen = Configuration::screen($run['configuration'], $step);
            $name = match ($screen['type']) { 'result' => 'result_viewed', 'boundary' => 'paywall_reached', default => 'step_viewed' };
            $this->exposure($name, $run, $step)->assertOk();
            $this->exposure($name, $run, $step)->assertOk();
            $answer = match ($screen['type']) {
                'visual_memory', 'tutorial' => ['cells' => $screen['pattern']],
                'pattern' => ['choice' => $screen['correct_choice']], 'logic' => ['order' => $screen['correct_order']], default => ['acknowledged' => true],
            };
            $run = $this->postJson('/api/v1/runs/'.$run['id'].'/steps/'.$step.'/complete', ['answer' => $answer])->assertOk()->json();
        }
        $this->assertSame(1, DB::table('events')->where('name', 'paywall_reached')->count());
        $this->assertSame(1, DB::table('events')->where('name', 'result_viewed')->count());
        $this->assertSame(1, DB::table('events')->where('name', 'quiz_completed')->count());
        $this->assertSame(1, DB::table('events')->where('name', 'demo_completed')->count());
        $this->assertSame(1, DB::table('events')->where('step_id', 'welcome')->where('name', 'step_viewed')->count());
    }

    public function test_analytics_rate_limit_cannot_exhaust_progress_budget_and_uuid_reuse_is_a_noop(): void
    {
        $bootstrap = $this->getJson('/api/v1/bootstrap')->assertOk();
        // Preserve the actual session cookie across requests to exercise its rate key.
        $this->withCredentials()->withCookie(config('session.cookie'), $bootstrap->getCookie(config('session.cookie'))->getValue());
        $id = (string) Str::uuid();
        $this->exposure(id: $id)->assertOk();
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        $this->exposure('step_viewed', $run, 'welcome', $id)->assertOk();
        $this->assertDatabaseMissing('events', ['name' => 'step_viewed']);
        for ($i = 0; $i < 38; $i++) { $this->exposure()->assertOk(); }
        $this->exposure()->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/v1/runs/'.$run['id'].'/steps/welcome/complete', ['answer' => ['acknowledged' => true]])->assertOk();
        $this->getJson('/api/v1/runs/'.$run['id'])->assertOk()->assertJsonPath('current_step', 'memory-tutorial');
        $this->assertSame(1, DB::table('events')->where('name', 'landing_viewed')->count());
    }

    public function test_csrf_is_required_for_events(): void
    {
        // Laravel bypasses CSRF in the testing environment; explicitly enable its real path.
        $this->app['env'] = 'local';
        $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'landing_viewed'])->assertStatus(419);
    }
}
