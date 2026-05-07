<?php

namespace App\Listeners;

use App\Events\CommentPosted;
use App\Services\NotificationMailer;

class SendCommentPostedEmail
{
    public function __construct(
        private readonly NotificationMailer $mailer,
    ) {}

    public function handle(CommentPosted $event): void
    {
        $event->comment->loadMissing(['task.owner', 'author']);

        $comment = $event->comment;
        $task = $comment->task;
        $owner = $task->owner;
        $author = $comment->author;

        if (! $owner || ! $author) {
            return;
        }

        // Don't notify the owner if they authored their own comment.
        if ($owner->id === $author->id) {
            return;
        }

        $this->mailer->send(
            notificationKey: 'comment_posted',
            recipientEmail: $owner->email,
            recipientName: $owner->name,
            variables: [
                'owner_name' => $owner->name,
                'author_name' => $author->name,
                'task_title' => $task->title,
                'task_id' => $task->id,
                'comment_body' => $comment->body,
            ],
        );
    }
}
