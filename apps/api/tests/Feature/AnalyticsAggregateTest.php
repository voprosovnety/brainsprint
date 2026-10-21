<?php

namespace Tests\Feature;

use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use Carbon\CarbonImmutable as Time;
use Database\Seeders\DatabaseSeeder;
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

    private function fixtureRun(string $visitor, string $at, bool $synthetic = false, array $utm = []): string
    {
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => $visitor, 'funnel_version_id' => $this->version,
            'current_step' => 'remember-word', 'created_at' => $at, 'updated_at' => $at, 'is_synthetic' => $synthetic, 'attribution' => json_encode((object) $utm)]);

        return $id;
    }

    private function event(string $run, string $name, string $at, string $step = '', bool $synthetic = false): void
    {
        DB::table('events')->insert(['run_id' => $run, 'name' => $name, 'created_at' => $at, 'step_id' => $step, 'is_synthetic' => $synthetic]);
    }

    private function summary(Dataset $dataset = Dataset::Real): array
    {
        return app(AggregateService::class)->summarize(Time::parse('2026-10-01Z'), Time::parse('2026-10-03Z'), Time::parse('2026-10-03T12:00Z'), $dataset, 24);
    }

    public function test_cohorts_observed_intersections_timing_and_synthetic_isolation(): void
    {
        $one = $this->fixtureRun('one', '2026-10-01 01:00Z', utm: ['utm_source' => 'mail']);
        $two = $this->fixtureRun('two', '2026-10-02 20:00Z');
        $three = $this->fixtureRun('three', '2026-10-01 04:00Z');
        $this->fixtureRun('excluded-at-to', '2026-10-03 00:00Z');
        $this->fixtureRun('excluded-before', '2026-09-30 23:59Z');
        $synthetic = $this->fixtureRun('demo', '2026-10-01 02:00Z', synthetic: true);
        foreach ([[$one, '2026-10-01 01:00Z'], [$two, '2026-10-02 20:00Z'], [$three, '2026-10-01 04:00Z']] as [$id, $at]) {
            $this->event($id, 'funnel_started', $at);
        }
        $this->event($synthetic, 'funnel_started', '2026-10-01 02:00Z', synthetic: true);
        $this->event($one, 'step_viewed', '2026-10-01 01:01Z', 'remember-word');
        $this->event($one, 'step_completed', '2026-10-01 01:02Z', 'remember-word');
        $this->event($two, 'step_viewed', '2026-10-02 20:01Z', 'remember-word');
        $this->event($three, 'step_viewed', '2026-10-01 04:01Z', 'remember-word');
        $this->event($one, 'quiz_confirmed_v3', '2026-10-01 01:05Z');
        $this->event($three, 'quiz_confirmed_v3', '2026-10-01 04:03Z');
        $this->event($one, 'processing_completed_v3', '2026-10-01 01:06Z');
        $this->event($one, 'paywall_viewed_v3', '2026-10-01 01:07Z', 'report-offer');
        // A completion without recorded checkout does not invent a checkout view.
        $this->event($one, 'mock_unlocked_v3', '2026-10-01 01:08Z', 'report-offer');
        $this->event($one, 'step_completed', '2026-10-01 01:08Z', 'report-offer');
        $this->event($one, 'upsell_decided_v3', '2026-10-01 01:09Z', 'guide-offer');
        $this->event($one, 'step_completed', '2026-10-01 01:09Z', 'guide-offer');
        $this->event($one, 'funnel_completed_v3', '2026-10-04 01:10Z'); // after asOf
        $summary = $this->summary();
        $this->assertSame(3, $summary['totals']['runs']);
        $this->assertSame(2, $summary['totals']['quiz_confirmed_v3']);
        $this->assertSame(1, $summary['totals']['mock_unlocked_v3']);
        $this->assertSame(0, $summary['totals']['funnel_completed_v3']);
        $this->assertSame(['numerator' => 2, 'denominator' => 3, 'rate' => 2/3, 'from' => 'funnel_started'], $summary['stage_conversions']['quiz_confirmed_v3']);
        $this->assertNull($summary['stage_conversions']['mock_unlocked_v3']['rate']);
        $this->assertSame(240.0, $summary['quiz_elapsed_seconds']['mean']);
        $steps = collect($summary['steps'])->keyBy('step_id');
        $this->assertSame(['numerator' => 1, 'denominator' => 3, 'rate' => 1/3], $steps['remember-word']['completion_among_viewed']);
        $this->assertSame(1, $steps['remember-word']['pending_observation']);
        $this->assertSame(1, $steps['remember-word']['not_completed_after_observation']);
        $this->assertSame(1, $steps['report-offer']['completion_among_viewed']['denominator']);
        $this->assertSame(1, $steps['guide-offer']['completed_without_recorded_view']);
        $this->assertNull($steps['guide-offer']['completion_among_viewed']['rate']);
        $this->assertCount(2, $summary['utm']);
        $this->assertSame(1, collect($summary['utm'])->first(fn ($r) => ($r['attribution']['utm_source'] ?? null) === 'mail')['quiz_confirmed_v3']);
        $this->assertSame(1, $this->summary(Dataset::Synthetic)['totals']['runs']);
        $this->assertEquals($summary, $this->summary());
    }

    public function test_timezone_and_microsecond_cohort_and_outcome_boundaries(): void
    {
        $one = $this->fixtureRun('one', '2026-10-01 00:00:00.200000Z');
        $two = $this->fixtureRun('two', '2026-10-01 00:00:00.400000Z');
        $this->fixtureRun('outside', '2026-10-01 00:00:00.700000Z');
        foreach ([$one, $two] as $id) { $this->event($id, 'paywall_viewed_v3', '2026-10-01 00:00:00.500000Z', 'report-offer'); }
        $this->event($two, 'checkout_viewed_v3', '2026-10-01 00:00:00.600000Z', 'report-offer');
        $service = app(AggregateService::class);
        $utc = $service->summarize(Time::parse('2026-10-01T00:00:00.200000Z'), Time::parse('2026-10-01T00:00:00.700000Z'), Time::parse('2026-10-02T00:00:00.650000Z'));
        $local = $service->summarize(Time::parse('2026-10-01T07:00:00.200000+07:00'), Time::parse('2026-10-01T07:00:00.700000+07:00'), Time::parse('2026-10-02T07:00:00.650000+07:00'));
        $this->assertSame('2026-10-01T00:00:00.200000+00:00', $utc['cohort']['from']);
        $this->assertSame(2, $utc['totals']['runs']);
        $this->assertSame($utc['totals'], $local['totals']);
        $this->assertSame(['numerator' => 1, 'denominator' => 2, 'rate' => 0.5, 'from' => 'paywall_viewed_v3'], $utc['stage_conversions']['checkout_viewed_v3']);
        $fraction = $service->summarize(Time::parse('2026-10-01T00:00:00.200000Z'), Time::parse('2026-10-01T00:00:00.450000Z'), Time::parse('2026-10-01T00:00:00.550000Z'));
        $this->assertSame(2, $fraction['totals']['paywall_viewed_v3']);
        $this->assertSame(0, $fraction['totals']['checkout_viewed_v3']);
    }

    public function test_unsupported_versions_are_excluded_from_every_aggregate(): void
    {
        DB::table('funnel_versions')->insert(['id' => 'retired', 'configuration' => '{}', 'published_at' => '2026-09-01Z']);
        $id = $this->fixtureRun('retired-visitor', '2026-10-01 01:00Z');
        DB::table('funnel_runs')->where('id', $id)->update(['funnel_version_id' => 'retired']);
        $this->event($id, 'funnel_started', '2026-10-01 01:00Z');
        $summary = $this->summary();
        $this->assertSame(0, $summary['totals']['runs']);
        $this->assertSame(0, $summary['totals']['funnel_started']);
        $this->assertSame([], $summary['utm']);
        $this->assertSame([], $summary['steps']);
    }

    public function test_invalid_or_unbounded_cohort_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AggregateService::class)->summarize(Time::parse('2026-01-01'), Time::parse('2026-10-01'), Time::parse('2026-10-03'));
    }
}
