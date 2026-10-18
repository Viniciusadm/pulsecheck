<?php

namespace Tests\Feature\History;

use App\Models\Monitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCheckResults;
use Tests\TestCase;

class CheckHistoryTest extends TestCase
{
    use CreatesCheckResults;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00'));
    }

    public function test_period_includes_from_and_excludes_to(): void
    {
        $monitor = Monitor::factory()->create();
        $beforeFrom = $this->checkAt($monitor, now()->parse('2026-10-17 09:59:59'));
        $atFrom = $this->checkAt($monitor, now()->parse('2026-10-17 10:00:00'));
        $inside = $this->checkAt($monitor, now()->parse('2026-10-17 10:30:00'));
        $atTo = $this->checkAt($monitor, now()->parse('2026-10-17 11:00:00'));

        $ids = $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/checks?from=2026-10-17T10:00:00Z&to=2026-10-17T11:00:00Z")
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$inside->id, $atFrom->id], $ids);
        $this->assertNotContains($beforeFrom->id, $ids);
        $this->assertNotContains($atTo->id, $ids);
    }

    public function test_defaults_to_the_last_24_hours(): void
    {
        $monitor = Monitor::factory()->create();
        $recent = $this->checkAt($monitor, now()->subHours(23));
        $this->checkAt($monitor, now()->subHours(25));

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/checks")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$recent->id]);
    }

    public function test_results_are_cursor_paginated_newest_first(): void
    {
        $monitor = Monitor::factory()->create();
        foreach (range(1, 60) as $minutesAgo) {
            $this->checkAt($monitor, now()->subMinutes($minutesAgo));
        }

        $this->actingAs(User::factory()->admin()->create())->fromSpa();
        $firstPage = $this->getJson("/api/monitors/{$monitor->id}/checks")->assertOk();

        $firstPage->assertJsonCount(50, 'data');
        $startedAt = collect($firstPage->json('data'))->pluck('started_at');
        $this->assertSame($startedAt->sortDesc()->values()->all(), $startedAt->all());

        $nextCursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($nextCursor);

        $secondPage = $this->getJson("/api/monitors/{$monitor->id}/checks?cursor={$nextCursor}")->assertOk();
        $secondPage->assertJsonCount(10, 'data');
        $this->assertNull($secondPage->json('meta.next_cursor'));
        $this->assertEmpty(array_intersect($firstPage->json('data.*.id'), $secondPage->json('data.*.id')));
    }

    public function test_does_not_leak_other_monitors_results(): void
    {
        $monitor = Monitor::factory()->create();
        $own = $this->checkAt($monitor, now()->subMinute());
        $this->checkAt(Monitor::factory()->create(), now()->subMinute());
        $this->checkAt(Monitor::factory()->create(), now()->subMinute());

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/checks")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$own->id]);
    }

    public static function invalidPeriods(): array
    {
        return [
            'from after to' => ['from=2026-10-17T11:00:00Z&to=2026-10-17T10:00:00Z', 'to'],
            'from equal to' => ['from=2026-10-17T10:00:00Z&to=2026-10-17T10:00:00Z', 'to'],
            'more than 90 days' => ['from=2026-07-01T00:00:00Z&to=2026-10-17T00:00:00Z', 'from'],
            'invalid from' => ['from=yesterday-ish&to=2026-10-17T10:00:00Z', 'from'],
            'invalid to' => ['from=2026-10-17T10:00:00Z&to=not-a-date', 'to'],
        ];
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_periods_are_rejected(string $query, string $field): void
    {
        $monitor = Monitor::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->fromSpa()
            ->getJson("/api/monitors/{$monitor->id}/checks?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }
}
