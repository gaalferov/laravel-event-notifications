<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\User;

/**
 * Persists activity events for later consumption (weekly digest).
 *
 * Kept separate from notification sending so both concerns can evolve
 * independently — adding a new event type just requires:
 *  1. A new Event class
 *  2. A listener that records activity (via this service)
 *  3. A listener that sends a notification (via NotificationMailer)
 */
class ActivityRecorder
{
    public function record(int $teamId, ?User $actor, string $type, array $payload): ActivityEvent
    {
        return ActivityEvent::create([
            'team_id' => $teamId,
            'actor_id' => $actor?->id,
            'type' => $type,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
