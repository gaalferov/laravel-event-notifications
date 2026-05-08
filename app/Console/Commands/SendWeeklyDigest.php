<?php

namespace App\Console\Commands;

use App\Models\ActivityEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Compiles the last N days of activity per team and sends one digest email
 * to each team owner via Mailtrap's template API.
 *
 * A failure for one team is isolated - we log it and continue so the remaining
 * teams still get their digest.
 */
class SendWeeklyDigest extends Command
{
    protected $signature = 'digest:send
                            {--team= : Limit to a single team (by team ID)}
                            {--days= : Override the digest window in days}';

    protected $description = 'Compile recent activity per team and send a weekly digest email via Mailtrap';

    public function __construct(
        private readonly NotificationMailer $mailer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('notifications.digest_window_days', 7));
        $since = now()->subDays($days);

        $teamsQuery = Team::query();
        if ($this->option('team') !== null) {
            $teamsQuery->whereKey((int) $this->option('team'));
        }

        $teams = $teamsQuery->get();

        if ($teams->isEmpty()) {
            $this->warn('No teams to process.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($teams as $team) {
            $events = ActivityEvent::where('team_id', $team->id)
                ->where('occurred_at', '>=', $since)
                ->orderBy('occurred_at')
                ->get();

            if ($events->isEmpty()) {
                $this->line("Team {$team->name}: no activity in the last {$days} day(s), skipping.");
                $skipped++;

                continue;
            }

            $recipient = User::where('team_id', $team->id)->where('role', 'owner')->first();

            if (! $recipient) {
                $this->warn("Team {$team->name}: no owner found, skipping.");
                $skipped++;

                continue;
            }

            $success = $this->mailer->send(
                notificationKey: 'weekly_digest',
                recipientEmail: $recipient->email,
                recipientName: $recipient->name,
                variables: [
                    'owner_name' => $recipient->name,
                    'team_name' => $team->name,
                    'window_days' => $days,
                    'event_count' => $events->count(),
                    'events' => $events->map(fn (ActivityEvent $e) => [
                        'type' => $e->type,
                        'type_label' => $this->typeLabel($e->type),
                        'occurred_at' => $e->occurred_at?->toIso8601String(),
                        'payload' => $e->payload,
                    ])->values()->all(),
                ],
            );

            if ($success) {
                $this->info("Team {$team->name}: digest sent to {$recipient->email} ({$events->count()} events).");
                $sent++;
            } else {
                $this->error("Team {$team->name}: digest delivery failed, continuing with next team.");
                $failed++;
            }
        }

        Log::info('Weekly digest run complete', [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'window_days' => $days,
        ]);

        $this->newLine();
        $this->info("Done. Sent: {$sent}, skipped: {$skipped}, failed: {$failed}.");

        return self::SUCCESS;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            ActivityEvent::TYPE_TEAMMATE_INVITED => 'Teammate invited',
            ActivityEvent::TYPE_TASK_ASSIGNED => 'Task assigned',
            ActivityEvent::TYPE_COMMENT_POSTED => 'Comment posted',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
