<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Funnel\Answer;
use App\Funnel\Configuration;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FunnelController extends Controller
{
    private function visitor(Request $request): string
    {
        // Stable even when two first writes arrive together after CSRF initialization.
        $id = $request->session()->get('visitor_id') ?? hash_hmac('sha256', $request->session()->getId(), config('app.key'));
        $request->session()->put('visitor_id', $id);

        return $id;
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
        return [
            'id' => $run->id, 'configuration_version' => $run->funnel_version_id,
            'configuration' => json_decode($this->version($run->funnel_version_id)->configuration, true),
            'current_step' => $run->current_step,
            'completed_steps' => array_keys(json_decode($run->answers, true)),
            'answers' => (object) json_decode($run->answers, true),
            'demo_completed' => $run->current_step === 'done',
            'result' => $run->result ? json_decode($run->result, true) : null,
        ];
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
        DB::table('events')->insert(['run_id' => $id, 'name' => $name, 'step_id' => $step, 'is_synthetic' => DB::table('funnel_runs')->where('id', $id)->value('is_synthetic'), 'created_at' => now()]);
    }

    public function bootstrap(Request $request)
    {
        $version = $this->version();
        $run = DB::table('funnel_runs')->where('visitor_id', $this->visitor($request))->first();

        return response()->json(['configuration_version' => $version->id, 'configuration' => json_decode($version->configuration, true), 'run' => $run ? $this->state($run) : null]);
    }

    public function start(Request $request)
    {
        $state = DB::transaction(function () use ($request) {
            $version = $this->version();
            $visitor = $this->visitor($request);
            $id = (string) Str::uuid();
            $inserted = DB::table('funnel_runs')->insertOrIgnore([
                'id' => $id, 'visitor_id' => $visitor, 'funnel_version_id' => $version->id,
                'current_step' => Configuration::entry(json_decode($version->configuration, true)), 'is_synthetic' => app()->environment(['local', 'testing']), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inserted) {
                $this->event($id, 'funnel_started');
            }
            $run = DB::table('funnel_runs')->where('visitor_id', $visitor)->first();

            return $this->state($run);
        });

        return response()->json($state);
    }

    public function show(Request $request, string $run)
    {
        return response()->json($this->state($this->owned($request, $run)));
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
                if ($answers[$step] !== $answer) {
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
                'completed_at' => ($next === 'done' || Configuration::legacy($configuration)) ? now() : null, 'updated_at' => now(),
            ]);
            $this->event($run, 'step_completed', $step);
            if ($quizCompleted) { $this->event($run, 'quiz_completed'); }
            if ($next === 'done') { $this->event($run, 'demo_completed'); }

            return $this->state(DB::table('funnel_runs')->find($run));
        });

        return response()->json($state);
    }
}
