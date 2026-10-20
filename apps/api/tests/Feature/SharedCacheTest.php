<?php

namespace Tests\Feature;

use App\Lifecycle\Retention;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedCacheTest extends TestCase
{
    // PostgreSQL lock contention deliberately catches a uniqueness violation;
    // exercise autocommit connections, as session locking runs before transactions.
    use DatabaseMigrations;

    private function cacheInstance(): Repository
    {
        return new Repository(new DatabaseStore(DB::connection(), 'cache', 'shared-test:', 'cache_locks'));
    }

    public function test_independent_instances_share_lock_ownership(): void
    {
        $first = $this->cacheInstance()->lock('session', 30, 'first-instance');
        $second = $this->cacheInstance()->lock('session', 30, 'second-instance');
        $this->assertTrue($first->get());
        $this->assertFalse($second->get());
        $this->assertFalse($second->release());
        $this->assertTrue($first->release());
        $this->assertTrue($second->get());
        $second->release();
    }

    public function test_independent_instances_share_the_rate_budget(): void
    {
        $first = new RateLimiter($this->cacheInstance());
        $second = new RateLimiter($this->cacheInstance());
        $first->hit('visitor', 60);
        $second->hit('visitor', 60);
        $this->assertSame(2, $first->attempts('visitor'));
        $this->assertTrue($second->tooManyAttempts('visitor', 2));
        $this->assertFalse($second->tooManyAttempts('other-visitor', 2));
    }

    public function test_retention_previews_and_bounds_only_expired_shared_cache(): void
    {
        foreach (['cache', 'cache_locks'] as $table) {
            foreach (['old-a' => time() - 60, 'old-b' => time() - 30, 'live' => time() + 600, 'indefinite' => 0] as $key => $expiration) {
                DB::table($table)->insert(['key' => $key, $table === 'cache' ? 'value' : 'owner' => 'fixture', 'expiration' => $expiration]);
            }
        }
        $retention = app(Retention::class);
        $this->assertSame(1, $retention->run(false, 1)['counts']['cache']);
        $this->assertDatabaseHas('cache', ['key' => 'old-a']);
        $retention->run(true, 1);
        foreach (['cache', 'cache_locks'] as $table) {
            $this->assertDatabaseMissing($table, ['key' => 'old-a']);
            foreach (['old-b', 'live', 'indefinite'] as $key) {
                $this->assertDatabaseHas($table, ['key' => $key]);
            }
        }
    }
}
