<?php

namespace Tests\Feature\Dashboard;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use App\Support\DashboardLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_default_layout_until_the_user_saves_one(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->getJson('/api/me/dashboard')
            ->assertOk()
            ->assertJsonPath('data.period', '24h')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonCount(count(DashboardLayout::defaultWidgets()), 'data.widgets')
            ->assertJsonPath('data.widgets.0.type', 'status_summary');
    }

    public function test_saves_the_layout_per_user(): void
    {
        $user = User::factory()->admin()->create();
        $other = User::factory()->create();
        $monitor = Monitor::factory()->create();

        $this->actingAs($user)->fromSpa()
            ->putJson('/api/me/dashboard', [
                'period' => '7d',
                'widgets' => [
                    ['id' => 'a', 'type' => 'uptime_trend', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                    ['id' => 'b', 'type' => 'monitor_latency', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4, 'options' => ['monitor_id' => $monitor->id]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->getJson('/api/me/dashboard')
            ->assertExactJson(['data' => [
                'period' => '7d',
                'is_default' => false,
                'widgets' => [
                    ['id' => 'a', 'type' => 'uptime_trend', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'options' => []],
                    ['id' => 'b', 'type' => 'monitor_latency', 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4, 'options' => ['monitor_id' => $monitor->id]],
                ],
            ]]);

        $this->assertNull($other->fresh()->dashboard_layout);
    }

    public function test_updating_only_the_period_keeps_the_default_layout(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->putJson('/api/me/dashboard', ['period' => '30d'])
            ->assertOk()
            ->assertJsonPath('data.period', '30d')
            ->assertJsonPath('data.is_default', true);
    }

    public function test_an_empty_dashboard_can_be_saved(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa()
            ->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => []])
            ->assertOk()
            ->assertJsonPath('data.widgets', [])
            ->assertJsonPath('data.is_default', false);
    }

    public function test_restoring_brings_back_the_default_layout_and_keeps_the_period(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['dashboard_layout' => [], 'dashboard_period' => '7d'])->save();

        $this->actingAs($user)->fromSpa()
            ->deleteJson('/api/me/dashboard')
            ->assertOk()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.period', '7d');
    }

    public function test_rejects_invalid_widgets(): void
    {
        $this->actingAs(User::factory()->create())->fromSpa();

        $widget = ['id' => 'a', 'type' => 'uptime_trend', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4];

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'type' => 'pie']]])
            ->assertJsonValidationErrors('widgets.0.type');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'x' => 8]]])
            ->assertJsonValidationErrors('widgets.0.w');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'w' => 2]]])
            ->assertJsonValidationErrors('widgets.0.w');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [$widget, $widget]])
            ->assertJsonValidationErrors('widgets.0.id');

        $this->putJson('/api/me/dashboard', ['period' => '1y', 'widgets' => [$widget]])
            ->assertJsonValidationErrors('period');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'extra' => true]]])
            ->assertJsonValidationErrors('widgets.0');
    }

    public function test_monitor_widgets_require_a_monitor_the_user_can_see(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);
        $visible = Monitor::factory()->create();
        $visible->teams()->attach($team);
        $hidden = Monitor::factory()->create();
        $hidden->teams()->attach(Team::factory()->create());

        $this->actingAs($user)->fromSpa();

        $widget = ['id' => 'a', 'type' => 'monitor_latency', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4];

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [$widget]])
            ->assertJsonValidationErrors('widgets.0.options.monitor_id');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'options' => ['monitor_id' => $hidden->id]]]])
            ->assertJsonValidationErrors('widgets.0.options.monitor_id');

        $this->putJson('/api/me/dashboard', ['period' => '24h', 'widgets' => [[...$widget, 'options' => ['monitor_id' => $visible->id]]]])
            ->assertOk();
    }
}
