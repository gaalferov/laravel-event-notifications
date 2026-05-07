<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\NotificationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDigestTest extends TestCase
{
    use RefreshDatabase;

    private Team $acme;

    private Team $globex;

    private User $acmeOwner;

    private User $globexOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Team::create(['name' => 'Acme']);
        $this->globex = Team::create(['name' => 'Globex']);

        $this->acmeOwner = User::create(['team_id' => $this->acme->id, 'name' => 'Alice', 'email' => 'alice@acme.test', 'role' => 'owner']);
        $this->globexOwner = User::create(['team_id' => $this->globex->id, 'name' => 'Dave', 'email' => 'dave@globex.test', 'role' => 'owner']);

        $this->seedActivity($this->acme->id, $this->acmeOwner, now()->subDays(2), 'teammate_invited', ['inviter_name' => 'Alice', 'invitee_name' => 'Carol']);
        $this->seedActivity($this->acme->id, $this->acmeOwner, now()->subDays(1), 'task_assigned', ['task_title' => 'Ship docs', 'assignee_name' => 'Bob']);

        // Globex has only older activity — outside the default 7-day window
        $this->seedActivity($this->globex->id, $this->globexOwner, now()->subDays(30), 'task_assigned', ['task_title' => 'Old task']);
    }

    private function seedActivity(int $teamId, User $actor, $when, string $type, array $payload): void
    {
        ActivityEvent::create([
            'team_id' => $teamId,
            'actor_id' => $actor->id,
            'type' => $type,
            'payload' => $payload,
            'occurred_at' => $when,
        ]);
    }

    public function test_digest_sends_one_email_per_team_with_recent_activity(): void
    {
        $mailer = $this->mock(NotificationMailer::class);

        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $key, string $email, string $name, array $vars) {
                return $key === 'weekly_digest'
                    && $email === 'alice@acme.test'
                    && $vars['team_name'] === 'Acme'
                    && $vars['event_count'] === 2
                    && count($vars['events']) === 2
                    && $vars['window_days'] === 7;
            })
            ->andReturn(true);

        $this->artisan('digest:send')->assertExitCode(0);
    }

    public function test_digest_skips_teams_without_recent_activity(): void
    {
        $mailer = $this->mock(NotificationMailer::class);
        // Only Acme should receive — Globex's only event is 30 days old.
        $mailer->shouldReceive('send')->once()->andReturn(true);

        $this->artisan('digest:send')->assertExitCode(0);
    }

    public function test_digest_window_can_be_overridden(): void
    {
        $mailer = $this->mock(NotificationMailer::class);

        // With a 60-day window, Globex's 30-day-old activity is included.
        $mailer->shouldReceive('send')->twice()->andReturn(true);

        $this->artisan('digest:send', ['--days' => 60])->assertExitCode(0);
    }

    public function test_digest_can_target_a_single_team(): void
    {
        $mailer = $this->mock(NotificationMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $key, string $email) => $email === 'alice@acme.test')
            ->andReturn(true);

        $this->artisan('digest:send', ['--team' => $this->acme->id])->assertExitCode(0);
    }

    public function test_digest_isolates_failures_between_teams(): void
    {
        // Add activity to Globex so both teams are candidates
        $this->seedActivity($this->globex->id, $this->globexOwner, now()->subDays(1), 'comment_posted', ['excerpt' => 'Nice work']);

        $mailer = $this->mock(NotificationMailer::class);

        // Both teams attempted — one fails, the other succeeds. No crash.
        $mailer->shouldReceive('send')
            ->twice()
            ->andReturn(false, true);

        $this->artisan('digest:send')->assertExitCode(0);
    }

    public function test_digest_skips_team_without_owner(): void
    {
        // New team, no owner
        Team::create(['name' => 'Ghost Team']);

        $mailer = $this->mock(NotificationMailer::class);
        // Only Acme still qualifies
        $mailer->shouldReceive('send')->once()->andReturn(true);

        $this->artisan('digest:send')->assertExitCode(0);
    }
}
