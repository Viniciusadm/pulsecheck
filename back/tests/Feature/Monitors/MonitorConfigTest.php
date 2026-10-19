<?php

namespace Tests\Feature\Monitors;

use App\Models\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_exposes_monitor_limits(): void
    {
        config([
            'pulsecheck.allowed_intervals' => [1, 5, 10],
            'pulsecheck.heartbeat_allowed_intervals' => [5, 60],
            'pulsecheck.default_interval' => 5,
            'pulsecheck.timeout_min' => 1,
            'pulsecheck.timeout_max' => 30,
            'pulsecheck.timeout_default' => 10,
            'pulsecheck.setup_token' => null,
            'app.timezone' => 'UTC',
        ]);

        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'setup_required' => true,
                    'setup_token_required' => false,
                    'instance' => [
                        'name' => 'PulseCheck',
                        'timezone' => 'UTC',
                        'locale' => 'en',
                    ],
                    'available_locales' => ['en', 'pt-BR'],
                    'monitors' => [
                        'allowed_intervals' => [1, 5, 10],
                        'heartbeat_intervals' => [5, 60],
                        'keyword_max_body_bytes' => 1048576,
                        'reminder_intervals' => [15, 30, 60, 120, 240],
                        'default_interval' => 5,
                        'timeout_min' => 1,
                        'timeout_max' => 30,
                        'timeout_default' => 10,
                    ],
                    'realtime' => null,
                ],
            ]);
    }

    public function test_public_config_exposes_the_instance_after_setup(): void
    {
        InstanceSettings::factory()->create(['name' => 'Acme Ops', 'timezone' => 'America/Sao_Paulo', 'locale' => 'en']);

        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.setup_required', false)
            ->assertJsonPath('data.instance', ['name' => 'Acme Ops', 'timezone' => 'America/Sao_Paulo', 'locale' => 'en']);
    }

    public function test_public_config_exposes_the_realtime_key_when_reverb_is_enabled(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-key',
        ]);

        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.realtime', ['key' => 'public-key']);
    }
}
