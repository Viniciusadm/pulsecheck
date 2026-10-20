<?php

namespace Tests\Feature\Monitors;

use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Actions\RecordCheckResult;
use App\Events\MonitorChecked;
use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use App\Models\User;
use App\Services\Checkers\HeartbeatChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class MonitorTypesApiTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeSecond();
        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'db.example.com' => ['93.184.215.40'],
            'internal.example.com' => ['10.0.0.5'],
        ]);
    }

    private function create(array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', ['name' => 'Target', 'interval_minutes' => 5, 'timeout_seconds' => 10, ...$payload]);
    }

    public function test_each_type_is_created_with_its_options_and_target(): void
    {
        $this->create(['type' => 'keyword', 'url' => 'https://example.com', 'options' => ['keyword' => 'ok']])
            ->assertCreated()
            ->assertJsonPath('data.options.keyword', 'ok')
            ->assertJsonPath('data.options.keyword_mode', 'contains')
            ->assertJsonPath('data.display_target', 'example.com');

        $this->create(['type' => 'tcp', 'options' => ['host' => 'db.example.com', 'port' => 5432]])
            ->assertCreated()
            ->assertJsonPath('data.url', null)
            ->assertJsonPath('data.display_target', 'db.example.com:5432');

        $this->create(['type' => 'ssl', 'url' => 'https://example.com', 'options' => ['warning_days' => 30]])
            ->assertCreated()
            ->assertJsonPath('data.options.warning_days', 30);
    }

    public function test_keyword_and_tcp_monitors_are_updated_without_resending_their_options(): void
    {
        $keywordId = $this->create(['type' => 'keyword', 'url' => 'https://example.com', 'options' => ['keyword' => 'ok']])->assertCreated()->json('data.id');
        $tcpId = $this->create(['type' => 'tcp', 'options' => ['host' => 'db.example.com', 'port' => 5432]])->assertCreated()->json('data.id');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->fromSpa()->patchJson("/api/monitors/{$keywordId}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.options.keyword', 'ok');

        $this->actingAs($admin)->fromSpa()->patchJson("/api/monitors/{$tcpId}", ['interval_minutes' => 10])
            ->assertOk()
            ->assertJsonPath('data.display_target', 'db.example.com:5432');

        $this->actingAs($admin)->fromSpa()->patchJson("/api/monitors/{$tcpId}", ['options' => ['host' => 'db.example.com']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('options.port');
    }

    public static function invalidPayloads(): array
    {
        return [
            'keyword without keyword' => [['type' => 'keyword', 'url' => 'https://example.com'], 'options'],
            'keyword with head' => [['type' => 'keyword', 'url' => 'https://example.com', 'options' => ['keyword' => 'ok', 'method' => 'HEAD']], 'options.method'],
            'tcp with url' => [['type' => 'tcp', 'url' => 'https://example.com', 'options' => ['host' => 'db.example.com', 'port' => 1]], 'url'],
            'tcp without port' => [['type' => 'tcp', 'options' => ['host' => 'db.example.com']], 'options.port'],
            'tcp to private host' => [['type' => 'tcp', 'options' => ['host' => 'internal.example.com', 'port' => 22]], 'options.host'],
            'tcp host with path' => [['type' => 'tcp', 'options' => ['host' => 'db.example.com/x', 'port' => 22]], 'options.host'],
            'ssl over http' => [['type' => 'ssl', 'url' => 'http://example.com'], 'url'],
            'heartbeat with url' => [['type' => 'heartbeat', 'url' => 'https://example.com'], 'url'],
            'heartbeat grace too long' => [['type' => 'heartbeat', 'options' => ['grace_seconds' => 4000]], 'options.grace_seconds'],
            'http interval of 60' => [['url' => 'https://example.com', 'interval_minutes' => 60], 'interval_minutes'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_type_specific_validation(array $payload, string $field): void
    {
        $this->create($payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_heartbeat_monitor_waits_for_the_first_window_and_exposes_its_url_to_editors(): void
    {
        $response = $this->create(['type' => 'heartbeat', 'interval_minutes' => 60, 'options' => ['grace_seconds' => 120]])->assertCreated();

        $monitor = Monitor::findOrFail($response->json('data.id'));
        $this->assertSame(MonitorType::Heartbeat, $monitor->type);
        $this->assertSame(40, strlen($monitor->heartbeat_token));
        $this->assertTrue($monitor->next_check_at->equalTo(now()->addMinutes(60)->addSeconds(120)));
        $response->assertJsonPath('data.heartbeat_url', url("/api/heartbeat/{$monitor->heartbeat_token}"));

        $this->actingAs(User::factory()->viewingAll()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.heartbeat_url');

        $this->assertArrayNotHasKey('heartbeat_url', (new MonitorChecked($monitor))->broadcastWith()['monitor']);
    }

    public function test_ping_marks_the_monitor_up_and_pushes_the_deadline(): void
    {
        $monitor = Monitor::factory()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('a', 40), 'interval_minutes' => 10]);

        $this->postJson('/api/heartbeat/'.str_repeat('a', 40))->assertOk()->assertExactJson(['data' => ['accepted' => true]]);

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Up, $monitor->status);
        $this->assertTrue($monitor->last_heartbeat_at->equalTo(now()));
        $this->assertTrue($monitor->next_check_at->equalTo(now()->addMinutes(10)->addSeconds(60)));
        $this->assertSame(1, $monitor->checkResults()->count());

        $this->get('/api/heartbeat/'.str_repeat('a', 40))->assertOk();
        $this->assertSame(2, $monitor->checkResults()->count());
    }

    public function test_ping_for_a_paused_or_unknown_monitor_records_nothing(): void
    {
        Monitor::factory()->paused()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('b', 40)]);

        $this->postJson('/api/heartbeat/'.str_repeat('b', 40))->assertExactJson(['data' => ['accepted' => false]]);
        $this->postJson('/api/heartbeat/'.str_repeat('c', 40))->assertNotFound();
        $this->postJson('/api/heartbeat/short')->assertNotFound();
    }

    public function test_pings_are_rate_limited_per_token(): void
    {
        Monitor::factory()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('d', 40)]);

        foreach (range(1, 30) as $attempt) {
            $this->postJson('/api/heartbeat/'.str_repeat('d', 40))->assertOk();
        }

        $this->postJson('/api/heartbeat/'.str_repeat('d', 40))->assertTooManyRequests();
    }

    public function test_one_missed_window_takes_a_heartbeat_down_and_one_ping_recovers_it(): void
    {
        $monitor = Monitor::factory()->up()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('e', 40), 'interval_minutes' => 5, 'last_heartbeat_at' => now()->subMinutes(10)]);

        $this->app->call([new RunMonitorCheck($monitor), 'handle']);

        $this->assertSame(MonitorStatus::Down, $monitor->fresh()->status);
        $this->assertSame(1, $monitor->incidents()->whereNull('resolved_at')->count());

        $this->postJson('/api/heartbeat/'.str_repeat('e', 40))->assertOk();

        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        $this->assertSame(0, $monitor->incidents()->whereNull('resolved_at')->count());
    }

    public function test_a_missed_window_is_discarded_when_a_ping_arrived_while_checking(): void
    {
        $monitor = Monitor::factory()->up()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('g', 40), 'interval_minutes' => 5, 'last_heartbeat_at' => now()->subMinutes(10)]);
        $outcome = app(HeartbeatChecker::class)->check($monitor);
        $this->postJson('/api/heartbeat/'.str_repeat('g', 40))->assertOk();

        $this->assertNull(app(RecordCheckResult::class)->handle($monitor, $outcome));

        $this->assertSame(MonitorStatus::Up, $monitor->fresh()->status);
        $this->assertSame(0, $monitor->incidents()->count());
        $this->assertSame(0, $monitor->checkResults()->where('is_success', false)->count());
    }

    public function test_token_rotation_invalidates_the_old_url(): void
    {
        $monitor = Monitor::factory()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('f', 40)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs(User::factory()->viewingAll()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/heartbeat-token")->assertForbidden();

        $newUrl = $this->actingAs($admin)->fromSpa()->postJson("/api/monitors/{$monitor->id}/heartbeat-token")->assertOk()->json('data.heartbeat_url');

        $this->assertStringNotContainsString(str_repeat('f', 40), $newUrl);
        $this->postJson('/api/heartbeat/'.str_repeat('f', 40))->assertNotFound();
    }

    public function test_resuming_a_heartbeat_monitor_waits_a_full_window(): void
    {
        $monitor = Monitor::factory()->paused()->create(['type' => MonitorType::Heartbeat, 'url' => null, 'heartbeat_token' => str_repeat('g', 40), 'interval_minutes' => 15]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson("/api/monitors/{$monitor->id}/resume")->assertOk();

        $this->assertTrue($monitor->fresh()->next_check_at->equalTo(now()->addMinutes(15)->addSeconds(60)));
    }
}
