<?php

namespace Tests\Feature\Account;

use App\Models\AuditLogEntry;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_are_recorded_but_system_updates_are_not(): void
    {
        $admin = User::factory()->admin()->create();
        $monitor = Monitor::factory()->create(['name' => 'Shop']);

        $monitor->update(['last_latency_ms' => 120]);
        $this->assertSame(0, AuditLogEntry::query()->count());

        $this->actingAs($admin)->fromSpa()->postJson("/api/monitors/{$monitor->id}/pause")->assertOk();

        $entry = AuditLogEntry::query()->sole();
        $this->assertSame('monitor.updated', $entry->action);
        $this->assertSame('monitor', $entry->subject_type);
        $this->assertSame($monitor->id, $entry->subject_id);
        $this->assertSame('Shop', $entry->subject_label);
        $this->assertSame(['fields' => ['is_paused']], $entry->metadata);
        $this->assertSame($admin->name, $entry->actor_name);
    }

    public function test_only_admins_read_the_audit_log_with_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $monitor = Monitor::factory()->create();
        $this->actingAs($admin);
        $monitor->update(['name' => 'Renamed']);
        $monitor->delete();

        $this->actingAs($member)->fromSpa()->getJson('/api/audit-log')->assertForbidden();

        $this->actingAs($admin)->fromSpa()->getJson('/api/audit-log')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'monitor.deleted')
            ->assertJsonPath('data.0.actor.id', $admin->id)
            ->assertJsonPath('data.0.subject.label', 'Renamed')
            ->assertJsonPath('data.1.metadata.fields', ['name']);

        $this->getJson('/api/audit-log?action=monitor.updated')->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-log?action=DROP TABLE')->assertJsonValidationErrors('action');
    }

    public function test_old_entries_are_pruned(): void
    {
        config(['pulsecheck.audit_log_retention_days' => 30]);
        $old = AuditLogEntry::query()->create(['action' => 'auth.login', 'created_at' => now()->subDays(31)]);
        $recent = AuditLogEntry::query()->create(['action' => 'auth.login']);

        $this->artisan('model:prune', ['--model' => AuditLogEntry::class])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
