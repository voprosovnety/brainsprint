<?php

namespace App\Http\Controllers;

use App\Funnel\Answer;
use App\Funnel\Configuration;
use App\Funnel\V3State;
use App\Funnel\VisitorContext;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FunnelController extends Controller
{
    private function visitor(Request $request): string
    {
        return VisitorContext::id($request);
    }

    private function version(?string $id = null): object
    {
        $version = $id ? DB::table('funnel_versions')->find($id) : DB::table('funnel_versions')->orderByDesc('published_at')->first();
        if (! $version) {
            throw new HttpException(503, 'The challenge is being prepared. Please try again shortly.');
        }

        return $version;
    }

    private function state(object $run): array
    {
        $configuration = json_decode($this->version($run->funnel_version_id)->configuration, true);
        $result = $run->result ? json_decode($run->result, true) : null;
        $state = [
            'id' => $run->id, 'configuration_version' => $run->funnel_version_id,
            'configuration' => $configuration,
            'current_step' => $run->current_step,
            'completed_steps' => array_keys(json_decode($run->answers, true)),
            'answers' => (object) json_decode($run->answers, true),
            'demo_completed' => $run->current_step === 'done',
            'result' => Configuration::v3($configuration) ? null : $result,
        ];
        if (Configuration::v3($configuration)) {
            $state = array_merge($state, ['started_at' => Carbon::parse($run->created_at)->utc()->toIso8601String()], V3State::publicState($result));
        }

        return $state;
    }

    private function owned(Request $request, string $id, bool $lock = false): object
    {
        $q = DB::table('funnel_runs')->where('id', $id)->where('visitor_id', $this->visitor($request));
        $run = ($lock ? $q->lockForUpdate() : $q)->first();
        if (! $run) {
            throw new HttpException(404, 'This round is not available in this browser. Return to the home screen.');
        }

        return $run;
    }

    private function event(string $id, string $name, string $step = ''): void
    {
        DB::table('events')->insert(array_merge(VisitorContext::run(DB::table('funnel_runs')->find($id)), ['run_id' => $id, 'name' => $name, 'step_id' => $step, 'created_at' => now()->format('Y-m-d H:i:s.uP')]));
    }

    public function bootstrap(Request $request)
    {
        $run = DB::table('funnel_runs')->where('visitor_id', $this->visitor($request))->first();
        $version = $this->version($run?->funnel_version_id);
        $configuration = json_decode($version->configuration, true);
        $context = VisitorContext::capture($request, $request->only(VisitorContext::UTM), ! Configuration::v3($configuration));
        if (Configuration::v3($configuration)) {
            $request->session()->forget('landing_version');
        } else {
            $request->session()->put('landing_version', $version->id);
        }
        if ($run) {
            DB::table('funnel_runs')->where('id', $run->id)->update(['updated_at' => now()]);
        }

        $experimentId = Configuration::v3($configuration) ? null : ($run?->experiment_id ?? $context['experiment_id']);
        $variant = $run?->variant ?? $context['variant'];

        return response()->json(['experiment' => $experimentId ? ['id' => $experimentId, 'variant' => $variant] : null, 'configuration_version' => $version->id, 'configuration' => $configuration, 'run' => $run ? $this->state($run) : null]);
    }

    public function start(Request $request)
    {
        $request->validate(['attribution' => ['sometimes', 'array']]);
        abort_if(array_diff(array_keys($request->all()), ['attribution']), 422, 'Only attribution is accepted.');
        $existing = DB::table('funnel_runs')->where('visitor_id', $this->visitor($request))->first();
        $version = $this->version($existing?->funnel_version_id);
        $context = VisitorContext::capture($request, $request->input('attribution', []), ! Configuration::v3(json_decode($version->configuration, true)));
        $state = DB::transaction(function () use ($request, $context, $version) {
            $visitor = $this->visitor($request);
            $id = (string) Str::uuid();
            $inserted = DB::table('funnel_runs')->insertOrIgnore([
                ...$context, 'id' => $id, 'visitor_id' => $visitor, 'funnel_version_id' => $version->id,
                'current_step' => Configuration::entry(json_decode($version->configuration, true)), 'is_synthetic' => app()->environment(['local', 'testing']), 'created_at' => now()->format('Y-m-d H:i:s.uP'), 'updated_at' => now()->format('Y-m-d H:i:s.uP'),
            ]);
            if ($inserted) {
                $this->event($id, 'funnel_started');
            }
            $run = DB::table('funnel_runs')->where('visitor_id', $visitor)->first();

            return $this->state($run);
        });

        return response()->json($state);
    }

    public function exposure(Request $request)
    {
        abort_if(strlen($request->getContent()) > 4096, 413, 'Event is too large.');
        abort_if(array_diff(array_keys($request->all()), ['event_id', 'name', 'run_id', 'step_id', 'properties']), 422, 'Unexpected event fields.');
        $data = $request->validate([
            'event_id' => ['required', 'uuid'],
            'name' => ['required', 'in:landing_viewed,step_viewed,result_viewed,paywall_reached,paywall_viewed_v3,checkout_viewed_v3,report_viewed_v3'],
            'run_id' => ['nullable', 'uuid'], 'step_id' => ['nullable', 'string', 'max:40'],
            'properties' => ['sometimes', 'array:surface'],
            'properties.surface' => ['sometimes', 'in:page'],
        ]);
        $step = $data['step_id'] ?? '';
        $runId = $data['run_id'] ?? null;
        if ($data['name'] === 'landing_viewed') {
            abort_if($runId || $step !== '', 422, 'Landing views cannot reference a run or step.');
            $landingVersion = $request->session()->get('landing_version');
            abort_unless($landingVersion, 409, 'Initialize the page first.');
            abort_if(Configuration::v3(json_decode($this->version($landingVersion)->configuration, true)), 422, 'This configuration has no landing screen.');
            $context = VisitorContext::capture($request);
            $context['funnel_version_id'] = $landingVersion;
            $key = 'landing:'.$context['visitor_id'].':'.$context['experiment_id'];
        } else {
            abort_unless($runId && $step, 422, 'A run and screen are required.');
            $run = $this->owned($request, $runId);
            $configuration = json_decode($this->version($run->funnel_version_id)->configuration, true);
            $screen = Configuration::screen($configuration, $step);
            if (Configuration::legacy($configuration) && $step === 'result') {
                $screen = ['type' => 'result'];
            }
            $answers = json_decode($run->answers, true);
            abort_unless($screen && ($run->current_step === $step || array_key_exists($step, $answers)), 409, 'This screen is not available yet.');
            if (Configuration::v3($configuration)) {
                $allowed = ['step_viewed'];
                if ($screen['type'] === 'paywall') { $allowed = [...$allowed, 'paywall_viewed_v3', 'checkout_viewed_v3']; }
                if ($screen['type'] === 'final' && (V3State::publicState($run->result ? json_decode($run->result, true) : null)['commerce']['unlocked'])) {
                    $allowed[] = 'report_viewed_v3';
                }
                abort_unless(in_array($data['name'], $allowed, true), 422, 'Event does not match the screen.');
            } else {
                $expected = match ($screen['type']) {
                    'result' => 'result_viewed', 'boundary' => 'paywall_reached', default => 'step_viewed'
                };
                abort_unless($data['name'] === $expected, 422, 'Event does not match the screen.');
            }
            $context = VisitorContext::run($run);
            $key = Configuration::v3($configuration) ? 'view:'.$data['name'].':'.$runId.':'.$step : 'view:'.$runId.':'.$step;
        }
        DB::table('events')->insertOrIgnore(array_merge($context, [
            'run_id' => $runId, 'name' => $data['name'], 'step_id' => $step,
            'client_event_id' => $data['event_id'], 'semantic_key' => $key,
            'properties' => json_encode((object) ($data['properties'] ?? [])), 'created_at' => now()->format('Y-m-d H:i:s.uP'),
        ]));

        return response()->json(['accepted' => true]);
    }

    public function show(Request $request, string $run)
    {
        $owned = $this->owned($request, $run);
        DB::table('funnel_runs')->where('id', $owned->id)->update(['updated_at' => now()]);

        return response()->json($this->state($owned));
    }

    public function complete(Request $request, string $run, string $step)
    {
        $state = DB::transaction(function () use ($request, $run, $step) {
            $record = $this->owned($request, $run, true);
            $configuration = json_decode($this->version($record->funnel_version_id)->configuration, true);
            $screen = Configuration::screen($configuration, $step);
            if (! $screen || (! Configuration::legacy($configuration) && ! $screen['enabled'])) {
                throw new HttpException(409, 'This step is not part of your run. Reload to restore your progress.');
            }
            $answer = Answer::validate($request, $screen);
            $answers = json_decode($record->answers, true);
            if (array_key_exists($step, $answers)) {
                if (Answer::canonical($answers[$step]) !== $answer) {
                    throw new HttpException(409, 'This answer is already saved. Reload to restore your progress.');
                }

                return $this->state($record);
            }
            if ($record->current_step !== $step) {
                throw new HttpException(409, 'Complete the current step first. Reload to restore your progress.');
            }
            $answers[$step] = $answer;
            $next = Configuration::next($configuration, $screen, $answer);
            $quizCompleted = Configuration::legacy($configuration) || (Configuration::screen($configuration, $next)['type'] ?? null) === 'result';
            $result = $record->result ? json_decode($record->result, true) : null;
            if (Configuration::v3($configuration)) {
                $result = V3State::advance($configuration, $screen, $answer, $next, $result);
            }
            if ($quizCompleted && ! $result) {
                $result = [
                    'kind' => 'illustrative_demo',
                    'disclaimer' => $configuration['result']['disclaimer'],
                    'indicators' => [
                        ['label' => 'Visual memory', 'value' => random_int(60, 95)],
                        ['label' => 'Pattern recognition', 'value' => random_int(60, 95)],
                        ['label' => 'Logical reasoning', 'value' => random_int(60, 95)],
                    ],
                ];
            }
            DB::table('funnel_runs')->where('id', $run)->update([
                'answers' => json_encode($answers), 'result' => $result ? json_encode($result) : null, 'current_step' => $next,
                'completed_at' => ($next === 'done' || Configuration::legacy($configuration)) ? now()->format('Y-m-d H:i:s.uP') : null, 'updated_at' => now()->format('Y-m-d H:i:s.uP'),
            ]);
            $this->event($run, 'step_completed', $step);
            if ($quizCompleted) {
                $this->event($run, 'quiz_completed');
            }
            if (Configuration::v3($configuration)) {
                foreach (V3State::milestones($screen, $next, $configuration) as $name) {
                    $this->event($run, $name, $step);
                }
            } elseif ($next === 'done') {
                $this->event($run, 'demo_completed');
            }

            return $this->state(DB::table('funnel_runs')->find($run));
        });

        return response()->json($state);
    }
}
