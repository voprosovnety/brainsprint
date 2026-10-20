<?php

namespace App\Analytics;

use App\Funnel\VisitorContext;
use App\Funnel\Configuration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Aggregate-only output shared by trusted admin and synthetic-only public callers. */
class AggregateService
{
    public function summarize(
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CarbonImmutable $asOf = null,
        Dataset $dataset = Dataset::Real,
        int $observationHours = 24,
    ): array {
        $asOf ??= CarbonImmutable::now();
        if ($from >= $to || $to > $asOf || $from->diffInDays($to) > 93 || $observationHours < 1 || $observationHours > 168) {
            throw new InvalidArgumentException('Use a past half-open cohort of at most 93 days and an observation window of 1–168 hours.');
        }
        $synthetic = $dataset === Dataset::Synthetic;
        // Laravel's default DateTime binding drops offsets and microseconds.
        $fromSql = $from->format('Y-m-d H:i:s.uP');
        $toSql = $to->format('Y-m-d H:i:s.uP');
        $asOfSql = $asOf->format('Y-m-d H:i:s.uP');
        // Run cohort: creation in [from,to). Outcomes are observed through asOf inclusive.
        $runs = DB::table('funnel_runs')->where('is_synthetic', $synthetic)->where('created_at', '>=', $fromSql)->where('created_at', '<', $toSql)->get()->keyBy('id');
        $events = DB::table('events')->where('is_synthetic', $synthetic)->whereIn('run_id', $runs->keys())->where('created_at', '<=', $asOfSql)->get();
        $byRun = $events->groupBy('run_id');
        $v3Stages = ['funnel_started', 'quiz_confirmed_v3', 'processing_completed_v3', 'paywall_viewed_v3', 'checkout_viewed_v3', 'mock_unlocked_v3', 'upsell_decided_v3', 'report_viewed_v3', 'funnel_completed_v3'];
        $stages = ['funnel_started', 'quiz_completed', 'result_viewed', 'paywall_reached', 'demo_completed'];
        $sets = [];
        foreach (array_unique([...$stages, ...$v3Stages]) as $stage) {
            $sets[$stage] = $events->where('name', $stage)->pluck('run_id')->unique()->all();
        }
        $versions = DB::table('funnel_versions')->whereIn('id', $runs->pluck('funnel_version_id')->unique())->get()->keyBy('id');
        $conversions = [];
        foreach (array_slice($stages, 1) as $i => $stage) {
            $previous = $stages[$i];
            $eligible = array_values(array_filter($sets[$previous], function ($id) use ($stage, $runs, $versions) {
                $config = json_decode($versions[$runs[$id]->funnel_version_id]->configuration, true);
                if (($config['schema_version'] ?? null) === 3) { return false; }
                if (! in_array($stage, ['paywall_reached', 'demo_completed'], true)) { return true; }
                return ! Configuration::legacy($config) && collect($config['steps'])->contains(fn ($s) => $s['type'] === 'boundary' && $s['enabled']);
            }));
            $conversions[$stage] = $this->rate(count(array_intersect($eligible, $sets[$stage])), count($eligible)) + ['from' => $previous];
        }
        $v3Ids = $runs->filter(fn ($run) => (json_decode($versions[$run->funnel_version_id]->configuration, true)['schema_version'] ?? null) === 3)->keys()->all();
        $v3Conversions = [];
        foreach (array_slice($v3Stages, 1) as $i => $stage) {
            $previous = $v3Stages[$i];
            $eligible = array_values(array_intersect($sets[$previous], $v3Ids));
            $v3Conversions[$stage] = $this->rate(count(array_intersect($eligible, $sets[$stage])), count($eligible)) + ['from' => $previous];
        }
        $elapsed = [];
        $v3Elapsed = [];
        foreach ($runs as $id => $run) {
            $isV3 = in_array($id, $v3Ids, true);
            $quiz = ($byRun[$id] ?? collect())->where('name', $isV3 ? 'quiz_confirmed_v3' : 'quiz_completed')->sortBy('created_at')->first();
            if ($quiz && $quiz->created_at >= $run->created_at) {
                $seconds = CarbonImmutable::parse($run->created_at)->diffInSeconds(CarbonImmutable::parse($quiz->created_at));
                if ($isV3) { $v3Elapsed[] = $seconds; } else { $elapsed[] = $seconds; }
            }
        }
        $stepGroups = $events->filter(fn ($e) => $e->step_id !== '' && in_array($e->name, ['step_viewed', 'result_viewed', 'paywall_reached', 'paywall_viewed_v3', 'report_viewed_v3', 'step_completed'], true))
            ->groupBy(fn ($e) => $runs[$e->run_id]->funnel_version_id.':'.$e->step_id);
        $steps = [];
        foreach ($stepGroups as $group) {
            $first = $group->first();
            $views = $group->whereIn('name', ['step_viewed', 'result_viewed', 'paywall_reached', 'paywall_viewed_v3', 'report_viewed_v3'])->unique('run_id');
            $completed = $group->where('name', 'step_completed')->pluck('run_id')->unique()->all();
            $viewIds = $views->pluck('run_id')->all();
            $remaining = $views->reject(fn ($e) => in_array($e->run_id, $completed, true));
            $mature = $remaining->filter(fn ($e) => CarbonImmutable::parse($e->created_at)->addHours($observationHours) <= $asOf)->count();
            $steps[] = [
                'configuration_version' => $runs[$first->run_id]->funnel_version_id, 'step_id' => $first->step_id,
                'viewed' => count($viewIds), 'completed' => count($completed),
                'completion_among_viewed' => $this->rate(count(array_intersect($viewIds, $completed)), count($viewIds)),
                'completed_without_recorded_view' => count(array_diff($completed, $viewIds)),
                'not_completed_after_observation' => $mature, 'pending_observation' => $remaining->count() - $mature,
            ];
        }
        $utm = $runs->groupBy(fn ($r) => json_encode(json_decode($r->attribution ?? '{}', true) ?: (object) []))->map(function ($group, $key) use ($sets) {
            $ids = $group->pluck('id')->all();
            return ['attribution' => json_decode($key, true) ?: (object) [], 'runs' => count($ids), 'quiz_completed' => count(array_intersect($ids, $sets['quiz_completed'])), 'quiz_confirmed_v3' => count(array_intersect($ids, $sets['quiz_confirmed_v3']))];
        })->values()->all();

        // Exposure cohort is independent of the run cohort; a start can fall after to.
        $exposures = DB::table('events')->where('is_synthetic', $synthetic)->where('name', 'landing_viewed')
            ->where('experiment_id', VisitorContext::EXPERIMENT)->whereIn('variant', ['A', 'B'])->whereNotNull('visitor_id')
            ->whereNotNull('funnel_version_id')->where('created_at', '<', $toSql)->orderBy('created_at')->orderBy('id')->get()->unique('visitor_id')
            ->filter(fn ($e) => CarbonImmutable::parse($e->created_at) >= $from);
        $visitors = $exposures->pluck('visitor_id');
        $experimentRuns = DB::table('funnel_runs')->where('is_synthetic', $synthetic)->whereIn('visitor_id', $visitors)->get()->keyBy('visitor_id');
        $experimentEvents = DB::table('events')->where('is_synthetic', $synthetic)->whereIn('run_id', $experimentRuns->pluck('id'))->where('created_at', '<=', $asOfSql)->get()->groupBy('run_id');
        $variants = [];
        foreach (['A', 'B'] as $variant) {
            $all = $exposures->where('variant', $variant);
            $mature = $all->filter(fn ($e) => CarbonImmutable::parse($e->created_at)->addHours($observationHours) <= $asOf);
            $starters = 0; $quizzes = 0; $boundaries = 0;
            foreach ($mature as $exposure) {
                $run = $experimentRuns[$exposure->visitor_id] ?? null;
                $end = CarbonImmutable::parse($exposure->created_at)->addHours($observationHours);
                if (! $run || $run->experiment_id !== $exposure->experiment_id || $run->variant !== $variant || $run->created_at < $exposure->created_at || CarbonImmutable::parse($run->created_at) > $end) { continue; }
                $outcomes = ($experimentEvents[$run->id] ?? collect())->filter(fn ($e) => $e->created_at >= $run->created_at && CarbonImmutable::parse($e->created_at) <= $end);
                // Creation alone is not enough: require its authoritative milestone too.
                if (! $outcomes->contains('name', 'funnel_started')) { continue; }
                $starters++;
                $quizzes += (int) $outcomes->contains('name', 'quiz_completed');
                $boundaries += (int) $outcomes->contains('name', 'paywall_reached');
            }
            $variants[$variant] = [
                'exposed' => $all->count(), 'eligible_exposed' => $mature->count(), 'pending_observation' => $all->count() - $mature->count(),
                'start_conversion' => $this->rate($starters, $mature->count()),
                'quiz_conversion' => $this->rate($quizzes, $mature->count()),
                'boundary_conversion' => $this->rate($boundaries, $mature->count()),
            ];
        }
        return [
            'dataset' => $dataset->value, 'cohort' => ['from' => $from->format('Y-m-d\TH:i:s.uP'), 'to_exclusive' => $to->format('Y-m-d\TH:i:s.uP'), 'as_of' => $asOf->format('Y-m-d\TH:i:s.uP'), 'observation_hours' => $observationHours],
            'totals' => ['runs' => $runs->count(), ...array_map(fn ($stage) => count($sets[$stage]), array_combine($stages, $stages))],
            'v3' => ['runs' => count($v3Ids), 'totals' => array_map(fn ($stage) => count(array_intersect($sets[$stage], $v3Ids)), array_combine($v3Stages, $v3Stages)), 'stage_conversions' => $v3Conversions, 'quiz_elapsed_seconds' => ['count' => count($v3Elapsed), 'mean' => $v3Elapsed ? array_sum($v3Elapsed) / count($v3Elapsed) : null]],
            'stage_conversions' => $conversions, 'steps' => $steps, 'utm' => $utm,
            'quiz_elapsed_seconds' => ['count' => count($elapsed), 'mean' => $elapsed ? array_sum($elapsed) / count($elapsed) : null],
            'experiment' => ['id' => VisitorContext::EXPERIMENT, 'status' => 'insufficient_data', 'winner' => null, 'variants' => $variants],
        ];
    }

    private function rate(int $numerator, int $denominator): array
    {
        return ['numerator' => $numerator, 'denominator' => $denominator, 'rate' => $denominator ? $numerator / $denominator : null];
    }
}
