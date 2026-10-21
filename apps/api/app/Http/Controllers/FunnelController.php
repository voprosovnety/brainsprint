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
        $version = $id ? DB::table('funnel_versions')->find($id) : DB::table('funnel_versions')->whereRaw("configuration->'schema_version' = '3'::jsonb")->orderByDesc('published_at')->first();
        if (! $version) {
            throw new HttpException(503, 'The challenge is being prepared. Please try again shortly.');
        }

        return $version;
    }

    private function state(object $run): array
    {
        $configuration = json_decode($this->version($run->funnel_version_id)->configuration, true);
        $result = $run->result ? json_decode($run->result, true) : null;

        return array_merge([
            'id' => $run->id, 'configuration_version' => $run->funnel_version_id,
            'configuration' => $configuration,
            'current_step' => $run->current_step,
            'completed_steps' => array_keys(json_decode($run->answers, true)),
            'answers' => (object) json_decode($run->answers, true),
            'demo_completed' => $run->current_step === 'done',
            'started_at' => Carbon::parse($run->created_at)->utc()->toIso8601String(),
        ], V3State::publicState($result));
    }

    private function requireSupported(object $run): void
    {
        abort_unless(Configuration::supported(json_decode($this->version($run->funnel_version_id)->configuration, true)), 410,
            'This saved challenge is no longer available. Delete your saved data on the home screen to begin the current challenge.');
    }

    private function owned(Request $request, string $id, bool $lock = false): object
    {
        $q = DB::table('funnel_runs')->where('id', $id)->where('visitor_id', $this->visitor($request));
        $run = ($lock ? $q->lockForUpdate() : $q)->first();
        if (! $run) {
            throw new HttpException(404, 'This round is not available in this browser. Return to the home screen.');
        }

        $this->requireSupported($run);

        return $run;
    }

    private function event(string $id, string $name, string $step = ''): void
    {
        DB::table('events')->insert(array_merge(VisitorContext::run(DB::table('funnel_runs')->find($id)), ['run_id' => $id, 'name' => $name, 'step_id' => $step, 'created_at' => now()->format('Y-m-d H:i:s.uP')]));
    }

    public function bootstrap(Request $request)
    {
        $run = DB::table('funnel_runs')->where('visitor_id', $this->visitor($request))->first();
        $restartRequired = $run && ! Configuration::supported(json_decode($this->version($run->funnel_version_id)->configuration, true));
        $version = $this->version($restartRequired ? null : $run?->funnel_version_id);
        $configuration = json_decode($version->configuration, true);
        VisitorContext::capture($request, $request->only(VisitorContext::UTM));
        if ($run && ! $restartRequired) {
            DB::table('funnel_runs')->where('id', $run->id)->update(['updated_at' => now()]);
        }

        return response()->json(['experiment' => null, 'restart_required' => (bool) $restartRequired,
            'configuration_version' => $version->id, 'configuration' => $configuration,
            'run' => $run && ! $restartRequired ? $this->state($run) : null]);
    }

    public function start(Request $request)
    {
        $request->validate(['attribution' => ['sometimes', 'array']]);
        abort_if(array_diff(array_keys($request->all()), ['attribution']), 422, 'Only attribution is accepted.');
        $existing = DB::table('funnel_runs')->where('visitor_id', $this->visitor($request))->first();
        if ($existing) { $this->requireSupported($existing); }
        $version = $this->version($existing?->funnel_version_id);
        $context = VisitorContext::capture($request, $request->input('attribution', []));
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
            'name' => ['required', 'in:step_viewed,paywall_viewed_v3,checkout_viewed_v3,report_viewed_v3'],
            'run_id' => ['nullable', 'uuid'], 'step_id' => ['nullable', 'string', 'max:40'],
            'properties' => ['sometimes', 'array:surface'],
            'properties.surface' => ['sometimes', 'in:page'],
        ]);
        $step = $data['step_id'] ?? '';
        $runId = $data['run_id'] ?? null;
        abort_unless($runId && $step, 422, 'A run and screen are required.');
        $run = $this->owned($request, $runId);
        $configuration = json_decode($this->version($run->funnel_version_id)->configuration, true);
        $screen = Configuration::screen($configuration, $step);
        $answers = json_decode($run->answers, true);
        abort_unless($screen && ($run->current_step === $step || array_key_exists($step, $answers)), 409, 'This screen is not available yet.');
        $allowed = ['step_viewed'];
        if ($screen['type'] === 'paywall') { $allowed = [...$allowed, 'paywall_viewed_v3', 'checkout_viewed_v3']; }
        if ($screen['type'] === 'final' && V3State::publicState($run->result ? json_decode($run->result, true) : null)['commerce']['unlocked']) {
            $allowed[] = 'report_viewed_v3';
        }
        abort_unless(in_array($data['name'], $allowed, true), 422, 'Event does not match the screen.');
        $context = VisitorContext::run($run);
        $key = 'view:'.$data['name'].':'.$runId.':'.$step;
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
            if (! $screen || ! $screen['enabled']) {
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
            $result = V3State::advance($configuration, $screen, $answer, $next, $record->result ? json_decode($record->result, true) : null);
            DB::table('funnel_runs')->where('id', $run)->update([
                'answers' => json_encode($answers), 'result' => $result ? json_encode($result) : null, 'current_step' => $next,
                'completed_at' => ($next === 'done') ? now()->format('Y-m-d H:i:s.uP') : null, 'updated_at' => now()->format('Y-m-d H:i:s.uP'),
            ]);
            $this->event($run, 'step_completed', $step);
            foreach (V3State::milestones($screen, $next, $configuration) as $name) {
                $this->event($run, $name, $step);
            }

            return $this->state(DB::table('funnel_runs')->find($run));
        });

        return response()->json($state);
    }
}
