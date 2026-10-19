<?php

namespace App\Analytics;

use App\Funnel\Configuration;
use App\Funnel\VisitorContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Append-only daily fixture. Existing fixture rows and every nonfixture row remain untouched. */
class SyntheticScenario
{
    public function seed(CarbonImmutable $day): void
    {
        $day = $day->utc()->startOfDay();
        $source = file_get_contents(resource_path('funnel/brainsprint-v2.json'));
        $config = json_decode($source, true, flags: JSON_THROW_ON_ERROR);
        Configuration::validate($config);
        $version = 'brainsprint-'.substr(hash('sha256', $source), 0, 12);
        DB::transaction(function () use ($day, $config, $version) {
            // Serializes concurrent invocations of this small seed without resetting any data.
            DB::select('select pg_advisory_xact_lock(?)', [440041]);
            $keys = array_map(fn ($i) => 'demo-v1:'.$day->toDateString().':'.$i.':landing', range(0, 23));
            if (DB::table('events')->whereIn('semantic_key', $keys)->count() === 24) {
                return;
            }
            if (! DB::table('funnel_versions')->where('id', $version)->exists()) {
                throw new \RuntimeException('Publish the checked-in configuration with php artisan db:seed before seeding demo analytics.');
            }
            for ($i = 0; $i < 24; $i++) {
                $visitor = 'demo-v1:'.$day->toDateString().':'.$i;
                if (DB::table('events')->where('semantic_key', $visitor.':landing')->exists()) {
                    continue;
                }
                $at = $i < 20 ? $day->subDays(3)->addHours(12)->addMinutes($i) : $day;
                $context = ['visitor_id' => $visitor, 'funnel_version_id' => $version, 'is_synthetic' => true,
                    'experiment_id' => VisitorContext::EXPERIMENT, 'variant' => $i % 2 ? 'B' : 'A',
                    'attribution' => json_encode($i % 3 ? ['utm_source' => 'demo-newsletter', 'utm_campaign' => 'illustrative-sample'] : (object) [])];
                $event = function ($run, $name, $step, $time) use ($context, $visitor) {
                    DB::table('events')->insert([...$context, 'run_id' => $run, 'name' => $name, 'step_id' => $step,
                        'semantic_key' => $visitor.':'.$name.':'.$step, 'created_at' => $time->format('Y-m-d H:i:s.uP')]);
                };
                DB::table('events')->insert([...$context, 'run_id' => null, 'name' => 'landing_viewed', 'step_id' => '', 'semantic_key' => $visitor.':landing', 'created_at' => $at]);
                if ($i % 4 === 0) {
                    continue;
                } // An exposed visitor need not start.
                $hash = md5($visitor);
                $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
                $started = $at->addSeconds(30);
                $answers = [];
                $result = null;
                $current = Configuration::entry($config);
                $time = $started;
                DB::table('funnel_runs')->insert([...$context, 'id' => $id, 'current_step' => $current, 'created_at' => $started, 'updated_at' => $started]);
                $event($id, 'funnel_started', '', $started);
                $stop = ['welcome', 'memory-one', 'logic-one', 'demo-result', 'finish', 'done'][$i % 6];
                while ($current !== 'done') {
                    $screen = Configuration::screen($config, $current);
                    $time = $time->addSeconds(2);
                    $view = match ($screen['type']) {
                        'result' => 'result_viewed', 'boundary' => 'paywall_reached', default => 'step_viewed'
                    };
                    $event($id, $view, $current, $time);
                    if ($current === $stop) {
                        break;
                    }
                    $answer = match ($screen['type']) {
                        'tutorial' => ['cells' => $i % 2 ? [] : $screen['pattern']],
                        'visual_memory' => ['cells' => $screen['pattern']],
                        'pattern' => ['choice' => $screen['correct_choice']],
                        'logic' => ['order' => $screen['correct_order']],
                        default => ['acknowledged' => true],
                    };
                    $answers[$current] = $answer;
                    $time = $time->addSeconds(18 + $i);
                    $event($id, 'step_completed', $current, $time);
                    $next = Configuration::next($config, $screen, $answer);
                    if ((Configuration::screen($config, $next)['type'] ?? null) === 'result') {
                        $event($id, 'quiz_completed', '', $time);
                        $result = ['kind' => 'illustrative_demo', 'disclaimer' => $config['result']['disclaimer'], 'indicators' => [
                            ['label' => 'Visual memory', 'value' => 60 + $i], ['label' => 'Pattern recognition', 'value' => 65 + $i], ['label' => 'Logical reasoning', 'value' => 70 + $i],
                        ]];
                    }
                    $current = $next;
                    if ($current === 'done') {
                        $event($id, 'demo_completed', '', $time);
                    }
                }
                DB::table('funnel_runs')->where('id', $id)->update(['current_step' => $current, 'answers' => json_encode((object) $answers),
                    'result' => $result ? json_encode($result) : null, 'completed_at' => $current === 'done' ? $time : null, 'updated_at' => $time]);
            }
        });
    }
}
