<?php

namespace Tests\Feature\Models;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_keeps_the_shared_data(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->create();
        CheckResult::factory()->for($monitor)->count(3)->create();
        $channel = NotificationChannel::factory()->create();

        $user->delete();

        $this->assertModelExists($monitor);
        $this->assertModelExists($channel);
        $this->assertSame(3, $monitor->checkResults()->count());
    }

    public function test_deleting_a_monitor_removes_its_results_and_incidents(): void
    {
        $monitor = Monitor::factory()->create();
        CheckResult::factory()->for($monitor)->count(3)->create();
        Incident::factory()->for($monitor)->resolved()->create();
        Incident::factory()->for($monitor)->open()->create();

        $monitor->delete();

        $this->assertDatabaseCount('check_results', 0);
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_relationships_connect_monitors_results_and_incidents(): void
    {
        $monitor = Monitor::factory()->create();
        $checkResult = CheckResult::factory()->for($monitor)->create();
        $incident = Incident::factory()->for($monitor)->create();

        $this->assertTrue($monitor->checkResults->first()->is($checkResult));
        $this->assertTrue($monitor->incidents->first()->is($incident));
        $this->assertTrue($checkResult->monitor->is($monitor));
        $this->assertTrue($incident->monitor->is($monitor));
    }
}
