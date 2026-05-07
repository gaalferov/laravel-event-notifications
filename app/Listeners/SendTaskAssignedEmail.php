<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Services\NotificationMailer;

class SendTaskAssignedEmail
{
    public function __construct(
        private readonly NotificationMailer $mailer,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $this->mailer->send(
            notificationKey: 'task_assigned',
            recipientEmail: $event->assignee->email,
            recipientName: $event->assignee->name,
            variables: [
                'assignee_name' => $event->assignee->name,
                'assigner_name' => $event->assigner->name,
                'task_title' => $event->task->title,
                'task_description' => $event->task->description ?? '',
                'task_id' => $event->task->id,
            ],
        );
    }
}
