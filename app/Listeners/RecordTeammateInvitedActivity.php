<?php

namespace App\Listeners;

use App\Events\TeammateInvited;
use App\Models\ActivityEvent;
use App\Services\ActivityRecorder;

class RecordTeammateInvitedActivity
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {}

    public function handle(TeammateInvited $event): void
    {
        $this->recorder->record(
            teamId: $event->inviter->team_id,
            actor: $event->inviter,
            type: ActivityEvent::TYPE_TEAMMATE_INVITED,
            payload: [
                'invitee_id' => $event->invitee->id,
                'invitee_name' => $event->invitee->name,
                'inviter_name' => $event->inviter->name,
            ],
        );
    }
}
