<?php

namespace Tests\Feature;

use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use App\Analytics\SyntheticScenario;
use App\Filament\Pages\Analytics;
use App\Filament\Resources\FunnelRunResource;
use App\Filament\Resources\FunnelRunResource\Pages\ListRecords;
use App\Models\AdminUser;
use App\Models\FunnelRun;
use Carbon\CarbonImmutable as Time;
use Tests\Fixtures\LegacyFunnelSeeder as DatabaseSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDemoAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Time::setTestNow(Time::parse('2026-10-19T12:00:00Z'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function admin(bool $authorized = true): AdminUser
    {
        return AdminUser::create(['name' => 'Test admin', 'email' => Str::uuid().'@example.test', 'password' => 'test-only-password', 'is_admin' => $authorized]);
    }

    private function fixture(): void
    {
        app(SyntheticScenario::class)->seed(Time::now());
    }

    public function test_anonymous_direct_resources_and_details_require_admin_authentication(): void
    {
        $this->fixture();
        foreach (['analytics', 'runs', 'runs/'.DB::table('funnel_runs')->value('id'), 'events', 'configurations', 'visitors'] as $path) {
            $this->get('/admin/'.$path)->assertRedirect('/admin/login');
        }
        $this->get('/admin/register')->assertNotFound();
        $this->get('/admin/password-reset/request')->assertNotFound();
    }

    public function test_nonadmin_cannot_open_panel_resources(): void
    {
        $this->actingAs($this->admin(false), 'admin');
        foreach (['analytics', 'runs', 'events', 'configurations', 'visitors'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }
    }

    public function test_nonadmin_login_is_rejected_even_locally(): void
    {
        $user = $this->admin(false);
        Livewire::test(Login::class)->set('data.email', $user->email)->set('data.password', 'test-only-password')
            ->call('authenticate')->assertHasErrors(['data.email']);
        $this->assertGuest('admin');
    }

    public function test_authorized_resources_are_readonly_and_direct_mutations_are_unavailable(): void
    {
        $this->fixture();
        $user = $this->admin();
        $this->actingAs($user, 'admin');
        $run = FunnelRun::orderByDesc('created_at')->first();
        foreach (['analytics', 'runs', 'runs/'.$run->id, 'events', 'configurations', 'visitors'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }
        foreach (['create', 'update', 'delete', 'replicate', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $ability === 'create' ? FunnelRun::class : $run));
        }
        $this->get('/admin/events/create')->assertNotFound();
        $this->get('/admin/runs/create')->assertNotFound();
        $this->get('/admin/runs/'.$run->id.'/edit')->assertNotFound();
        $this->post('/admin/runs/'.$run->id)->assertStatus(405);
        Livewire::test(ListRecords::class)->assertCanSeeTableRecords([$run])->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist('edit')->assertActionDoesNotExist('delete')->call('mountAction', 'delete', ['record' => $run->id]);
        $this->assertDatabaseHas('funnel_runs', ['id' => $run->id]);
        $this->assertFalse(FunnelRunResource::canEdit($run));
    }

    public function test_consumer_session_cannot_authenticate_admin_even_with_admin_payload(): void
    {
        $user = $this->admin();
        $id = Str::random(40);
        $data = ['login_admin_'.sha1(SessionGuard::class) => $user->id, '_token' => Str::random(40)];
        DB::table('sessions')->insert(['id' => $id, 'payload' => base64_encode((config('session.encrypt') ? Crypt::encrypt(json_encode($data)) : json_encode($data))), 'last_activity' => time()]);
        $this->withCookie('brainsprint_admin_session', $id)->get('/admin/runs')->assertRedirect('/admin/login');
        $this->assertDatabaseMissing('admin_sessions', ['id' => $id, 'user_id' => $user->id]);
    }

    public function test_correct_admin_payload_authenticates_only_from_admin_storage(): void
    {
        $user = $this->admin();
        $id = Str::random(40);
        $data = ['login_admin_'.sha1(SessionGuard::class) => $user->id, '_token' => Str::random(40)];
        DB::table('admin_sessions')->insert(['id' => $id, 'payload' => base64_encode(config('session.encrypt') ? Crypt::encrypt(json_encode($data)) : json_encode($data)), 'last_activity' => time()]);
        $this->withCookie('brainsprint_admin_session', $id)->get('/admin/runs')->assertOk();
    }

    public function test_default_livewire_mutations_and_unused_file_endpoints_are_closed(): void
    {
        $login = $this->get('/admin/login')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^" ]+)"/', $login, $match);
        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $this->assertNotEmpty($snapshot);
        $route = app('router')->getRoutes()->getByName('default-livewire.update')->uri();
        $this->postJson('/'.$route, ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'authenticate', 'params' => []]]]]], ['X-Livewire' => ''])->assertNotFound();
        $this->postJson('/'.str_replace('/update', '/upload-file', $route))->assertNotFound();
        $this->get('/'.str_replace('/update', '/preview-file/anything', $route))->assertNotFound();
        $this->get('/'.str_replace('/update', '/preview-file/anything.js', $route))->assertNotFound();
        $this->assertGuest('admin');
    }

    public function test_admin_cookie_is_scoped_and_uses_separate_storage(): void
    {
        $response = $this->get('/admin/login')->assertOk();
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'brainsprint_admin_session');
        $this->assertNotNull($cookie);
        $this->assertSame('/admin', $cookie->getPath());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame(1, DB::table('admin_sessions')->count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_revoked_admin_cannot_replay_a_signed_resource_snapshot(): void
    {
        $user = $this->admin();
        $id = Str::random(40);
        $data = ['login_admin_'.sha1(SessionGuard::class) => $user->id, '_token' => Str::random(40)];
        DB::table('admin_sessions')->insert(['id' => $id, 'payload' => base64_encode(config('session.encrypt') ? Crypt::encrypt(json_encode($data)) : json_encode($data)), 'last_activity' => time()]);
        $html = $this->withCookie('brainsprint_admin_session', $id)->get('/admin/runs')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^" ]+)"/', $html, $match);
        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $user->update(['is_admin' => false]);
        Auth::forgetGuards();
        $this->withCookie('brainsprint_admin_session', $id)->postJson('/admin/livewire/update', ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => 'mountAction', 'params' => ['delete']]]]]], ['X-Livewire' => ''])->assertForbidden();
    }

    public function test_admin_livewire_requires_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/admin/livewire/update', ['components' => []], ['X-Livewire' => ''])->assertStatus(419);
    }

    public function test_consumer_cookie_and_storage_are_unchanged(): void
    {
        $response = $this->get('/api/v1/bootstrap')->assertOk();
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame(0, DB::table('admin_sessions')->count());
        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertGuest('admin');
    }

    public function test_seed_is_idempotent_and_preserves_existing_records(): void
    {
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => 'private-real', 'funnel_version_id' => DB::table('funnel_versions')->value('id'), 'current_step' => 'welcome', 'is_synthetic' => false,
            'answers' => '{"welcome":{"acknowledged":true}}', 'result' => '{"private":"preserve"}', 'created_at' => Time::now()->subDay(), 'updated_at' => Time::now()->subDay()]);
        $before = DB::table('funnel_runs')->find($id);
        $this->fixture();
        $runs = DB::table('funnel_runs')->orderBy('id')->get();
        $events = DB::table('events')->orderBy('id')->get();
        $versions = DB::table('funnel_versions')->orderBy('id')->get();
        $this->fixture();
        $this->assertEquals($runs, DB::table('funnel_runs')->orderBy('id')->get());
        $this->assertEquals($events, DB::table('events')->orderBy('id')->get());
        $this->assertEquals($versions, DB::table('funnel_versions')->orderBy('id')->get());
        $this->assertEquals($before, DB::table('funnel_runs')->find($id));
        $this->assertSame(18, DB::table('funnel_runs')->where('is_synthetic', true)->count());
        $this->assertSame(24, DB::table('events')->where('name', 'landing_viewed')->count());
        $this->assertSame(0, DB::table('events')->where('is_synthetic', false)->count());
    }

    public function test_demo_seed_never_publishes_a_missing_configuration(): void
    {
        DB::table('funnel_versions')->delete();
        try {
            $this->fixture();
            $this->fail('A missing published version must be rejected.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('php artisan db:seed', $error->getMessage());
        }
        $this->assertDatabaseCount('funnel_versions', 0);
        $this->assertDatabaseCount('funnel_runs', 0);
        $this->assertDatabaseCount('events', 0);
    }

    public function test_public_caller_matches_shared_service_without_real_or_raw_data(): void
    {
        $this->fixture();
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => 'private-real', 'funnel_version_id' => DB::table('funnel_versions')->value('id'), 'current_step' => 'welcome', 'created_at' => Time::now()->subHour(), 'updated_at' => Time::now(), 'is_synthetic' => false]);
        $expected = app(AggregateService::class)->summarize(Time::now()->subDays(7), Time::now(), Time::now(), Dataset::Synthetic);
        $response = $this->getJson('/api/v1/demo-analytics')->assertOk()->assertJsonPath('dataset', 'synthetic')->assertJsonPath('totals.runs', 18);
        foreach (['cohort', 'totals', 'stage_conversions', 'quiz_elapsed_seconds', 'experiment'] as $key) {
            $this->assertEquals($expected[$key], $response->json($key));
        }
        $this->assertSame(['dataset', 'cohort', 'totals', 'stage_conversions', 'quiz_elapsed_seconds', 'experiment', 'v3'], array_keys($response->json()));
        foreach (['visitor_id', 'run_id', 'answers', 'private-real', $id, 'utm', 'admin'] as $private) {
            $response->assertDontSee($private);
        }
        $this->assertSame('insufficient_data', $response->json('experiment.status'));
        $this->assertNull($response->json('experiment.winner'));
        $this->assertSame(2, $response->json('experiment.variants.A.pending_observation'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_empty_public_sample_has_no_invented_rates(): void
    {
        $this->getJson('/api/v1/demo-analytics')->assertOk()->assertJsonPath('totals.runs', 0)
            ->assertJsonPath('stage_conversions.quiz_completed.rate', null)->assertJsonPath('quiz_elapsed_seconds.mean', null);
    }

    public function test_public_query_cannot_switch_dataset_or_bounds(): void
    {
        foreach (['dataset=real', 'dataset=all', 'is_synthetic=0', 'from=1900-01-01', 'run_id=anything', 'sql=select', 'dataset%5B%5D=real'] as $query) {
            $this->getJson('/api/v1/demo-analytics?'.$query)->assertUnprocessable()->assertJsonStructure(['error' => ['code', 'message']]);
        }
        $this->postJson('/api/v1/demo-analytics', ['dataset' => 'real'])->assertStatus(405);
    }

    public function test_dashboard_defaults_and_validates_caller_bounds(): void
    {
        $this->fixture();
        $this->actingAs($this->admin(), 'admin');
        Livewire::test(Analytics::class)->assertSet('dataset', 'real')->assertSee('Real traffic')
            ->set('dataset', 'synthetic')->call('apply')->assertHasNoErrors()->assertSee('Synthetic data')
            ->assertViewHas('metrics', app(AggregateService::class)->summarize(Time::now()->subDays(7), Time::now(), Time::now(), Dataset::Synthetic))
            ->set('dataset', 'all')->call('apply')->assertHasErrors('dataset')
            ->set('dataset', 'real')->set('from', '2025-01-01T00:00')->call('apply')->assertHasErrors('from')
            ->set('to', 'not-a-date')->call('apply')->assertHasErrors('to');
    }

    public function test_admin_utm_is_escaped_in_dashboard_and_record_details(): void
    {
        $id = (string) Str::uuid();
        DB::table('funnel_runs')->insert(['id' => $id, 'visitor_id' => 'utm-text-fixture', 'funnel_version_id' => DB::table('funnel_versions')->value('id'), 'current_step' => 'welcome',
            'is_synthetic' => false, 'attribution' => json_encode(['utm_source' => '<script>demo</script>']), 'created_at' => Time::now()->subHour(), 'updated_at' => Time::now()]);
        $this->actingAs($this->admin(), 'admin');
        foreach (['/admin/analytics', '/admin/runs/'.$id] as $url) {
            $this->get($url)->assertOk()->assertSee('&lt;script&gt;demo&lt;/script&gt;', false)->assertDontSee('<script>demo</script>', false);
        }
    }

    public function test_provisioning_is_local_only_and_seed_dates_are_bounded(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
        $this->artisan('admin:local', ['email' => 'blocked@example.test'])->assertFailed();
        $this->assertDatabaseCount('admin_users', 0);
        $this->artisan('analytics:seed-demo', ['--date' => '2100-01-01'])->assertFailed();
    }
}
