<?php

namespace Tests\Feature;

use App\Funnel\Configuration;
use Tests\Fixtures\LegacyFunnelSeeder as DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FunnelProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withSession(['visitor_id' => 'owner']);
    }

    private function start(): array { return $this->postJson('/api/v1/runs')->assertOk()->json(); }
    private function complete(array $run, string $step, array $answer) {
        return $this->postJson('/api/v1/runs/'.$run['id'].'/steps/'.$step.'/complete', ['answer' => $answer]);
    }
    private function answer(array $screen): array {
        return match ($screen['type']) {
            'tutorial', 'visual_memory' => ['cells' => $screen['pattern']],
            'pattern' => ['choice' => $screen['correct_choice']],
            'logic' => ['order' => $screen['correct_order']],
            default => ['acknowledged' => true],
        };
    }

    public function test_full_funnel_is_transactional_and_result_and_terminal_retries_are_stable(): void
    {
        $run = $this->start();
        $this->postJson('/api/v1/runs')->assertJsonPath('id', $run['id']);
        $result = null;
        while ($run['current_step'] !== 'done') {
            $step = $run['current_step'];
            $screen = Configuration::screen($run['configuration'], $step);
            $answer = $this->answer($screen);
            $next = $this->complete($run, $step, $answer)->assertOk()->json();
            $this->complete($run, $step, $answer)->assertOk()->assertExactJson($next);
            $this->getJson('/api/v1/runs/'.$run['id'])->assertOk()->assertExactJson($next)->assertHeader('Cache-Control', 'no-store, private');
            $this->getJson('/api/v1/bootstrap')->assertJsonPath('run', $next);
            if ($next['result']) {
                $result ??= $next['result'];
                $this->assertSame($result, $next['result']);
            }
            if ($next['current_step'] === 'demo-result') {
                $this->assertNull(DB::table('funnel_runs')->first()->completed_at);
                $this->assertDatabaseHas('events', ['name' => 'quiz_completed']);
                $this->assertDatabaseMissing('events', ['name' => 'demo_completed']);
            }
            $run = $next;
        }
        $this->assertTrue($run['demo_completed']);
        $this->assertNotNull(DB::table('funnel_runs')->first()->completed_at);
        $this->assertCount(10, $run['completed_steps']);
        $this->assertNotContains('memory-guide', $run['completed_steps']);
        $this->assertDatabaseCount('events', 13);
        $this->assertSame(1, DB::table('events')->where('name', 'quiz_completed')->count());
        $this->assertSame(1, DB::table('events')->where('name', 'demo_completed')->count());
        $this->assertSame($result['disclaimer'], 'Illustrative demo values — not calculated from your answers.');
        $this->assertSame(3, count($result['indicators']));
        $this->assertDatabaseMissing('events', ['name' => 'paywall_reached']);
        $this->postJson('/api/v1/runs')->assertExactJson($run);
    }

    public function test_tutorial_branch_uses_canonical_saved_answer_and_rejoins(): void
    {
        $run = $this->start();
        $this->complete($run, 'memory-one', ['cells' => []])->assertStatus(409);
        $this->complete($run, 'welcome', ['acknowledged' => true])->assertOk();
        $guide = $this->complete($run, 'memory-tutorial', ['cells' => []])->assertOk()->assertJsonPath('current_step', 'memory-guide')->json();
        $this->complete($run, 'memory-tutorial', ['cells' => []])->assertExactJson($guide);
        $this->complete($run, 'memory-tutorial', ['cells' => [0, 4, 8]])->assertStatus(409);
        $this->complete($run, 'memory-one', ['cells' => []])->assertStatus(409);
        $this->complete($run, 'memory-guide', ['acknowledged' => true])->assertOk()->assertJsonPath('current_step', 'memory-one');
        $this->withSession(['visitor_id' => 'correct-owner']);
        $run = $this->start();
        $this->complete($run, 'welcome', ['acknowledged' => true])->assertOk();
        $this->complete($run, 'memory-tutorial', ['cells' => [8, 0, 4]])->assertOk()->assertJsonPath('current_step', 'memory-one');
        $this->complete($run, 'memory-guide', ['acknowledged' => true])->assertStatus(409);
    }

    public function test_wrong_owner_skips_and_invalid_answers_are_rejected_without_writes(): void
    {
        $run = $this->start();
        $this->complete($run, 'finish', ['acknowledged' => true])->assertStatus(409);
        foreach ([false, 1, 'true'] as $value) { $this->complete($run, 'welcome', ['acknowledged' => $value])->assertStatus(422); }
        $this->complete($run, 'welcome', ['acknowledged' => true, 'next' => 'finish'])->assertStatus(422);
        $this->postJson('/api/v1/runs/'.$run['id'].'/steps/welcome/complete', ['answer' => ['acknowledged' => true], 'next' => 'finish'])->assertStatus(422);
        $this->complete($run, 'memory-tutorial', ['cells' => [9]])->assertStatus(422);
        foreach ([[true], ['1'], [1.1], [-1], [0, 0]] as $cells) { $this->complete($run, 'memory-tutorial', ['cells' => $cells])->assertStatus(422); }
        $this->complete($run, 'pattern-one', ['choice' => 'unknown'])->assertStatus(422);
        $this->complete($run, 'logic-one', ['order' => ['sun', 'star', 'star']])->assertStatus(422);
        $this->complete($run, 'logic-one', ['order' => ['sun', 'star']])->assertStatus(422);
        $this->complete($run, 'logic-one', ['order' => ['a' => 'sun', 'b' => 'star', 'c' => 'moon']])->assertStatus(422);
        $this->complete($run, 'memory-tutorial', ['cells' => ['a' => 1]])->assertStatus(422);
        $this->complete($run, 'logic-one', ['order' => ['sun', 'star', 'secret']])->assertStatus(422);
        $this->complete($run, 'nonexistent', ['acknowledged' => true])->assertStatus(409);
        $this->withSession(['visitor_id' => 'stranger']);
        $this->getJson('/api/v1/runs/'.$run['id'])->assertNotFound();
        $this->complete($run, 'welcome', ['acknowledged' => true])->assertNotFound();
        $this->assertDatabaseCount('events', 1);
        $this->assertSame([], json_decode(DB::table('funnel_runs')->first()->answers, true));
    }

    public function test_failed_milestone_rolls_back_progress_and_generated_result(): void
    {
        $run = $this->start();
        while ($run['current_step'] !== 'logic-two') {
            $screen = Configuration::screen($run['configuration'], $run['current_step']);
            $run = $this->complete($run, $screen['id'], $this->answer($screen))->assertOk()->json();
        }
        DB::table('events')->insert(['run_id' => $run['id'], 'name' => 'quiz_completed', 'created_at' => now()]);
        $this->complete($run, 'logic-two', ['order' => ['wave', 'leaf', 'circle', 'spark']])->assertStatus(500);
        $this->getJson('/api/v1/runs/'.$run['id'])->assertExactJson($run);
        $this->assertDatabaseMissing('events', ['step_id' => 'logic-two']);
    }

    public function test_publication_rejects_broken_graphs_and_parameters(): void
    {
        $original = json_decode(file_get_contents(resource_path('funnel/brainsprint-v2.json')), true);
        $mutations = [
            fn (&$c) => $c['steps'][0]['next'] = 'missing',
            fn (&$c) => $c['steps'][3]['next'] = 'welcome',
            fn (&$c) => $c['steps'][2]['next'] = 'memory-tutorial',
            fn (&$c) => $c['steps'][1]['branch']['incorrect'] = 'missing',
            fn (&$c) => $c['steps'][2]['next'] = 'memory-two',
            fn (&$c) => $c['steps'][3]['grid_size'] = 20,
            fn (&$c) => $c['steps'][5]['correct_choice'] = 'missing',
            fn (&$c) => $c['steps'][7]['correct_order'] = ['sun', 'sun', 'moon'],
            fn (&$c) => $c['steps'][3]['next'] = 'done',
            fn (&$c) => $c['steps'][9]['enabled'] = false,
            fn (&$c) => $c['schema_version'] = '2',
            fn (&$c) => $c['steps'][0]['enabled'] = 1,
            fn (&$c) => $c['steps'][1]['pattern'] = ['0', '4', '8'],
            fn (&$c) => $c['steps'][0]['grid_size'] = 3,
            fn (&$c) => $c['result']['private'] = 'unsupported',
        ];
        foreach ($mutations as $mutate) {
            $config = $original;
            $mutate($config);
            try { Configuration::validate($config); $this->fail('Invalid configuration accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertDatabaseCount('funnel_versions', 1);
    }

    public function test_configuration_reordering_disabling_and_pinning_affect_only_new_runs(): void
    {
        $old = $this->start();
        $config = $old['configuration'];
        $config['steps'][3]['enabled'] = false;
        $config['steps'][4]['grid_size'] = 3;
        $config['steps'][4]['pattern'] = [0, 4];
        $config['steps'][4]['reveal_seconds'] = 3;
        // Reorder two pattern rounds through explicit transitions; array order is irrelevant.
        $config['steps'][4]['next'] = 'pattern-two';
        $config['steps'][6]['next'] = 'pattern-one';
        $config['steps'][5]['next'] = 'logic-one';
        $config['steps'] = array_reverse($config['steps']);
        Configuration::validate($config);
        DB::table('funnel_versions')->insert(['id' => 'reconfigured', 'configuration' => json_encode($config), 'published_at' => now()->addMinute()]);
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('configuration_version', 'reconfigured')->assertJsonPath('run.configuration', $old['configuration']);
        $this->postJson('/api/v1/runs')->assertJsonPath('configuration_version', $old['configuration_version']);
        $this->withSession(['visitor_id' => 'new-owner']);
        $run = $this->start();
        $this->assertSame('reconfigured', $run['configuration_version']);
        $this->complete($run, 'welcome', ['acknowledged' => true])->assertOk();
        $this->complete($run, 'memory-tutorial', ['cells' => [0, 4, 8]])->assertJsonPath('current_step', 'memory-two');
        $this->complete($run, 'memory-one', ['cells' => []])->assertStatus(409);
        $this->complete($run, 'memory-two', ['cells' => [3, 8]])->assertJsonPath('current_step', 'pattern-two');
        $this->complete($run, 'pattern-two', ['choice' => 'left'])->assertJsonPath('current_step', 'pattern-one');
    }
}
