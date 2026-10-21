<?php

namespace Tests\Feature;

use App\Funnel\Configuration;
use Database\Seeders\DatabaseSeeder;
use Tests\Support\CompletesFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsExposureTest extends TestCase
{
    use RefreshDatabase, CompletesFunnel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['visitor_id' => 'analytics-owner']);
        config(['release.consumer_per_minute' => 2000, 'release.global_per_minute' => 4000]);
    }

    private function exposure(string $name = 'step_viewed', ?array $run = null, ?string $step = null, ?string $id = null)
    {
        return $this->postJson('/api/v1/events', array_filter([
            'event_id' => $id ?? (string) Str::uuid(), 'name' => $name, 'run_id' => $run['id'] ?? null, 'step_id' => $step,
        ], fn ($v) => $v !== null));
    }

    public function test_direct_entry_has_no_experiment_or_exposure_and_preserves_first_touch(): void
    {
        $this->getJson('/api/v1/bootstrap?utm_source=newsletter&utm_campaign=october')->assertOk()->assertJsonPath('experiment', null)->assertJsonPath('restart_required', false);
        $this->getJson('/api/v1/bootstrap?utm_source=overwrite')->assertJsonPath('experiment', null);
        $this->assertDatabaseCount('events', 0);
        $run = $this->postJson('/api/v1/runs', ['attribution' => ['utm_source' => 'overwrite']])->assertOk()->json();
        $this->postJson('/api/v1/runs')->assertJsonPath('id', $run['id']);
        $record = DB::table('funnel_runs')->first();
        $this->assertSame(['utm_source' => 'newsletter', 'utm_campaign' => 'october'], json_decode($record->attribution, true));
        $this->assertTrue((bool) $record->is_synthetic);
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('run.id', $run['id']);
        $this->assertDatabaseCount('events', 1);
    }

    public function test_start_can_capture_campaign_without_prior_view_delivery(): void
    {
        $this->postJson('/api/v1/runs', ['attribution' => ['utm_content' => 'quick-start']])->assertOk();
        $this->assertSame(['utm_content' => 'quick-start'], json_decode(DB::table('funnel_runs')->value('attribution'), true));
        $this->assertDatabaseMissing('events', ['name' => 'step_viewed']);
        $this->postJson('/api/v1/runs', ['variant' => 'B'])->assertStatus(422);
        $this->getJson('/api/v1/bootstrap?utm_source='.str_repeat('x', 121))->assertStatus(422);
        $this->postJson('/api/v1/runs', ['attribution' => ['url' => 'secret']])->assertStatus(422);
    }

    public function test_client_cannot_forge_milestones_context_future_screens_or_ownership(): void
    {
        $this->getJson('/api/v1/bootstrap')->assertOk();
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        foreach (['funnel_started', 'step_completed', 'quiz_confirmed_v3', 'processing_completed_v3', 'mock_unlocked_v3', 'upsell_decided_v3', 'funnel_completed_v3'] as $name) {
            $this->exposure($name, $run, 'remember-word')->assertStatus(422);
        }
        foreach (['visitor_id', 'variant', 'is_synthetic', 'funnel_version_id', 'experiment_id', 'created_at', 'attribution'] as $field) {
            $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'step_viewed', $field => 'forged'])->assertStatus(422);
        }
        $this->exposure('step_viewed', $run, 'confirm-answers')->assertStatus(409);
        $this->exposure('result_viewed', $run, 'remember-word')->assertStatus(422);
        $this->exposure('step_viewed', $run, 'missing')->assertStatus(409);
        $this->exposure('landing_viewed', $run)->assertStatus(422);
        $this->postJson('/api/v1/events', ['event_id' => 'invalid', 'name' => 'step_viewed'])->assertStatus(422);
        $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'step_viewed', 'properties' => ['answer' => 'no']])->assertStatus(422);
        $this->withSession(['visitor_id' => 'another']);
        $this->exposure('step_viewed', $run, 'remember-word')->assertNotFound();
        $this->assertDatabaseCount('events', 1);
    }

    public function test_actual_views_follow_confirmed_progress_unlock_and_retries(): void
    {
        config(['release.analytics_per_minute' => 1000]);
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        $this->exposure('report_viewed_v3', $run, 'report-offer')->assertStatus(409);
        $this->exposure('checkout_viewed_v3', $run, 'remember-word')->assertStatus(422);
        while ($run['current_step'] !== 'done') {
            $step = $run['current_step'];
            $screen = Configuration::screen($run['configuration'], $step);
            $this->exposure('step_viewed', $run, $step)->assertOk();
            $this->exposure('step_viewed', $run, $step)->assertOk();
            if ($screen['type'] === 'paywall') {
                $this->exposure('paywall_viewed_v3', $run, $step)->assertOk();
                $this->exposure('checkout_viewed_v3', $run, $step)->assertOk();
                $this->exposure('report_viewed_v3', $run, $step)->assertStatus(422);
            }
            if ($screen['type'] === 'final') { $this->exposure('report_viewed_v3', $run, $step)->assertOk(); }
            $run = $this->complete($run, $step, $this->answer($screen))->assertOk()->json();
        }
        foreach (['paywall_viewed_v3', 'checkout_viewed_v3', 'report_viewed_v3', 'quiz_confirmed_v3', 'funnel_completed_v3'] as $name) {
            $this->assertSame(1, DB::table('events')->where('name', $name)->count());
        }
        $this->exposure('step_viewed', $run, 'remember-word')->assertOk();
        $this->assertSame(1, DB::table('events')->where('step_id', 'remember-word')->where('name', 'step_viewed')->count());
        $skipped = collect($run['configuration']['steps'])->first(fn ($s) => $s['type'] === 'final' && ! $s['guide']);
        $this->exposure('step_viewed', $run, $skipped['id'])->assertStatus(409);
    }

    public function test_analytics_rate_limit_cannot_exhaust_progress_budget_and_uuid_reuse_is_a_noop(): void
    {
        $bootstrap = $this->getJson('/api/v1/bootstrap')->assertOk();
        // Preserve the actual session cookie across requests to exercise its rate key.
        $this->withCredentials()->withCookie(config('session.cookie'), $bootstrap->getCookie(config('session.cookie'))->getValue());
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        $id = (string) Str::uuid();
        $this->exposure('step_viewed', $run, 'remember-word', $id)->assertOk();
        $run = $this->complete($run, 'remember-word', ['acknowledged' => true])->assertOk()->json();
        $step = $run['current_step'];
        $this->exposure('step_viewed', $run, $step, $id)->assertOk();
        $this->assertDatabaseMissing('events', ['name' => 'step_viewed', 'step_id' => $step]);
        for ($i = 0; $i < 38; $i++) { $this->exposure('step_viewed', $run, 'remember-word')->assertOk(); }
        $this->exposure('step_viewed', $run, 'remember-word')->assertStatus(429)->assertHeader('Retry-After');
        $screen = Configuration::screen($run['configuration'], $step);
        $next = $this->complete($run, $step, $this->answer($screen))->assertOk()->json();
        $this->getJson('/api/v1/runs/'.$run['id'])->assertJsonPath('current_step', $next['current_step']);
        $this->assertSame(1, DB::table('events')->where('name', 'step_viewed')->count());
    }

    public function test_csrf_is_required_for_events(): void
    {
        // Laravel bypasses CSRF in the testing environment; explicitly enable its real path.
        $this->app['env'] = 'local';
        $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'step_viewed'])->assertStatus(419);
    }
}
