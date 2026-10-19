<?php

namespace Tests\Feature\Incidents;

use App\Events\IncidentUpdated;
use App\Models\Incident;
use App\Models\IncidentNote;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AcknowledgeIncidentTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->monitor = Monitor::factory()->down()->create();
        $this->monitor->teams()->attach($this->team);
    }

    private function teamMember(): User
    {
        $user = User::factory()->create();
        $user->teams()->attach($this->team);

        return $user;
    }

    public function test_a_member_of_the_team_acknowledges_an_open_incident(): void
    {
        $this->freezeSecond();
        Event::fake([IncidentUpdated::class]);
        $member = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();

        $this->actingAs($member)->fromSpa()
            ->postJson("/api/incidents/{$incident->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.acknowledged_by', ['id' => $member->id, 'name' => $member->name])
            ->assertJsonPath('data.acknowledged_at', now()->toJSON());

        Event::assertDispatched(IncidentUpdated::class, fn (IncidentUpdated $event) => $event->incident->is($incident));
    }

    public function test_acknowledging_twice_keeps_the_first_acknowledger(): void
    {
        $first = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();

        $this->actingAs($first)->fromSpa()->postJson("/api/incidents/{$incident->id}/acknowledge")->assertOk();
        $this->actingAs($this->teamMember())->fromSpa()
            ->postJson("/api/incidents/{$incident->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.acknowledged_by.id', $first->id);
    }

    public function test_resolved_incidents_cannot_be_acknowledged(): void
    {
        $incident = Incident::factory()->for($this->monitor)->resolved()->create();

        $this->actingAs($this->teamMember())->fromSpa()
            ->postJson("/api/incidents/{$incident->id}/acknowledge")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('incident');
    }

    public function test_users_outside_the_team_get_not_found(): void
    {
        $outsider = User::factory()->create();
        $outsider->teams()->attach(Team::factory()->create());
        $incident = Incident::factory()->for($this->monitor)->open()->create();

        $this->actingAs($outsider)->fromSpa()->postJson("/api/incidents/{$incident->id}/acknowledge")->assertNotFound();
        $this->actingAs($outsider)->fromSpa()->getJson("/api/incidents/{$incident->id}/notes")->assertNotFound();
    }

    public function test_update_is_broadcast_to_users_who_can_see_the_monitor(): void
    {
        $member = $this->teamMember();
        $admin = User::factory()->admin()->create();
        User::factory()->create();
        $incident = Incident::factory()->for($this->monitor)->open()->create();

        $channels = collect((new IncidentUpdated($incident))->broadcastOn())->map(fn (PrivateChannel $channel) => $channel->name)->sort()->values()->all();

        $this->assertSame(collect(["private-users.{$member->id}.monitors", "private-users.{$admin->id}.monitors"])->sort()->values()->all(), $channels);
        $this->assertSame($incident->id, (new IncidentUpdated($incident))->broadcastWith()['incident']['id']);
    }

    public function test_deleting_the_acknowledger_keeps_the_acknowledgement_time(): void
    {
        $member = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();
        $this->actingAs($member)->fromSpa()->postJson("/api/incidents/{$incident->id}/acknowledge")->assertOk();

        $member->delete();

        $incident->refresh();
        $this->assertNull($incident->acknowledged_by);
        $this->assertNotNull($incident->acknowledged_at);
    }

    public function test_notes_are_listed_created_and_deleted_by_their_author_or_an_admin(): void
    {
        $author = $this->teamMember();
        $other = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();

        $noteId = $this->actingAs($author)->fromSpa()
            ->postJson("/api/incidents/{$incident->id}/notes", ['body' => 'Restarted the database.'])
            ->assertCreated()
            ->assertJsonPath('data.user.id', $author->id)
            ->assertJsonPath('data.can_delete', true)
            ->json('data.id');

        $this->actingAs($other)->fromSpa()
            ->getJson("/api/incidents/{$incident->id}/notes")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Restarted the database.')
            ->assertJsonPath('data.0.can_delete', false);

        $this->actingAs($other)->fromSpa()->deleteJson("/api/incidents/{$incident->id}/notes/{$noteId}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->fromSpa()->deleteJson("/api/incidents/{$incident->id}/notes/{$noteId}")->assertNoContent();

        $this->assertDatabaseMissing('incident_notes', ['id' => $noteId]);
    }

    public function test_notes_are_validated_and_scoped_to_their_incident(): void
    {
        $author = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();
        $otherIncident = Incident::factory()->for($this->monitor)->resolved()->create();
        $note = IncidentNote::factory()->for($otherIncident)->for($author)->create();
        $this->actingAs($author)->fromSpa();

        $this->postJson("/api/incidents/{$incident->id}/notes", ['body' => ''])->assertJsonValidationErrors('body');
        $this->postJson("/api/incidents/{$incident->id}/notes", ['body' => str_repeat('a', 2001)])->assertJsonValidationErrors('body');
        $this->deleteJson("/api/incidents/{$incident->id}/notes/{$note->id}")->assertNotFound();
    }

    public function test_incident_lists_include_acknowledgement_and_note_counts(): void
    {
        $member = $this->teamMember();
        $incident = Incident::factory()->for($this->monitor)->open()->create();
        IncidentNote::factory()->for($incident)->for($member)->count(2)->create();

        $this->actingAs($member)->fromSpa()
            ->getJson("/api/monitors/{$this->monitor->id}/incidents")
            ->assertOk()
            ->assertJsonPath('data.0.notes_count', 2)
            ->assertJsonPath('data.0.acknowledged_by', null)
            ->assertJsonPath('data.0.reminder_count', 0);
    }
}
