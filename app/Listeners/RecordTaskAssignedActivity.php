<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Models\ActivityEvent;
use App\Services\ActivityRecorder;

class RecordTaskAssignedActivity
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {}

    public function handle(TaskAssigned $event): void
    {
        $this->recorder->record(
            teamId: $event->task->team_id,
            actor: $event->assigner,
            type: ActivityEvent::TYPE_TASK_ASSIGNED,
            payload: [
                'task_id' => $event->task->id,
                'task_title' => $event->task->title,
                'assignee_id' => $event->assignee->id,
                'assignee_name' => $event->assignee->name,
                'assigner_name' => $event->assigner->name,
            ],
        );
    }
}
