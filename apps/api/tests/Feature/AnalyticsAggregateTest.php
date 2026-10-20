<?php

namespace Tests\Feature;

use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use App\Funnel\VisitorContext;
use Carbon\CarbonImmutable as Time;
use Tests\Fixtures\LegacyFunnelSeeder as DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAggregateTest extends TestCase
{
    use RefreshDatabase;

    private string $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->version = DB::table('funnel_versions')->value('id');
    }

    private function fixtureRun(string $visitor, string $at, ?string $variant = 'A', bool $synthetic = false, array $utm = []): string
    {
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert([
            'id' => $id, 'visitor_id' => $visitor, 'funnel_version_id' => $this->version,
            'experiment_id' => $variant ? VisitorContext::EXPERIMENT : null, 'variant' => $variant,
            'attribution' => json_encode((object) $utm), 'is_synthetic' => $synthetic,
            'current_step' => 'welcome', 'created_at' => $at, 'updated_at' => $at,
        ]);
        return $id;
    }

    private function event(?string $run, string $name, string $at, string $step = '', ?string $visitor = null, ?string $variant = null, bool $synthetic = false): void
    {
        DB::table('events')->insert([
            'run_id' => $run, 'name' => $name, 'created_at' => $at, 'step_id' => $step,
            'visitor_id' => $visitor, 'experiment_id' => $variant ? VisitorContext::EXPERIMENT : null,
            'variant' => $variant, 'funnel_version_id' => $variant ? $this->version : null,
            'is_synthetic' => $synthetic,
        ]);
    }

    private function summary(Dataset $dataset = Dataset::Real): array
    {
        return app(AggregateService::class)->summarize(Time::parse('2026-10-01Z'), Time::parse('2026-10-03Z'), Time::parse('2026-10-03T12:00Z'), $dataset, 24);
    }

    public function test_exact_cohorts_branch_denominators_historical_rows_and_synthetic_isolation(): void
    {
        $one = $this->fixtureRun('one', '2026-10-01 01:00Z', utm: ['utm_source' => 'mail']);
        $two = $this->fixtureRun('two', '2026-10-02 20:00Z');
        $legacy = $this->fixtureRun('legacy', '2026-10-01 04:00Z', null);
        $this->fixtureRun('excluded-at-to', '2026-10-03 00:00Z');
        $this->fixtureRun('excluded-before', '2026-09-30 23:59Z');
        $synthetic = $this->fixtureRun('demo', '2026-10-01 02:00Z', synthetic: true);
        foreach ([[$one, '2026-10-01 01:00Z'], [$two, '2026-10-02 20:00Z'], [$legacy, '2026-10-01 04:00Z']] as [$id, $at]) {
            $this->event($id, 'funnel_started', $at);
        }
        $this->event($synthetic, 'funnel_started', '2026-10-01 02:00Z', synthetic: true);
        $this->event($one, 'step_viewed', '2026-10-01 01:01Z', 'welcome');
        $this->event($one, 'step_completed', '2026-10-01 01:02Z', 'welcome');
        $this->event($two, 'step_viewed', '2026-10-02 20:01Z', 'welcome');
        // A conditional explanation has one exposed run, not the whole cohort.
        $this->event($one, 'step_viewed', '2026-10-01 01:03Z', 'memory-guide');
        $this->event($one, 'step_completed', '2026-10-01 01:04Z', 'memory-guide');
        $this->event($legacy, 'step_completed', '2026-10-01 04:03Z', 'memory');
        $this->event($one, 'quiz_completed', '2026-10-01 01:05Z');
        $this->event($legacy, 'quiz_completed', '2026-10-01 04:03Z');
        // Legacy completed_at does not imply terminal demo completion.
        DB::table('funnel_runs')->where('id', $legacy)->update(['completed_at' => '2026-10-01 04:03Z']);
        $this->event($one, 'result_viewed', '2026-10-01 01:06Z', 'demo-result');
        $this->event($one, 'paywall_reached', '2026-10-01 01:07Z', 'demo-boundary');
        $this->event($one, 'demo_completed', '2026-10-04 01:08Z'); // after asOf
        $summary = $this->summary();
        $this->assertSame(['runs' => 3, 'funnel_started' => 3, 'quiz_completed' => 2, 'result_viewed' => 1, 'paywall_reached' => 1, 'demo_completed' => 0], $summary['totals']);
        $this->assertSame(['numerator' => 2, 'denominator' => 3, 'rate' => 2/3, 'from' => 'funnel_started'], $summary['stage_conversions']['quiz_completed']);
        $this->assertSame(240.0, $summary['quiz_elapsed_seconds']['mean']);
        $steps = collect($summary['steps'])->keyBy('step_id');
        $this->assertSame(['numerator' => 1, 'denominator' => 2, 'rate' => 0.5], $steps['welcome']['completion_among_viewed']);
        $this->assertSame(1, $steps['welcome']['pending_observation']);
        $this->assertSame(0, $steps['welcome']['not_completed_after_observation']);
        $this->assertSame(1, $steps['memory-guide']['completion_among_viewed']['denominator']);
        $this->assertSame(1, $steps['memory']['completed_without_recorded_view']);
        $this->assertNull($steps['memory']['completion_among_viewed']['rate']);
        $this->assertCount(2, $summary['utm']);
        $this->assertSame(1, collect($summary['utm'])->first(fn ($r) => ($r['attribution']['utm_source'] ?? null) === 'mail')['quiz_completed']);
        $this->assertSame(1, $this->summary(Dataset::Synthetic)['totals']['runs']);
        $this->assertSame(0, $summary['experiment']['variants']['A']['eligible_exposed']);
        $this->assertNull($summary['experiment']['winner']);
        $this->assertEquals($summary, $this->summary()); // same reusable output for a second caller
    }

    public function test_experiment_uses_mature_exposed_visitors_and_post_exposure_outcomes_within_window(): void
    {
        foreach ([['converted','A','2026-10-01 00:00Z'], ['never-started','A','2026-10-01 01:00Z'], ['too-early','A','2026-10-01 02:00Z'], ['late','B','2026-10-01 03:00Z'], ['pending','B','2026-10-02 20:00Z'], ['boundary','B','2026-10-02 12:00Z'], ['beyond-cohort','B','2026-10-03 00:00Z']] as [$visitor,$variant,$at]) {
            $this->event(null, 'landing_viewed', $at, visitor: $visitor, variant: $variant);
        }
        // Duplicate historical exposure row cannot inflate a visitor denominator.
        $this->event(null, 'landing_viewed', '2026-10-01 00:00Z', visitor: 'converted', variant: 'A');
        $one = $this->fixtureRun('converted', '2026-10-01 00:01Z');
        $this->event($one, 'funnel_started', '2026-10-01 00:01Z');
        $this->event($one, 'quiz_completed', '2026-10-01 00:06Z');
        $this->event($one, 'paywall_reached', '2026-10-01 00:07Z', 'demo-boundary');
        $early = $this->fixtureRun('too-early', '2026-10-01 01:59Z');
        $this->event($early, 'funnel_started', '2026-10-01 01:59Z');
        $late = $this->fixtureRun('late', '2026-10-02 03:00:01Z', 'B');
        $this->event($late, 'funnel_started', '2026-10-02 03:00:01Z');
        $edge = $this->fixtureRun('boundary', '2026-10-03 00:00Z', 'B'); // outside run cohort, inside exposure window
        $this->event($edge, 'funnel_started', '2026-10-03 00:00Z');
        $this->event($edge, 'quiz_completed', '2026-10-03 12:00Z'); // inclusive window endpoint
        $this->event($edge, 'paywall_reached', '2026-10-03 12:00:01Z', 'demo-boundary');
        // An exposure lacking newly added context is ineligible.
        $this->event(null, 'landing_viewed', '2026-10-01 00:00Z');
        $summary = $this->summary();
        $a = $summary['experiment']['variants']['A']; $b = $summary['experiment']['variants']['B'];
        $this->assertSame(3, $a['exposed']);
        $this->assertSame(['numerator' => 1, 'denominator' => 3, 'rate' => 1/3], $a['start_conversion']);
        $this->assertSame(1, $a['quiz_conversion']['numerator']);
        $this->assertSame(1, $a['boundary_conversion']['numerator']);
        $this->assertSame(3, $b['exposed']);
        $this->assertSame(1, $b['pending_observation']);
        $this->assertSame(['numerator' => 1, 'denominator' => 2, 'rate' => 0.5], $b['start_conversion']);
        $this->assertSame(1, $b['quiz_conversion']['numerator']);
        $this->assertSame(0, $b['boundary_conversion']['numerator']);
        $this->assertSame('insufficient_data', $summary['experiment']['status']);
        $this->assertSame(0, $this->summary(Dataset::Synthetic)['experiment']['variants']['A']['exposed']);
    }

    public function test_timezone_microsecond_boundaries_and_legacy_destination_eligibility(): void
    {
        $legacyConfig = json_decode(file_get_contents(resource_path('funnel/memory-v1.json')), true);
        DB::table('funnel_versions')->insert(['id' => 'legacy-test', 'configuration' => json_encode($legacyConfig), 'published_at' => '2026-09-01Z']);
        $legacy = $this->fixtureRun('legacy-viewer', '2026-10-01 00:00:00.200000Z', null);
        DB::table('funnel_runs')->where('id', $legacy)->update(['funnel_version_id' => 'legacy-test']);
        $this->event($legacy, 'result_viewed', '2026-10-01 00:00:00.300000Z', 'result');
        $modern = $this->fixtureRun('modern-viewer', '2026-10-01 00:00:00.400000Z');
        $this->event($modern, 'result_viewed', '2026-10-01 00:00:00.500000Z', 'demo-result');
        $this->event($modern, 'paywall_reached', '2026-10-01 00:00:00.600000Z', 'demo-boundary');
        $this->fixtureRun('outside-fraction', '2026-10-01 00:00:00.700000Z');
        $service = app(AggregateService::class);
        $utc = $service->summarize(Time::parse('2026-10-01T00:00:00.200000Z'), Time::parse('2026-10-01T00:00:00.700000Z'), Time::parse('2026-10-01T00:00:00.650000Z')->addDay());
        $local = $service->summarize(Time::parse('2026-10-01T07:00:00.200000+07:00'), Time::parse('2026-10-01T07:00:00.700000+07:00'), Time::parse('2026-10-02T07:00:00.650000+07:00'));
        $this->assertSame('2026-10-01T00:00:00.200000+00:00', $utc['cohort']['from']);
        $this->assertSame('2026-10-01T00:00:00.700000+00:00', $utc['cohort']['to_exclusive']);
        $this->assertSame(2, $utc['totals']['runs']);
        $this->assertSame($utc['totals'], $local['totals']);
        $this->assertSame(['numerator' => 1, 'denominator' => 1, 'rate' => 1, 'from' => 'result_viewed'], $utc['stage_conversions']['paywall_reached']);
        $fraction = $service->summarize(Time::parse('2026-10-01T00:00:00.200000Z'), Time::parse('2026-10-01T00:00:00.450000Z'), Time::parse('2026-10-01T00:00:00.550000Z'));
        $this->assertSame(2, $fraction['totals']['result_viewed']);
        $this->assertSame(0, $fraction['totals']['paywall_reached']);
    }

    public function test_invalid_or_unbounded_cohort_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AggregateService::class)->summarize(Time::parse('2026-01-01'), Time::parse('2026-10-01'), Time::parse('2026-10-03'));
    }
}
