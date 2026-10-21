<?php

namespace App\Analytics;

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
        $runs = DB::table('funnel_runs')->whereIn('funnel_version_id', DB::table('funnel_versions')->select('id')->whereRaw("configuration->'schema_version' = '3'::jsonb"))->where('is_synthetic', $synthetic)->where('created_at', '>=', $fromSql)->where('created_at', '<', $toSql)->get()->keyBy('id');
        $events = DB::table('events')->where('is_synthetic', $synthetic)->whereIn('run_id', $runs->keys())->where('created_at', '<=', $asOfSql)->get();
        $byRun = $events->groupBy('run_id');
        $stages = ['funnel_started', 'quiz_confirmed_v3', 'processing_completed_v3', 'paywall_viewed_v3', 'checkout_viewed_v3', 'mock_unlocked_v3', 'upsell_decided_v3', 'report_viewed_v3', 'funnel_completed_v3'];
        $sets = [];
        foreach ($stages as $stage) {
            $sets[$stage] = $events->where('name', $stage)->pluck('run_id')->unique()->all();
        }
        $conversions = [];
        foreach (array_slice($stages, 1) as $i => $stage) {
            $previous = $stages[$i];
            $conversions[$stage] = $this->rate(count(array_intersect($sets[$previous], $sets[$stage])), count($sets[$previous])) + ['from' => $previous];
        }
        $elapsed = [];
        foreach ($runs as $id => $run) {
            $quiz = ($byRun[$id] ?? collect())->where('name', 'quiz_confirmed_v3')->sortBy('created_at')->first();
            if ($quiz && $quiz->created_at >= $run->created_at) {
                $elapsed[] = CarbonImmutable::parse($run->created_at)->diffInSeconds(CarbonImmutable::parse($quiz->created_at));
            }
        }
        $stepGroups = $events->filter(fn ($e) => $e->step_id !== '' && in_array($e->name, ['step_viewed', 'paywall_viewed_v3', 'report_viewed_v3', 'step_completed'], true))
            ->groupBy(fn ($e) => $runs[$e->run_id]->funnel_version_id.':'.$e->step_id);
        $steps = [];
        foreach ($stepGroups as $group) {
            $first = $group->first();
            $views = $group->whereIn('name', ['step_viewed', 'paywall_viewed_v3', 'report_viewed_v3'])->sortBy('created_at')->unique('run_id');
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
            return ['attribution' => json_decode($key, true) ?: (object) [], 'runs' => count($ids), 'quiz_confirmed_v3' => count(array_intersect($ids, $sets['quiz_confirmed_v3']))];
        })->values()->all();

        return [
            'dataset' => $dataset->value, 'cohort' => ['from' => $from->format('Y-m-d\TH:i:s.uP'), 'to_exclusive' => $to->format('Y-m-d\TH:i:s.uP'), 'as_of' => $asOf->format('Y-m-d\TH:i:s.uP'), 'observation_hours' => $observationHours],
            'totals' => ['runs' => $runs->count(), ...array_map(fn ($stage) => count($sets[$stage]), array_combine($stages, $stages))],
            'stage_conversions' => $conversions, 'steps' => $steps, 'utm' => $utm,
            'quiz_elapsed_seconds' => ['count' => count($elapsed), 'mean' => $elapsed ? array_sum($elapsed) / count($elapsed) : null],
        ];
    }

    private function rate(int $numerator, int $denominator): array
    {
        return ['numerator' => $numerator, 'denominator' => $denominator, 'rate' => $denominator ? $numerator / $denominator : null];
    }
}
