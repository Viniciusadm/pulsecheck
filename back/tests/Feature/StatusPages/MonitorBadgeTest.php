<?php

namespace Tests\Feature\StatusPages;

use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class MonitorBadgeTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    public function test_editors_enable_rotate_and_disable_the_badge(): void
    {
        $monitor = Monitor::factory()->up()->create();
        $admin = User::factory()->admin()->create();

        $firstUrl = $this->actingAs($admin)->fromSpa()->postJson("/api/monitors/{$monitor->id}/badge")->assertOk()->json('data.badge_url');
        $firstToken = $monitor->fresh()->badge_token;
        $this->assertSame(url("/api/public/badges/{$firstToken}"), $firstUrl);

        $this->actingAs($admin)->fromSpa()->postJson("/api/monitors/{$monitor->id}/badge")->assertOk();
        $this->get("/api/public/badges/{$firstToken}/status.svg")->assertNotFound();

        $this->actingAs($admin)->fromSpa()->deleteJson("/api/monitors/{$monitor->id}/badge")->assertOk()->assertJsonPath('data.badge_url', null);
        $this->assertNull($monitor->fresh()->badge_token);
    }

    public function test_members_cannot_manage_badges_or_see_the_url(): void
    {
        $team = Team::factory()->create();
        $monitor = Monitor::factory()->create(['badge_token' => str_repeat('a', 40)]);
        $monitor->teams()->attach($team);
        $member = User::factory()->create();
        $member->teams()->attach($team);

        $this->actingAs($member)->fromSpa()->postJson("/api/monitors/{$monitor->id}/badge")->assertForbidden();
        $this->actingAs($member)->fromSpa()->getJson("/api/monitors/{$monitor->id}")->assertJsonMissingPath('data.badge_url');
        $this->assertArrayNotHasKey('badge_token', $monitor->toArray());
    }

    public function test_status_badge_is_an_escaped_svg(): void
    {
        Monitor::factory()->down()->create(['name' => '<script>', 'badge_token' => str_repeat('b', 40)]);

        $response = $this->get('/api/public/badges/'.str_repeat('b', 40).'/status.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');

        $this->assertStringContainsString('>down</text>', $response->getContent());
        $this->assertStringContainsString('#dc2626', $response->getContent());
        $this->assertStringNotContainsString('<script>', $response->getContent());
    }

    public function test_uptime_badge_shows_the_percentage_for_the_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
        $monitor = Monitor::factory()->up()->create(['badge_token' => str_repeat('c', 40)]);
        $this->checkAt($monitor, now()->subHour());
        $this->checkAt($monitor, now()->subMinutes(30));
        $this->checkAt($monitor, now()->subMinutes(20));
        $this->checkAt($monitor, now()->subMinutes(10), isSuccess: false, latencyMs: null);

        $content = $this->get('/api/public/badges/'.str_repeat('c', 40).'/uptime.svg?days=7')->assertOk()->getContent();

        $this->assertStringContainsString('uptime 7 d', $content);
        $this->assertStringContainsString('>75%</text>', $content);

        $this->get('/api/public/badges/'.str_repeat('c', 40).'/uptime.svg?days=5')->assertUnprocessable();
    }

    public function test_unknown_badges_render_a_not_found_svg(): void
    {
        $this->get('/api/public/badges/'.str_repeat('z', 40).'/status.svg')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
    }
}
