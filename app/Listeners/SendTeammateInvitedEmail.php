<?php

namespace App\Listeners;

use App\Events\TeammateInvited;
use App\Services\NotificationMailer;

class SendTeammateInvitedEmail
{
    public function __construct(
        private readonly NotificationMailer $mailer,
    ) {}

    public function handle(TeammateInvited $event): void
    {
        $this->mailer->send(
            notificationKey: 'teammate_invited',
            recipientEmail: $event->invitee->email,
            recipientName: $event->invitee->name,
            variables: [
                'invitee_name' => $event->invitee->name,
                'inviter_name' => $event->inviter->name,
                'team_name' => $event->inviter->team->name,
            ],
        );
    }
}
