<?php

namespace Tests\Feature\Monitors;

use Tests\TestCase;

class MonitorConfigTest extends TestCase
{
    public function test_public_config_exposes_monitor_limits(): void
    {
        config([
            'pulsecheck.allowed_intervals' => [1, 5, 10],
            'pulsecheck.default_interval' => 5,
            'pulsecheck.timeout_min' => 1,
            'pulsecheck.timeout_max' => 30,
            'pulsecheck.timeout_default' => 10,
            'pulsecheck.max_monitors_per_user' => 20,
        ]);

        $this->fromSpa()->getJson('/api/config')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'registration_enabled' => true,
                    'monitors' => [
                        'allowed_intervals' => [1, 5, 10],
                        'default_interval' => 5,
                        'timeout_min' => 1,
                        'timeout_max' => 30,
                        'timeout_default' => 10,
                        'max_per_user' => 20,
                    ],
                ],
            ]);
    }
}
