<?php

namespace Tests\Feature\Models;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_removes_all_of_their_data(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user)->create();
        CheckResult::factory()->for($monitor)->count(3)->create();
        Incident::factory()->for($monitor)->resolved()->create();
        Incident::factory()->for($monitor)->open()->create();
        NotificationSetting::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('monitors', 0);
        $this->assertDatabaseCount('check_results', 0);
        $this->assertDatabaseCount('incidents', 0);
        $this->assertDatabaseCount('notification_settings', 0);
    }

    public function test_deleting_a_user_keeps_other_users_data(): void
    {
        $otherMonitor = Monitor::factory()->create();
        CheckResult::factory()->for($otherMonitor)->create();
        $user = User::factory()->has(Monitor::factory())->create();

        $user->delete();

        $this->assertModelExists($otherMonitor);
        $this->assertSame(1, $otherMonitor->checkResults()->count());
    }

    public function test_relationships_connect_users_monitors_results_and_incidents(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user)->create();
        $checkResult = CheckResult::factory()->for($monitor)->create();
        $incident = Incident::factory()->for($monitor)->create();

        $this->assertTrue($user->monitors->first()->is($monitor));
        $this->assertTrue($monitor->user->is($user));
        $this->assertTrue($monitor->checkResults->first()->is($checkResult));
        $this->assertTrue($monitor->incidents->first()->is($incident));
        $this->assertTrue($checkResult->monitor->is($monitor));
        $this->assertTrue($incident->monitor->is($monitor));
    }
}
