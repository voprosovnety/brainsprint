<?php

namespace Tests\Feature;

use App\Funnel\Configuration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CompletesFunnel;
use Tests\TestCase;

class FunnelProgressionTest extends TestCase
{
    use RefreshDatabase, CompletesFunnel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['visitor_id' => 'owner']);
        // A complete journey with retries exceeds a minute's normal production budget.
        config(['release.consumer_per_minute' => 2000, 'release.global_per_minute' => 4000]);
    }

    private function start(): array { return $this->postJson('/api/v1/runs')->assertOk()->json(); }

    public function test_full_funnel_persists_private_report_unlock_and_stable_terminal_retries(): void
    {
        $run = $this->start();
        $this->assertSame('remember-word', $run['current_step']);
        $this->postJson('/api/v1/runs')->assertJsonPath('id', $run['id']);
        $report = null;
        $teaser = null;
        $deadline = null;
        while ($run['current_step'] !== 'done') {
            $screen = Configuration::screen($run['configuration'], $run['current_step']);
            $answer = $this->answer($screen);
            $next = $this->complete($run, $screen['id'], $answer)->assertOk()->json();
            $this->complete($run, $screen['id'], $answer)->assertOk()->assertExactJson($next);
            $this->getJson('/api/v1/runs/'.$run['id'])->assertOk()->assertExactJson($next)->assertHeader('Cache-Control', 'no-store, private');
            $this->getJson('/api/v1/bootstrap')->assertJsonPath('run', $next);
            if ($next['teaser']) {
                $teaser ??= $next['teaser'];
                $this->assertSame($teaser, $next['teaser']);
            }
            if ($next['commerce']['deadline']) {
                $deadline ??= $next['commerce']['deadline'];
                $this->assertSame($deadline, $next['commerce']['deadline']);
            }
            if (! $next['commerce']['unlocked']) {
                $this->assertNull($next['result']);
            } else {
                $report ??= $next['result'];
                $this->assertSame($report, $next['result']);
            }
            if ($screen['type'] === 'confirmation') {
                $this->assertNotNull($next['teaser']);
                $this->assertNotNull(json_decode(DB::table('funnel_runs')->first()->result, true)['report']);
                $this->assertNull(DB::table('funnel_runs')->first()->completed_at);
            }
            $run = $next;
        }
        $this->assertTrue($run['demo_completed']);
        $this->assertCount(57, $run['completed_steps']);
        $this->assertSame('accept', $run['commerce']['upsell']);
        $this->assertSame('illustrative_sample', $report['kind']);
        $this->assertNotNull(DB::table('funnel_runs')->first()->completed_at);
        foreach (['funnel_started', 'quiz_confirmed_v3', 'processing_completed_v3', 'mock_unlocked_v3', 'upsell_decided_v3', 'funnel_completed_v3'] as $name) {
            $this->assertSame(1, DB::table('events')->where('name', $name)->count());
        }
        $this->assertDatabaseMissing('events', ['name' => 'paywall_viewed_v3']);
        $this->postJson('/api/v1/runs')->assertExactJson($run);
    }

    public function test_guide_decline_uses_only_its_final_and_preserves_immutable_decision(): void
    {
        $run = $this->advanceTo($this->start(), 'guide-offer');
        $screen = Configuration::screen($run['configuration'], 'guide-offer');
        $next = $this->complete($run, 'guide-offer', ['decision' => 'decline'])->assertOk()->assertJsonPath('current_step', $screen['decline_next'])->json();
        $this->complete($run, 'guide-offer', ['decision' => 'decline'])->assertExactJson($next);
        $this->complete($run, 'guide-offer', ['decision' => 'accept'])->assertStatus(409);
        $this->complete($run, $screen['next'], ['acknowledged' => true])->assertStatus(409);
        $this->complete($run, $screen['decline_next'], ['acknowledged' => true])->assertJsonPath('demo_completed', true);
    }

    public function test_ownership_future_screens_and_strict_answers_are_enforced_without_writes(): void
    {
        $run = $this->start();
        foreach ([false, 1, 'true'] as $value) { $this->complete($run, 'remember-word', ['acknowledged' => $value])->assertStatus(422); }
        $this->complete($run, 'remember-word', ['acknowledged' => true, 'next' => 'done'])->assertStatus(422);
        $this->postJson('/api/v1/runs/'.$run['id'].'/steps/remember-word/complete', ['answer' => ['acknowledged' => true], 'next' => 'done'])->assertStatus(422);
        $choice = collect($run['configuration']['steps'])->firstWhere('type', 'single_choice');
        $this->complete($run, $choice['id'], ['choice' => 'unknown'])->assertStatus(422);
        $this->complete($run, $choice['id'], ['choice' => $choice['options'][0]['id']])->assertStatus(409);
        $this->complete($run, 'missing', ['acknowledged' => true])->assertStatus(409);
        $this->complete($run, 'report-offer', ['offer_id' => 'unknown', 'acknowledged' => true])->assertStatus(422);
        $this->complete($run, 'report-offer', ['offer_id' => 'report-intro-v3', 'acknowledged' => true, 'card' => 'forbidden'])->assertStatus(422);
        $email = collect($run['configuration']['steps'])->firstWhere('type', 'email');
        $this->complete($run, $email['id'], ['email' => 'invalid'])->assertStatus(422);
        $this->withSession(['visitor_id' => 'stranger']);
        $this->getJson('/api/v1/runs/'.$run['id'])->assertNotFound();
        $this->complete($run, 'remember-word', ['acknowledged' => true])->assertNotFound();
        $this->assertDatabaseCount('events', 1);
        $this->assertSame([], json_decode(DB::table('funnel_runs')->first()->answers, true));
    }

    public function test_failed_milestone_rolls_back_answer_transition_and_private_report(): void
    {
        $run = $this->advanceTo($this->start(), 'confirm-answers');
        DB::table('events')->insert(['run_id' => $run['id'], 'name' => 'quiz_confirmed_v3', 'step_id' => 'confirm-answers', 'created_at' => now()]);
        $this->complete($run, 'confirm-answers', ['acknowledged' => true])->assertStatus(500);
        $this->getJson('/api/v1/runs/'.$run['id'])->assertExactJson($run);
        $this->assertNull(json_decode(DB::table('funnel_runs')->first()->result, true)['report']);
        $this->assertDatabaseMissing('events', ['name' => 'step_completed', 'step_id' => 'confirm-answers']);
    }

    public function test_publication_rejects_unsupported_schemas_and_broken_graphs_or_parameters(): void
    {
        $original = $this->start()['configuration'];
        $choiceIndex = array_search('single_choice', array_column($original['steps'], 'type'));
        foreach ([
            fn (&$c) => $c['steps'][0]['next'] = 'missing',
            fn (&$c) => $c['steps'][1]['next'] = $c['entry'],
            fn (&$c) => $c['steps'][0]['next'] = 'done',
            fn (&$c) => $c['steps'][0]['enabled'] = false,
            fn (&$c) => $c['steps'][$choiceIndex]['options'][0]['id'] = '!invalid',
            fn (&$c) => $c['steps'][0]['grid_size'] = 3,
            fn (&$c) => $c['offer']['intro_cents'] = 0,
            fn (&$c) => $c['schema_version'] = '3',
            fn (&$c) => $c['schema_version'] = 2,
            fn (&$c) => $c['schema_version'] = null,
            fn (&$c) => $c['result']['private'] = 'unsupported',
        ] as $mutate) {
            $config = $original;
            $mutate($config);
            try { Configuration::validate($config); $this->fail('Invalid configuration accepted.'); }
            catch (ValidationException $error) { $this->assertNotEmpty($error->errors()); }
        }
        $this->assertDatabaseCount('funnel_versions', 1);
    }

    public function test_republication_and_array_reordering_do_not_repin_existing_runs(): void
    {
        $old = $this->start();
        $config = $old['configuration'];
        $config['steps'][0]['word'] = 'New word';
        $config['steps'] = array_reverse($config['steps']);
        Configuration::validate($config);
        DB::table('funnel_versions')->insert(['id' => 'reconfigured', 'configuration' => json_encode($config), 'published_at' => now()->addMinute()]);
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('configuration_version', $old['configuration_version'])->assertJsonPath('run.configuration', $old['configuration']);
        $this->postJson('/api/v1/runs')->assertJsonPath('configuration_version', $old['configuration_version']);
        $this->withSession(['visitor_id' => 'new-owner']);
        $this->assertSame('reconfigured', $this->start()['configuration_version']);
    }

    public function test_retired_run_never_exposes_old_content_and_can_be_deleted_before_restart(): void
    {
        // Minimal unsupported records, not runnable historical fixtures.
        foreach (['{}', '{"schema_version":2}'] as $index => $configuration) {
            $version = 'retired-'.$index;
            DB::table('funnel_versions')->insert(['id' => $version, 'configuration' => $configuration, 'published_at' => now()->addMinute()]);
            $this->withSession(['visitor_id' => 'owner']);
            $id = (string) Str::uuid();
            DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => 'owner', 'funnel_version_id' => $version, 'current_step' => 'old-step', 'answers' => '{"old-step":{"private":true}}', 'result' => '{"private":"old-result"}', 'created_at' => now(), 'updated_at' => now()]);
            $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('restart_required', true)->assertJsonPath('run', null)->assertJsonPath('configuration.schema_version', 3)->assertDontSee('old-step')->assertDontSee('old-result');
            $this->getJson('/api/v1/runs/'.$id)->assertStatus(410)->assertJsonPath('error.code', 'run_retired');
            $this->postJson('/api/v1/runs')->assertStatus(410);
            $this->postJson('/api/v1/runs/'.$id.'/steps/old-step/complete', ['answer' => ['acknowledged' => true]])->assertStatus(410);
            $this->postJson('/api/v1/events', ['event_id' => (string) Str::uuid(), 'name' => 'step_viewed', 'run_id' => $id, 'step_id' => 'old-step'])->assertStatus(410);
            $this->withSession(['visitor_id' => 'stranger']);
            $this->getJson('/api/v1/runs/'.$id)->assertNotFound();
            $this->postJson('/api/v1/runs/'.$id.'/steps/old-step/complete', ['answer' => ['acknowledged' => true]])->assertNotFound();
            $this->withSession(['visitor_id' => 'owner']);
            $this->call('POST', '/api/v1/privacy/delete', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertOk();
            $this->assertDatabaseMissing('funnel_runs', ['id' => $id]);
            $this->getJson('/api/v1/bootstrap')->assertJsonPath('restart_required', false);
            $this->assertSame(3, $this->start()['configuration']['schema_version']);
        }
    }
}
