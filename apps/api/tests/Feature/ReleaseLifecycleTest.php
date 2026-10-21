<?php

namespace Tests\Feature;

use App\Lifecycle\Retention;
use App\Models\AdminUser;
use Carbon\CarbonImmutable as Time;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReleaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function createRun(string $visitor, $when, bool $synthetic = false): string
    {
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => $visitor, 'funnel_version_id' => DB::table('funnel_versions')->value('id'), 'current_step' => 'remember-word', 'created_at' => $when, 'updated_at' => $when, 'is_synthetic' => $synthetic]);

        return $id;
    }

    private function event(?string $visitor, $when, ?string $run = null): void
    {
        DB::table('events')->insert(['visitor_id' => $visitor, 'run_id' => $run, 'name' => 'step_viewed', 'created_at' => $when, 'is_synthetic' => true]);
    }

    public function test_deletion_removes_only_owned_data_including_unlinked_and_run_linked_events(): void
    {
        $owned = $this->createRun('owner', now());
        $other = $this->createRun('other', now());
        $this->event('owner', now());
        $this->event(null, now(), $owned);
        $this->event('other', now());
        DB::table('admin_sessions')->insert(['id' => 'admin', 'payload' => '', 'last_activity' => time()]);
        $this->withSession(['visitor_id' => 'owner', 'attribution' => ['utm_source' => 'old']]);
        $oldSession = session()->getId();
        $this->call('POST', '/api/v1/privacy/delete', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertOk()->assertExactJson(['deleted' => true])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseMissing('funnel_runs', ['id' => $owned]);
        $this->assertDatabaseMissing('events', ['visitor_id' => 'owner']);
        $this->assertDatabaseMissing('events', ['run_id' => $owned]);
        $this->assertDatabaseHas('funnel_runs', ['id' => $other]);
        $this->assertDatabaseHas('events', ['visitor_id' => 'other']);
        $this->assertDatabaseHas('admin_sessions', ['id' => 'admin']);
        $this->assertDatabaseMissing('sessions', ['id' => $oldSession]);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertFalse(session()->has('attribution'));
        $this->getJson('/api/v1/bootstrap')->assertOk()->assertJsonPath('run', null);
        $this->assertNotSame('owner', session('visitor_id'));
        $this->getJson('/api/v1/runs/'.$other)->assertNotFound();
    }

    public function test_deletion_failure_rolls_back_events_and_preserves_session(): void
    {
        $id = $this->createRun('rollback-owner', now());
        $this->event('rollback-owner', now());
        $bootstrap = $this->withSession(['visitor_id' => 'rollback-owner'])->getJson('/api/v1/bootstrap')->assertOk();
        $session = $bootstrap->getCookie(config('session.cookie'))->getValue();
        $this->withCookie(config('session.cookie'), $session);
        DB::unprepared("CREATE FUNCTION reject_privacy_delete() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'isolated forced failure'; END $$");
        DB::unprepared('CREATE TRIGGER reject_privacy_delete BEFORE DELETE ON funnel_runs FOR EACH ROW EXECUTE FUNCTION reject_privacy_delete()');
        try {
            $this->call('POST', '/api/v1/privacy/delete', [], $this->prepareCookiesForRequest(), [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertStatus(500)->assertDontSee('isolated forced failure');
            $this->assertDatabaseHas('funnel_runs', ['id' => $id]);
            $this->assertDatabaseHas('events', ['visitor_id' => 'rollback-owner']);
            $this->assertTrue(hash_equals($session, session()->getId()), 'Failed deletion must preserve the consumer session.');
            $this->assertSame('rollback-owner', session('visitor_id'));
        } finally {
            DB::unprepared('DROP TRIGGER reject_privacy_delete ON funnel_runs');
            DB::unprepared('DROP FUNCTION reject_privacy_delete()');
        }
    }

    public function test_deletion_before_start_removes_unlinked_events_and_rejects_identity_input(): void
    {
        $this->event('unlinked-visitor', now());
        $this->withSession(['visitor_id' => 'unlinked-visitor']);
        $this->postJson('/api/v1/privacy/delete', ['visitor_id' => 'other'])->assertUnprocessable();
        $this->call('POST', '/api/v1/privacy/delete?visitor_id=other', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertUnprocessable();
        $this->assertDatabaseHas('events', ['visitor_id' => 'unlinked-visitor']);
        $this->call('POST', '/api/v1/privacy/delete', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertOk();
        $this->assertDatabaseCount('events', 0);
    }

    public function test_deletion_requires_real_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->call('POST', '/api/v1/privacy/delete', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{}')->assertStatus(419);
    }

    public function test_retention_preview_and_execution_preserve_freshness_versions_admins_and_live_sessions(): void
    {
        $this->travelTo(Time::parse('2026-10-19T12:00:00Z'));
        $old = now()->subDays(31);
        $stale = $this->createRun('stale-real', $old);
        $sample = $this->createRun('stale-synthetic', $old, true);
        $fresh = $this->createRun('fresh', now());
        $recentEvent = $this->createRun('old-run-new-event', $old);
        $this->event(null, now(), $recentEvent); // run-linked event still refreshes the owning visitor
        $this->event('stale-real', $old);
        $this->event(null, $old, $stale);
        $this->event('stale-orphan', $old);
        $this->event('boundary', now()->subDays(30));
        $this->event('fresh-orphan', now());
        $this->event(null, $old);
        $admin = AdminUser::create(['email' => 'retention@example.test', 'name' => 'Admin', 'password' => 'isolated-test-secret', 'is_admin' => true]);
        foreach (['sessions' => 10080, 'admin_sessions' => 120] as $table => $minutes) {
            DB::table($table)->insert([
                ['id' => 'old', 'payload' => '', 'last_activity' => now()->subMinutes($minutes + 1)->timestamp],
                ['id' => 'live', 'payload' => '', 'last_activity' => now()->timestamp],
                ['id' => 'boundary', 'payload' => '', 'last_activity' => now()->subMinutes($minutes)->timestamp],
            ]);
        }
        $before = DB::table('events')->count();
        $this->artisan('privacy:retention')->assertSuccessful();
        $this->assertSame($before, DB::table('events')->count());
        $preview = app(Retention::class)->run(false, 500);
        $this->assertSame(3, $preview['counts']['visitors']);
        $this->assertSame(2, $preview['counts']['runs']);
        $this->assertSame(3, $preview['counts']['events']);
        $this->assertSame(1, $preview['counts']['unattributed_events']);
        $this->artisan('privacy:retention', ['--execute' => true])->assertSuccessful();
        foreach ([$stale, $sample] as $id) {
            $this->assertDatabaseMissing('funnel_runs', ['id' => $id]);
        }
        foreach ([$fresh, $recentEvent] as $id) {
            $this->assertDatabaseHas('funnel_runs', ['id' => $id]);
        }
        foreach (['fresh-orphan', 'boundary'] as $visitor) {
            $this->assertDatabaseHas('events', ['visitor_id' => $visitor]);
        }
        foreach (['sessions', 'admin_sessions'] as $table) {
            $this->assertDatabaseMissing($table, ['id' => 'old']);
            foreach (['live', 'boundary'] as $id) {
                $this->assertDatabaseHas($table, ['id' => $id]);
            }
        }
        $this->assertDatabaseHas('admin_users', ['id' => $admin->id]);
        $this->assertDatabaseCount('funnel_versions', 1);
        $this->assertSame(0, app(Retention::class)->run(true, 500)['counts']['visitors']);
    }

    public function test_retention_limit_and_recent_restore_activity(): void
    {
        $old = now()->subDays(31);
        foreach (['a', 'b', 'c'] as $visitor) {
            $this->createRun($visitor, $old);
        }
        $this->withSession(['visitor_id' => 'a'])->getJson('/api/v1/bootstrap')->assertOk();
        $this->assertSame(1, app(Retention::class)->run(true, 1)['counts']['runs']);
        $this->assertDatabaseHas('funnel_runs', ['visitor_id' => 'a']);
        $this->assertDatabaseCount('funnel_runs', 2);
        $this->artisan('privacy:retention', ['--limit' => '0'])->assertFailed();
        $this->artisan('privacy:retention', ['--limit' => '1001'])->assertFailed();
    }

    public function test_release_provisioning_hashes_authorizes_rotates_and_revokes_only_own_sessions(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'brainsprint-secret-');
        chmod($path, 0600);
        try {
            $password = bin2hex(random_bytes(24));
            file_put_contents($path, $password."\n");
            $this->artisan('admin:release', ['email' => 'operator@example.test', '--password-file' => $path])->assertSuccessful();
            $user = AdminUser::where('email', 'operator@example.test')->firstOrFail();
            $this->assertTrue(Hash::check($password, $user->password));
            $this->assertTrue($user->is_admin);
            $this->assertNotSame($password, $user->password);
            DB::table('admin_sessions')->insert([
                ['id' => 'own', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
                ['id' => 'other', 'user_id' => $user->id + 1, 'payload' => '', 'last_activity' => time()],
            ]);
            $user->forceFill(['remember_token' => 'old-token'])->save();
            $rotated = bin2hex(random_bytes(24));
            file_put_contents($path, $rotated);
            $this->artisan('admin:release', ['email' => $user->email, '--password-file' => $path])->assertSuccessful();
            $this->assertTrue(Hash::check($rotated, $user->fresh()->password));
            $this->assertNull($user->fresh()->remember_token);
            $this->assertDatabaseMissing('admin_sessions', ['id' => 'own']);
            $this->assertDatabaseHas('admin_sessions', ['id' => 'other']);
            file_put_contents($path, str_repeat('x', 73));
            clearstatcache();
            $this->artisan('admin:release', ['email' => $user->email, '--password-file' => $path])->assertFailed();
            $this->assertTrue(Hash::check($rotated, $user->fresh()->password));
            chmod($path, 0644);
            clearstatcache();
            $this->artisan('admin:release', ['email' => $user->email, '--password-file' => $path])->assertFailed();
            chmod($path, 0600);
            file_put_contents($path, 'short');
            clearstatcache();
            $this->artisan('admin:release', ['email' => $user->email, '--password-file' => $path])->assertFailed();
            $this->assertTrue(Hash::check($rotated, $user->fresh()->password));
        } finally {
            unlink($path);
        }
    }

    public function test_production_proxy_secret_cannot_be_supplied_by_browser_identity_headers(): void
    {
        $this->app['env'] = 'production';
        config(['release.proxy_secret' => str_repeat('s', 40)]);
        $this->getJson('/api/v1/bootstrap', ['X-Forwarded-For' => '1.2.3.4', 'X-Visitor-Id' => 'admin'])->assertForbidden();
        $this->assertDatabaseCount('sessions', 0);
        $this->getJson('/api/v1/bootstrap', ['X-Consumer-Proxy' => str_repeat('s', 40)])->assertOk();
        $this->artisan('admin:local', ['email' => 'blocked@example.test'])->assertFailed();
    }
}
