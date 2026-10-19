<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemoryRoundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $source = file_get_contents(resource_path('funnel/memory-v1.json'));
        DB::table('funnel_versions')->insert(['id' => 'historical-memory', 'configuration' => $source, 'published_at' => now()]);
        $this->withSession(['visitor_id' => 'owner']);
    }

    public function test_start_completion_and_retries_are_stable_and_persisted(): void
    {
        $run = $this->postJson('/api/v1/runs')->assertOk()->json();
        $this->postJson('/api/v1/runs')->assertOk()->assertJsonPath('id', $run['id']);
        $path = '/api/v1/runs/'.$run['id'].'/steps/memory/complete';
        $state = $this->postJson($path, ['answer' => ['cells' => [0, 5]]])->assertOk()->assertJsonPath('current_step', 'result')->json();
        $this->postJson($path, ['answer' => ['cells' => [5, 0]]])->assertOk()->assertExactJson($state);
        $this->getJson('/api/v1/runs/'.$run['id'])->assertOk()->assertExactJson($state)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('run.result', $state['result']);
        $this->postJson($path, ['answer' => ['cells' => [1]]])->assertStatus(409)->assertJsonPath('error.code', 'state_conflict');
        $this->postJson('/api/v1/runs')->assertOk()->assertJsonPath('result', $state['result']);
        $this->assertDatabaseCount('funnel_runs', 1);
        $this->assertDatabaseCount('events', 3);
        $record = DB::table('funnel_runs')->first();
        $this->assertSame(['memory' => ['cells' => [0, 5]]], json_decode($record->answers, true));
        $this->assertNotNull($record->completed_at);
        $this->assertSame($run['configuration_version'], $record->funnel_version_id);
        $this->assertSame('illustrative_demo', $state['result']['kind']);
    }

    public function test_ownership_is_required_for_reads_and_writes(): void
    {
        $id = $this->postJson('/api/v1/runs')->json('id');
        $this->withSession(['visitor_id' => 'another-owner']);
        $this->getJson('/api/v1/runs/'.$id)->assertNotFound();
        $this->postJson('/api/v1/runs/'.$id.'/steps/memory/complete', ['answer' => ['cells' => [0]]])->assertNotFound();
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('run', null);
        $this->assertNull(DB::table('funnel_runs')->first()->result);
    }

    public function test_invalid_steps_and_bounded_answers_do_not_change_state(): void
    {
        $id = $this->postJson('/api/v1/runs')->json('id');
        $path = '/api/v1/runs/'.$id.'/steps/memory/complete';
        foreach ([[16], [-1], [0, 0], ['bad'], [true], [0.5]] as $cells) {
            $this->postJson($path, ['answer' => ['cells' => $cells]])->assertStatus(422)->assertJsonPath('error.code', 'invalid_answer');
        }
        $this->postJson($path, ['answer' => ['cells' => [0], 'admin' => true]])->assertStatus(422);
        $this->postJson('/api/v1/runs/'.$id.'/steps/result/complete', ['answer' => ['cells' => [0]]])->assertStatus(409);
        $this->assertDatabaseCount('events', 1);
        $this->assertNull(DB::table('funnel_runs')->first()->result);
        $this->postJson($path, ['answer' => ['cells' => []]])->assertOk();
    }

    public function test_run_keeps_its_published_configuration(): void
    {
        $run = $this->postJson('/api/v1/runs')->json();
        $config = $run['configuration'];
        $config['steps'][0]['pattern'] = [1];
        DB::table('funnel_versions')->insert(['id' => 'new-version', 'configuration' => json_encode($config), 'published_at' => now()->addMinute()]);
        $this->getJson('/api/v1/bootstrap')->assertJsonPath('configuration_version', 'new-version')->assertJsonPath('run.configuration.steps.0.pattern', $run['configuration']['steps'][0]['pattern']);
        $this->postJson('/api/v1/runs')->assertJsonPath('configuration_version', $run['configuration_version']);
        $this->withSession(['visitor_id' => (string) Str::uuid()]);
        $this->postJson('/api/v1/runs')->assertJsonPath('configuration_version', 'new-version');
    }

    public function test_failed_milestone_rolls_back_answer_and_result(): void
    {
        $id = $this->postJson('/api/v1/runs')->json('id');
        DB::table('events')->insert(['run_id' => $id, 'name' => 'step_completed', 'step_id' => 'memory', 'created_at' => now()]);
        $this->postJson('/api/v1/runs/'.$id.'/steps/memory/complete', ['answer' => ['cells' => [0]]])->assertStatus(500);
        $run = DB::table('funnel_runs')->first();
        $this->assertSame('memory', $run->current_step);
        $this->assertNull($run->result);
        $this->assertSame([], json_decode($run->answers, true));
        $this->assertDatabaseCount('events', 2);
    }
}
