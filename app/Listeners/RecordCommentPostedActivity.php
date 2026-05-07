<?php

namespace App\Listeners;

use App\Events\CommentPosted;
use App\Models\ActivityEvent;
use App\Services\ActivityRecorder;

class RecordCommentPostedActivity
{
    public function __construct(
        private readonly ActivityRecorder $recorder,
    ) {}

    public function handle(CommentPosted $event): void
    {
        $comment = $event->comment;
        $task = $comment->task;

        $this->recorder->record(
            teamId: $task->team_id,
            actor: $comment->author,
            type: ActivityEvent::TYPE_COMMENT_POSTED,
            payload: [
                'comment_id' => $comment->id,
                'task_id' => $task->id,
                'task_title' => $task->title,
                'author_name' => $comment->author->name,
                'excerpt' => mb_strimwidth($comment->body, 0, 140, '…'),
            ],
        );
    }
}
