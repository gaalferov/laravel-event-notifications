<?php

namespace Tests\Feature;

use App\Events\CommentPosted;
use App\Events\TaskAssigned;
use App\Events\TeammateInvited;
use App\Models\ActivityEvent;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\NotificationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventSimulatorTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private User $member;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::create(['name' => 'Acme']);
        $this->owner = User::create(['team_id' => $this->team->id, 'name' => 'Alice', 'email' => 'alice@acme.test', 'role' => 'owner']);
        $this->member = User::create(['team_id' => $this->team->id, 'name' => 'Bob', 'email' => 'bob@acme.test', 'role' => 'member']);
        $this->task = Task::create([
            'team_id' => $this->team->id,
            'owner_id' => $this->owner->id,
            'assignee_id' => null,
            'title' => 'Ship release notes',
            'description' => 'Q2 changelog.',
            'status' => 'open',
        ]);
    }

    // ─── /api/events/invite ───

    public function test_invite_dispatches_teammate_invited_event(): void
    {
        Event::fake([TeammateInvited::class]);

        $response = $this->postJson('/api/events/invite', [
            'inviter_email' => 'alice@acme.test',
            'invitee_name' => 'Carol',
            'invitee_email' => 'carol@acme.test',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(TeammateInvited::class, function (TeammateInvited $event) {
            return $event->inviter->email === 'alice@acme.test'
                && $event->invitee->email === 'carol@acme.test'
                && $event->invitee->team_id === $this->team->id;
        });

        $this->assertDatabaseHas('users', ['email' => 'carol@acme.test', 'team_id' => $this->team->id]);
    }

    public function test_invite_requires_existing_inviter(): void
    {
        $response = $this->postJson('/api/events/invite', [
            'inviter_email' => 'nobody@example.com',
            'invitee_name' => 'Dana',
            'invitee_email' => 'dana@acme.test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inviter_email']);
    }

    public function test_invite_rejects_duplicate_invitee_email(): void
    {
        $response = $this->postJson('/api/events/invite', [
            'inviter_email' => 'alice@acme.test',
            'invitee_name' => 'Duplicate',
            'invitee_email' => 'bob@acme.test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invitee_email']);
    }

    public function test_invite_validates_input_fields(): void
    {
        $response = $this->postJson('/api/events/invite', [
            'inviter_email' => 'not-an-email',
            'invitee_name' => '',
            'invitee_email' => 'also-bad',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inviter_email', 'invitee_name', 'invitee_email']);
    }

    // ─── /api/events/task-assigned ───

    public function test_assign_task_dispatches_task_assigned_event(): void
    {
        Event::fake([TaskAssigned::class]);

        $response = $this->postJson('/api/events/task-assigned', [
            'task_id' => $this->task->id,
            'assigner_email' => 'alice@acme.test',
            'assignee_email' => 'bob@acme.test',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(TaskAssigned::class, function (TaskAssigned $event) {
            return $event->task->id === $this->task->id
                && $event->assignee->email === 'bob@acme.test'
                && $event->assigner->email === 'alice@acme.test'
                && $event->task->assignee_id === $this->member->id;
        });
    }

    public function test_assign_task_rejects_cross_team_assignee(): void
    {
        $otherTeam = Team::create(['name' => 'Globex']);
        User::create(['team_id' => $otherTeam->id, 'name' => 'External', 'email' => 'ext@globex.test', 'role' => 'member']);

        $response = $this->postJson('/api/events/task-assigned', [
            'task_id' => $this->task->id,
            'assigner_email' => 'alice@acme.test',
            'assignee_email' => 'ext@globex.test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_email']);
    }

    public function test_assign_task_fails_for_unknown_task(): void
    {
        $response = $this->postJson('/api/events/task-assigned', [
            'task_id' => 9999,
            'assigner_email' => 'alice@acme.test',
            'assignee_email' => 'bob@acme.test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['task_id']);
    }

    public function test_assign_task_requires_existing_assignee_email(): void
    {
        $response = $this->postJson('/api/events/task-assigned', [
            'task_id' => $this->task->id,
            'assigner_email' => 'alice@acme.test',
            'assignee_email' => 'nobody@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_email']);
    }

    // ─── /api/events/comment ───

    public function test_comment_dispatches_comment_posted_event(): void
    {
        Event::fake([CommentPosted::class]);

        $response = $this->postJson('/api/events/comment', [
            'task_id' => $this->task->id,
            'author_email' => 'bob@acme.test',
            'body' => 'This looks good to me.',
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(CommentPosted::class, function (CommentPosted $event) {
            return $event->comment->task_id === $this->task->id
                && $event->comment->author->email === 'bob@acme.test'
                && $event->comment->body === 'This looks good to me.';
        });

        $this->assertDatabaseHas('comments', ['task_id' => $this->task->id, 'body' => 'This looks good to me.']);
    }

    public function test_comment_rejects_cross_team_author(): void
    {
        $otherTeam = Team::create(['name' => 'Globex']);
        User::create(['team_id' => $otherTeam->id, 'name' => 'External', 'email' => 'ext@globex.test', 'role' => 'member']);

        $response = $this->postJson('/api/events/comment', [
            'task_id' => $this->task->id,
            'author_email' => 'ext@globex.test',
            'body' => 'Intruder comment',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['author_email']);
    }

    public function test_comment_requires_existing_author_email(): void
    {
        $response = $this->postJson('/api/events/comment', [
            'task_id' => $this->task->id,
            'author_email' => 'ghost@example.com',
            'body' => 'hi',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['author_email']);
    }

    public function test_comment_validates_body_length(): void
    {
        $response = $this->postJson('/api/events/comment', [
            'task_id' => $this->task->id,
            'author_email' => 'bob@acme.test',
            'body' => str_repeat('a', 2001),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['body']);
    }

    // ─── End-to-end: listeners actually run ───

    public function test_invite_end_to_end_fires_both_listeners(): void
    {
        $mailer = $this->mock(NotificationMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $key, string $email, string $name, array $vars) {
                return $key === 'teammate_invited'
                    && $email === 'carol@acme.test'
                    && $vars['invitee_name'] === 'Carol'
                    && $vars['inviter_name'] === 'Alice'
                    && $vars['team_name'] === 'Acme';
            })
            ->andReturn(true);

        $this->postJson('/api/events/invite', [
            'inviter_email' => 'alice@acme.test',
            'invitee_name' => 'Carol',
            'invitee_email' => 'carol@acme.test',
        ])->assertStatus(201);

        $this->assertDatabaseHas('activity_events', [
            'team_id' => $this->team->id,
            'type' => ActivityEvent::TYPE_TEAMMATE_INVITED,
        ]);
    }

    public function test_comment_on_own_task_does_not_email_owner(): void
    {
        $mailer = $this->mock(NotificationMailer::class);
        $mailer->shouldNotReceive('send');

        // Alice (owner) comments on Alice's task
        $this->postJson('/api/events/comment', [
            'task_id' => $this->task->id,
            'author_email' => 'alice@acme.test',
            'body' => 'Note to self',
        ])->assertStatus(201);

        // Activity is still recorded even though no email was sent
        $this->assertDatabaseHas('activity_events', [
            'team_id' => $this->team->id,
            'type' => ActivityEvent::TYPE_COMMENT_POSTED,
        ]);
    }

    public function test_mail_failure_does_not_prevent_activity_recording(): void
    {
        $mailer = $this->mock(NotificationMailer::class);
        $mailer->shouldReceive('send')->once()->andReturn(false);

        $this->postJson('/api/events/invite', [
            'inviter_email' => 'alice@acme.test',
            'invitee_name' => 'Eve',
            'invitee_email' => 'eve@acme.test',
        ])->assertStatus(201);

        $this->assertDatabaseHas('activity_events', [
            'type' => ActivityEvent::TYPE_TEAMMATE_INVITED,
        ]);
        $this->assertDatabaseHas('users', ['email' => 'eve@acme.test']);
    }
}
