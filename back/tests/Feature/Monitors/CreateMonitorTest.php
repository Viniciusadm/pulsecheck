<?php

namespace Tests\Feature\Monitors;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class CreateMonitorTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns([
            'example.com' => ['93.184.215.14'],
            'internal.example.com' => ['10.0.0.5'],
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Example',
            'url' => 'https://example.com/health',
            'interval_minutes' => 1,
            'timeout_seconds' => 15,
        ], $overrides);
    }

    public function test_user_can_create_a_monitor_scheduled_for_an_immediate_first_check(): void
    {
        $this->freezeSecond();
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->fromSpa()->postJson('/api/monitors', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Example')
            ->assertJsonPath('data.url', 'https://example.com/health')
            ->assertJsonPath('data.interval_minutes', 1)
            ->assertJsonPath('data.timeout_seconds', 15)
            ->assertJsonPath('data.status', MonitorStatus::Pending->value)
            ->assertJsonPath('data.is_paused', false);

        $monitor = Monitor::findOrFail($response->json('data.id'));
        $this->assertTrue($monitor->next_check_at->equalTo(now()));
    }

    public function test_interval_and_timeout_default_to_configured_values(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson('/api/monitors', [
            'name' => 'Example',
            'url' => 'https://example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.interval_minutes', 5)
            ->assertJsonPath('data.timeout_seconds', 10);
    }

    public function test_monitors_are_shared_with_every_user(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson('/api/monitors', $this->validPayload());

        $response->assertCreated();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/monitors/'.$response->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.name', 'Example');
    }

    public function test_server_controlled_fields_in_the_payload_are_ignored(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()->postJson('/api/monitors', $this->validPayload([
            'status' => 'up',
            'is_paused' => true,
            'consecutive_failures' => 9,
            'next_check_at' => now()->addYear()->toIso8601String(),
        ]));

        $monitor = Monitor::findOrFail($response->json('data.id'));
        $this->assertSame(MonitorStatus::Pending, $monitor->status);
        $this->assertFalse($monitor->is_paused);
        $this->assertSame(0, $monitor->consecutive_failures);
        $this->assertTrue($monitor->next_check_at->lessThanOrEqualTo(now()));
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing name' => [['name' => null], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'missing url' => [['url' => null], 'url'],
            'malformed url' => [['url' => 'not a url'], 'url'],
            'ftp url' => [['url' => 'ftp://example.com'], 'url'],
            'private ip url' => [['url' => 'http://192.168.0.1'], 'url'],
            'host resolving to private ip' => [['url' => 'https://internal.example.com'], 'url'],
            'metadata url' => [['url' => 'http://169.254.169.254/latest/meta-data'], 'url'],
            'interval 0' => [['interval_minutes' => 0], 'interval_minutes'],
            'interval 2' => [['interval_minutes' => 2], 'interval_minutes'],
            'interval 60' => [['interval_minutes' => 60], 'interval_minutes'],
            'interval not integer' => [['interval_minutes' => 'five'], 'interval_minutes'],
            'timeout 0' => [['timeout_seconds' => 0], 'timeout_seconds'],
            'timeout 31' => [['timeout_seconds' => 31], 'timeout_seconds'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', $this->validPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('monitors', 0);
    }

    public function test_timeout_must_be_shorter_than_the_interval(): void
    {
        config(['pulsecheck.timeout_max' => 120]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', $this->validPayload(['interval_minutes' => 1, 'timeout_seconds' => 60]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('timeout_seconds');
    }

    public function test_monitor_writes_are_rate_limited_per_user(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->fromSpa();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->postJson('/api/monitors', ['name' => ''])->assertUnprocessable();
        }

        $this->postJson('/api/monitors', $this->validPayload())->assertTooManyRequests();

        $this->actingAs(User::factory()->admin()->create())->postJson('/api/monitors', $this->validPayload())->assertCreated();
    }
}
