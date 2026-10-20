<?php

namespace App\Lifecycle;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class Retention
{
    // Reads, writes and exposures define stored activity. Both datasets use the same policy.
    private function activity()
    {
        return DB::query()->fromSub(
            DB::table('funnel_runs')->selectRaw('visitor_id, updated_at AS activity_at')
                ->unionAll(DB::table('events')->leftJoin('funnel_runs', 'events.run_id', '=', 'funnel_runs.id')->whereRaw('COALESCE(events.visitor_id, funnel_runs.visitor_id) IS NOT NULL')->selectRaw('COALESCE(events.visitor_id, funnel_runs.visitor_id) AS visitor_id, events.created_at AS activity_at')),
            'activity'
        )->selectRaw('visitor_id, MAX(activity_at) AS last_activity')->groupBy('visitor_id');
    }

    public function run(bool $execute, int $limit): array
    {
        $now = CarbonImmutable::now();
        $cutoff = $now->subDays(30);
        $visitors = DB::query()->fromSub($this->activity(), 'visitors')->where('last_activity', '<', $cutoff)
            ->orderBy('last_activity')->orderBy('visitor_id')->limit($limit)->pluck('visitor_id');
        $counts = ['visitors' => 0, 'runs' => 0, 'events' => 0, 'sessions' => 0, 'admin_sessions' => 0];
        foreach ($visitors as $visitor) {
            DB::transaction(function () use ($visitor, $cutoff, $execute, &$counts) {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$visitor]);
                $last = $this->activity()->where('visitor_id', $visitor)->first();
                if (! $last || CarbonImmutable::parse($last->last_activity)->gte($cutoff)) {
                    return;
                }
                $runs = DB::table('funnel_runs')->where('visitor_id', $visitor);
                // Include legacy linked events even if their visitor column is null.
                $events = DB::table('events')->where(fn ($q) => $q->where('visitor_id', $visitor)->orWhereIn('run_id', (clone $runs)->select('id')));
                $counts['visitors']++;
                $counts['runs'] += (clone $runs)->count();
                $counts['events'] += (clone $events)->count();
                if ($execute) {
                    $events->delete();
                    $runs->delete();
                }
            });
        }
        $orphans = DB::table('events')->whereNull('run_id')->whereNull('visitor_id')->where('created_at', '<', $cutoff)->orderBy('id')->limit($limit)->pluck('id');
        $orphanQuery = DB::table('events')->whereIn('id', $orphans);
        $counts['unattributed_events'] = $execute ? $orphanQuery->delete() : $orphanQuery->count();
        foreach (['sessions' => (int) config('session.lifetime'), 'admin_sessions' => 120] as $table => $minutes) {
            // A bounded statement rechecks expiry at deletion; active sessions are preserved.
            $expired = fn () => DB::table($table)->where('last_activity', '<', $now->subMinutes($minutes)->timestamp);
            $ids = $expired()->orderBy('last_activity')->orderBy('id')->limit($limit)->pluck('id');
            $query = $expired()->whereIn('id', $ids);
            $counts[$table] = $execute ? $query->delete() : $query->count();
        }

        return ['mode' => $execute ? 'execute' : 'preview', 'cutoff' => $cutoff->toIso8601String(), 'limit' => $limit, 'counts' => $counts];
    }
}
