<?php

namespace Tests\Feature\Redis;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use App\Security\SafeHttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\FakesDns;
use Tests\TestCase;
use Throwable;

#[Group('redis')]
class RedisQueueIntegrationTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'redis', 'cache.default' => 'redis']);

        try {
            Redis::connection('default')->flushdb();
            Redis::connection('cache')->flushdb();
        } catch (Throwable $exception) {
            if (getenv('CI_REQUIRE_REDIS') === 'true') {
                throw $exception;
            }

            $this->markTestSkipped('Redis is not reachable: '.$exception->getMessage());
        }
    }

    public function test_cache_locks_work_on_redis(): void
    {
        $lock = Cache::lock('pulsecheck-test-lock', 10);

        $this->assertTrue($lock->get());
        $this->assertFalse(Cache::lock('pulsecheck-test-lock', 10)->get());

        $lock->release();
        $this->assertTrue(Cache::lock('pulsecheck-test-lock', 10)->get());
    }

    public function test_unique_check_jobs_are_queued_once_per_monitor(): void
    {
        $monitor = Monitor::factory()->create();

        RunMonitorCheck::dispatch($monitor);
        RunMonitorCheck::dispatch($monitor);
        RunMonitorCheck::dispatch(Monitor::factory()->create());

        $this->assertSame(2, Queue::connection('redis')->size());
    }

    public function test_dispatcher_queues_due_monitors_once(): void
    {
        Monitor::factory()->count(3)->create(['next_check_at' => now()->subMinute()]);
        Monitor::factory()->create(['next_check_at' => now()->addMinutes(5)]);

        $this->artisan('monitors:dispatch-due')->assertSuccessful();
        $this->artisan('monitors:dispatch-due')->assertSuccessful();

        $this->assertSame(3, Queue::connection('redis')->size());
    }

    public function test_worker_processes_a_check_from_the_redis_queue(): void
    {
        $this->fakeDns(['example.com' => ['93.184.215.14']]);
        $this->app->instance(SafeHttpClient::class, $this->app->make(SafeHttpClient::class, ['handler' => new MockHandler([new Response(200)])]));
        $monitor = Monitor::factory()->create(['url' => 'https://example.com']);

        RunMonitorCheck::dispatch($monitor);
        $this->artisan('queue:work', ['connection' => 'redis', '--once' => true, '--stop-when-empty' => true])->assertSuccessful();

        $this->assertSame(0, Queue::connection('redis')->size());
        $this->assertSame(200, $monitor->checkResults()->sole()->status_code);
        $this->assertTrue(Cache::lock('laravel_unique_job:'.RunMonitorCheck::class.':'.$monitor->id, 10)->get());
    }
}
