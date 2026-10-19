<?php

namespace Tests\Feature\StatusPages;

use App\Enums\IncidentKind;
use App\Enums\MonitorStatus;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class PublicStatusPageTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    }

    private function page(array $monitors, array $attributes = []): StatusPage
    {
        $page = StatusPage::factory()->published()->create(['slug' => 'acme', 'name' => 'Acme status', 'uptime_days' => 30, ...$attributes]);

        foreach (array_values($monitors) as $position => $monitor) {
            $page->monitors()->attach($monitor, ['sort_order' => $position]);
        }

        return $page;
    }

    public function test_published_page_is_public_and_exposes_only_safe_fields(): void
    {
        $monitor = Monitor::factory()->up()->create(['name' => 'Internal API name', 'url' => 'https://secret-host.example.com/health?key=abc']);
        $this->checkAt($monitor, now()->subHour());
        $this->checkAt($monitor, now()->subMinutes(30), isSuccess: false, latencyMs: null);
        Incident::factory()->for($monitor)->resolved()->create(['started_at' => now()->subDays(2), 'cause' => 'timeout: secret detail']);
        $page = $this->page([$monitor]);
        $page->monitors()->updateExistingPivot($monitor->id, ['display_name' => 'API']);

        $response = $this->getJson('/api/public/status-pages/acme')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, public')
            ->assertJsonPath('data.name', 'Acme status')
            ->assertJsonPath('data.overall_status', 'operational')
            ->assertJsonPath('data.monitors.0.name', 'API')
            ->assertJsonPath('data.monitors.0.key', 1)
            ->assertJsonPath('data.monitors.0.uptime_percentage', 50)
            ->assertJsonPath('data.monitors.0.latency_ms', null)
            ->assertJsonCount(30, 'data.monitors.0.days')
            ->assertJsonCount(1, 'data.incidents')
            ->assertJsonPath('data.incidents.0.monitor_key', 1);

        $content = $response->getContent();
        $keys = array_keys(Arr::dot($response->json('data')));

        foreach (['secret-host', 'secret detail', 'Internal API name'] as $secret) {
            $this->assertStringNotContainsString($secret, $content);
        }

        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/(^|\.)(id|url|cause|error_message|status_code|monitor_id)$/', $key);
        }
    }

    public function test_drafts_and_unknown_slugs_are_not_found(): void
    {
        StatusPage::factory()->create(['slug' => 'draft']);

        $this->getJson('/api/public/status-pages/draft')->assertNotFound();
        $this->getJson('/api/public/status-pages/missing')->assertNotFound();
    }

    public function test_latency_and_incidents_follow_the_page_settings(): void
    {
        $monitor = Monitor::factory()->up()->create();
        $this->checkAt($monitor, now()->subHour(), latencyMs: 240);
        Incident::factory()->for($monitor)->resolved()->create(['started_at' => now()->subDay()]);
        $this->page([$monitor], ['show_latency' => true, 'show_incidents' => false]);

        $this->getJson('/api/public/status-pages/acme')
            ->assertJsonPath('data.monitors.0.latency_ms', 240)
            ->assertJsonPath('data.incidents', []);
    }

    public function test_uptime_and_latency_of_all_monitors_are_loaded_in_batch(): void
    {
        $rolledUp = Monitor::factory()->up()->create();
        $this->checkAt($rolledUp, CarbonImmutable::parse('2026-10-15 10:00:00'), isSuccess: false, latencyMs: null);
        $this->checkAt($rolledUp, CarbonImmutable::parse('2026-10-16 10:00:00'));
        $this->artisan('uptime:rollup')->assertSuccessful();
        $this->checkAt($rolledUp, now()->subHour(), latencyMs: 300);
        $rawOnly = Monitor::factory()->up()->create();
        $this->checkAt($rawOnly, CarbonImmutable::parse('2026-10-16 10:00:00'), latencyMs: 50);
        $silent = Monitor::factory()->up()->create();
        $this->page([$rolledUp, $rawOnly, $silent], ['show_latency' => true]);
        Monitor::factory()->count(5)->create()->each(fn (Monitor $monitor) => StatusPage::query()->sole()->monitors()->attach($monitor, ['sort_order' => 10]));
        DB::enableQueryLog();

        $monitors = collect($this->getJson('/api/public/status-pages/acme')->assertOk()->json('data.monitors'))->keyBy('key');

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
        $this->assertSame(66.67, $monitors[1]['uptime_percentage']);
        $this->assertSame(300, $monitors[1]['latency_ms']);
        $this->assertSame(['date' => '2026-10-15', 'uptime_percentage' => 0], $monitors[1]['days'][27]);
        $this->assertSame(100, $monitors[2]['uptime_percentage']);
        $this->assertNull($monitors[2]['latency_ms']);
        $this->assertNull($monitors[3]['uptime_percentage']);
    }

    public static function overallStatuses(): array
    {
        return [
            'all up' => [[MonitorStatus::Up, MonitorStatus::Up], 'operational'],
            'one degraded' => [[MonitorStatus::Up, MonitorStatus::Degraded], 'degraded'],
            'one down' => [[MonitorStatus::Up, MonitorStatus::Down], 'partial_outage'],
            'all down' => [[MonitorStatus::Down, MonitorStatus::Down], 'major_outage'],
            'only pending' => [[MonitorStatus::Pending], 'unknown'],
        ];
    }

    #[DataProvider('overallStatuses')]
    public function test_overall_status(array $statuses, string $expected): void
    {
        $this->page(array_map(fn (MonitorStatus $status) => Monitor::factory()->create(['status' => $status]), $statuses));

        $this->getJson('/api/public/status-pages/acme')->assertJsonPath('data.overall_status', $expected);
    }

    public function test_maintenance_and_paused_monitors_have_their_own_status(): void
    {
        $this->page([
            Monitor::factory()->up()->create(['maintenance_ends_at' => now()->addHour()]),
            Monitor::factory()->paused()->create(),
        ]);

        $this->getJson('/api/public/status-pages/acme')
            ->assertJsonPath('data.monitors.0.status', 'maintenance')
            ->assertJsonPath('data.monitors.1.status', 'paused')
            ->assertJsonPath('data.overall_status', 'maintenance');
    }

    public function test_degradation_incidents_are_not_listed(): void
    {
        $monitor = Monitor::factory()->up()->create();
        Incident::factory()->for($monitor)->resolved()->create(['kind' => IncidentKind::Degradation, 'started_at' => now()->subDay()]);
        $this->page([$monitor]);

        $this->getJson('/api/public/status-pages/acme')->assertJsonPath('data.incidents', []);
    }

    public function test_edits_are_visible_right_away_despite_the_cache(): void
    {
        $first = Monitor::factory()->up()->create(['name' => 'First']);
        $second = Monitor::factory()->up()->create(['name' => 'Second']);
        $page = $this->page([$first, $second]);
        $this->getJson('/api/public/status-pages/acme')->assertJsonPath('data.monitors.0.name', 'First');

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->patchJson("/api/status-pages/{$page->id}", ['monitors' => [['monitor_id' => $second->id], ['monitor_id' => $first->id]]])
            ->assertOk();

        $this->getJson('/api/public/status-pages/acme')->assertJsonPath('data.monitors.0.name', 'Second');
    }

    public function test_public_page_is_rate_limited(): void
    {
        $this->page([]);

        foreach (range(1, 60) as $attempt) {
            $this->getJson('/api/public/status-pages/acme')->assertOk();
        }

        $this->getJson('/api/public/status-pages/acme')->assertTooManyRequests();
    }
}
