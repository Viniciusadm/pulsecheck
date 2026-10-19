<?php

namespace Tests\Feature\Monitors;

use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesDns;
use Tests\TestCase;

class MonitorRequestOptionsTest extends TestCase
{
    use FakesDns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDns(['example.com' => ['93.184.215.14']]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'API',
            'url' => 'https://example.com/health',
            'interval_minutes' => 1,
            'timeout_seconds' => 10,
        ], $overrides);
    }

    public function test_monitor_is_created_with_request_options_and_secrets_are_never_returned(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', $this->payload([
                'latency_threshold_ms' => 2000,
                'options' => ['method' => 'POST', 'expected_status_codes' => ['200-299', '304'], 'follow_redirects' => false, 'body_content_type' => 'application/json'],
                'secrets' => ['headers' => ['X-Api-Key' => 'abc'], 'body' => '{}', 'auth' => ['type' => 'bearer', 'token' => 'tok']],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.type', 'http')
            ->assertJsonPath('data.options.method', 'POST')
            ->assertJsonPath('data.options.expected_status_codes', ['200-299', '304'])
            ->assertJsonPath('data.latency_threshold_ms', 2000)
            ->assertJsonPath('data.auth_type', 'bearer')
            ->assertJsonPath('data.secrets_set', ['headers', 'body', 'auth']);

        $this->assertStringNotContainsString('tok', json_encode($response->json('data.secrets_set')));
        $this->assertArrayNotHasKey('secrets', $response->json('data'));
        $this->assertStringNotContainsString('"abc"', $response->getContent());

        $monitor = Monitor::findOrFail($response->json('data.id'));
        $this->assertSame('abc', $monitor->secret('headers')['X-Api-Key']);
        $this->assertStringNotContainsString('abc', (string) $monitor->getRawOriginal('secrets'));
    }

    public function test_defaults_reproduce_a_plain_get(): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.options.method', 'GET')
            ->assertJsonPath('data.options.follow_redirects', true)
            ->assertJsonPath('data.options.expected_status_codes', ['200-399'])
            ->assertJsonPath('data.secrets_set', [])
            ->assertJsonPath('data.latency_threshold_ms', null);
    }

    public function test_saved_secrets_can_be_cleared_explicitly(): void
    {
        $admin = User::factory()->admin()->create();
        $id = $this->actingAs($admin)->fromSpa()
            ->postJson('/api/monitors', $this->payload([
                'options' => ['method' => 'POST'],
                'secrets' => ['headers' => ['X-Api-Key' => 'abc'], 'body' => '{}'],
            ]))
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($admin)->fromSpa()
            ->patchJson("/api/monitors/{$id}", ['clear_secrets' => ['body']])
            ->assertOk()
            ->assertJsonPath('data.secrets_set', ['headers']);
        $this->assertNull(Monitor::findOrFail($id)->secret('body'));

        $this->actingAs($admin)->fromSpa()
            ->patchJson("/api/monitors/{$id}", ['clear_secrets' => ['password']])
            ->assertJsonValidationErrors('clear_secrets.0');
    }

    public static function invalidPayloads(): array
    {
        return [
            'unknown method' => [['options' => ['method' => 'TRACE']], 'options.method'],
            'bad status range' => [['options' => ['expected_status_codes' => ['299-200']]], 'options.expected_status_codes.0'],
            'status out of range' => [['options' => ['expected_status_codes' => ['700']]], 'options.expected_status_codes.0'],
            'unknown option' => [['options' => ['verbose' => true]], 'options'],
            'reserved header' => [['secrets' => ['headers' => ['Host' => 'evil']]], 'secrets.headers'],
            'header with line break' => [['secrets' => ['headers' => ['X-A' => "a\r\nB: c"]]], 'secrets.headers.X-A'],
            'body with get' => [['secrets' => ['body' => '{}']], 'secrets.body'],
            'basic without username' => [['secrets' => ['auth' => ['type' => 'basic']]], 'secrets.auth.username'],
            'bearer without token' => [['secrets' => ['auth' => ['type' => 'bearer']]], 'secrets.auth.token'],
            'authorization header and auth' => [['secrets' => ['headers' => ['Authorization' => 'x'], 'auth' => ['type' => 'bearer', 'token' => 't']]], 'secrets.headers'],
            'threshold over timeout' => [['latency_threshold_ms' => 10000], 'latency_threshold_ms'],
            'unknown type' => [['type' => 'ftp'], 'type'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_options_are_rejected(array $overrides, string $field): void
    {
        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->postJson('/api/monitors', $this->payload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_update_keeps_saved_secrets_sent_as_null_and_cannot_change_the_type(): void
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com',
            'secrets' => ['headers' => ['X-Api-Key' => 'abc'], 'auth' => ['type' => 'bearer', 'token' => 'tok']],
        ]);
        $this->actingAs(User::factory()->admin()->create())->fromSpa();

        $this->patchJson("/api/monitors/{$monitor->id}", ['type' => 'http'])->assertJsonValidationErrors('type');

        $this->patchJson("/api/monitors/{$monitor->id}", ['secrets' => ['headers' => null, 'auth' => ['type' => 'basic', 'username' => 'u', 'password' => 'p']]])
            ->assertOk()
            ->assertJsonPath('data.auth_type', 'basic');

        $monitor->refresh();
        $this->assertSame(['X-Api-Key' => 'abc'], $monitor->secret('headers'));
        $this->assertSame('u', $monitor->secret('auth')['username']);
    }

    public function test_update_rejects_an_authorization_header_when_saved_auth_exists(): void
    {
        $monitor = Monitor::factory()->create(['url' => 'https://example.com', 'secrets' => ['auth' => ['type' => 'bearer', 'token' => 'tok']]]);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['secrets' => ['headers' => ['Authorization' => 'x']]])
            ->assertJsonValidationErrors('secrets.headers');
    }

    public function test_changing_request_options_resets_the_check_state(): void
    {
        $monitor = Monitor::factory()->down()->create(['url' => 'https://example.com']);
        $incident = Incident::factory()->for($monitor)->open()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['options' => ['method' => 'HEAD']])
            ->assertOk();

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Pending, $monitor->status);
        $this->assertSame(2, $monitor->config_revision);
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_changing_only_the_latency_threshold_keeps_the_state(): void
    {
        $monitor = Monitor::factory()->down()->create(['url' => 'https://example.com']);

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/monitors/{$monitor->id}", ['latency_threshold_ms' => 500])
            ->assertOk();

        $monitor->refresh();
        $this->assertSame(MonitorStatus::Down, $monitor->status);
        $this->assertSame(1, $monitor->config_revision);
    }

    public function test_degraded_filter_lists_degraded_monitors(): void
    {
        $degraded = Monitor::factory()->create(['status' => MonitorStatus::Degraded]);
        Monitor::factory()->up()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson('/api/monitors?status=degraded')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $degraded->id);
    }
}
