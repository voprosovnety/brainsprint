<?php

namespace App\Analytics;

use App\Funnel\Configuration;
use App\Funnel\V3State;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Separate append-only sample cohort; never creates real events or publishes a version. */
class SyntheticV3Scenario
{
    public function seed(CarbonImmutable $day): void
    {
        $source = file_get_contents(resource_path('funnel/brainsprint-v3.json'));
        $config = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        Configuration::validate($config);
        $version = 'brainsprint-'.substr(hash('sha256', $source), 0, 12);
        if (! DB::table('funnel_versions')->where('id', $version)->exists()) {
            return; // Preserve legacy-only fixture environments; never publish implicitly.
        }
        $day = $day->utc()->startOfDay();
        DB::transaction(function () use ($config, $version, $day) {
            DB::select('select pg_advisory_xact_lock(?)', [440043]);
            for ($i = 0; $i < 24; $i++) {
                $visitor = 'demo-v3:'.$day->toDateString().':'.$i;
                if (DB::table('events')->where('semantic_key', $visitor.':started')->exists()) { continue; }
                $id = (string) \Illuminate\Support\Str::uuid();
                $time = $i < 20 ? $day->subDays(3)->addHours(12)->addMinutes($i) : $day->addMinutes($i);
                $context = ['visitor_id' => $visitor, 'funnel_version_id' => $version, 'is_synthetic' => true,
                    'experiment_id' => null, 'variant' => null, 'attribution' => json_encode(['utm_source' => 'v3-sample', 'utm_campaign' => 'illustrative-sample'])];
                $current = $config['entry'];
                DB::table('funnel_runs')->insert([...$context, 'id' => $id, 'current_step' => $current, 'created_at' => $time, 'updated_at' => $time]);
                $event = function (string $name, string $step = '', ?string $key = null) use ($id, $context, $visitor, &$time) {
                    DB::table('events')->insert([...$context, 'run_id' => $id, 'name' => $name, 'step_id' => $step,
                        'semantic_key' => $key ?? $visitor.':'.$name.':'.$step, 'created_at' => $time->format('Y-m-d H:i:s.uP')]);
                };
                $event('funnel_started', '', $visitor.':started');
                $stop = ['remember-word', 'visual-nested-shapes', 'confirm-answers', 'report-offer', 'guide-offer', 'done'][$i % 6];
                $answers = [];
                $envelope = null;
                while ($current !== 'done') {
                    $screen = Configuration::screen($config, $current);
                    $time = $time->addSeconds(2);
                    $event(match ($screen['type']) { 'paywall' => 'paywall_viewed_v3', 'final' => 'report_viewed_v3', default => 'step_viewed' }, $current);
                    if ($current === $stop) { break; }
                    $answer = match ($screen['type']) {
                        'single_choice', 'scale' => ['choice' => $screen['options'][$i % count($screen['options'])]['id']],
                        'email' => ['email' => null],
                        'processing' => count($screen['prompts']) ? ['focus' => 'yes', 'practice' => $i % 2 ? 'yes' : 'no'] : ['acknowledged' => true],
                        'paywall' => ['offer_id' => $config['offer']['id'], 'acknowledged' => true],
                        'upsell' => ['decision' => $i % 4 < 2 ? 'accept' : 'decline'],
                        default => ['acknowledged' => true],
                    };
                    if ($screen['type'] === 'paywall') { $event('checkout_viewed_v3', $current); }
                    $answers[$current] = $answer;
                    $time = $time->addSeconds($screen['type'] === 'timed_reveal' ? 5 : 12 + $i);
                    $next = Configuration::next($config, $screen, $answer);
                    $envelope = V3State::advance($config, $screen, $answer, $next, $envelope);
                    if ((Configuration::screen($config, $next)['type'] ?? null) === 'paywall') {
                        $envelope['commerce']['deadline'] = $time->addSeconds($config['offer']['countdown_seconds'])->toIso8601String();
                    }
                    $event('step_completed', $current);
                    foreach (V3State::milestones($screen, $next, $config) as $name) { $event($name, $current); }
                    $current = $next;
                }
                DB::table('funnel_runs')->where('id', $id)->update(['current_step' => $current, 'answers' => json_encode((object) $answers),
                    'result' => $envelope ? json_encode($envelope) : null, 'completed_at' => $current === 'done' ? $time : null, 'updated_at' => $time]);
            }
        });
    }
}
