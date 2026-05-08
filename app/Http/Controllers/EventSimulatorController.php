<?php

namespace App\Http\Controllers;

use App\Events\CommentPosted;
use App\Events\TaskAssigned;
use App\Events\TeammateInvited;
use App\Http\Requests\AssignTaskRequest;
use App\Http\Requests\InviteTeammateRequest;
use App\Http\Requests\PostCommentRequest;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Exposes HTTP endpoints to simulate each domain event.
 *
 * In a real SaaS these events would fire from your invite flow, task manager,
 * and commenting UI. Here we expose endpoints that mirror those user actions
 * so the email integration can be exercised end-to-end without building out
 * full features.
 *
 * Validation (including email normalization, existence checks, and cross-team
 * guards) lives in the FormRequest classes in app/Http/Requests - the
 * controller only deals with persistence and event dispatching.
 */
class EventSimulatorController extends Controller
{
    public function invite(InviteTeammateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $inviter = User::where('email', $data['inviter_email'])->firstOrFail();

        $invitee = User::create([
            'team_id' => $inviter->team_id,
            'name' => $data['invitee_name'],
            'email' => $data['invitee_email'],
            'role' => 'member',
        ]);

        TeammateInvited::dispatch($inviter, $invitee);

        return response()->json([
            'status' => 'ok',
            'invitee_id' => $invitee->id,
            'team_id' => $invitee->team_id,
        ], 201);
    }

    public function assignTask(AssignTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $task = Task::findOrFail($data['task_id']);
        $assigner = User::where('email', $data['assigner_email'])->firstOrFail();
        $assignee = User::where('email', $data['assignee_email'])->firstOrFail();

        $task->update(['assignee_id' => $assignee->id]);

        TaskAssigned::dispatch($task->fresh(), $assigner, $assignee);

        return response()->json([
            'status' => 'ok',
            'task_id' => $task->id,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function comment(PostCommentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $task = Task::findOrFail($data['task_id']);
        $author = User::where('email', $data['author_email'])->firstOrFail();

        $comment = Comment::create([
            'task_id' => $task->id,
            'author_id' => $author->id,
            'body' => $data['body'],
        ]);

        CommentPosted::dispatch($comment->fresh(['task.owner', 'author']));

        return response()->json([
            'status' => 'ok',
            'comment_id' => $comment->id,
            'task_id' => $task->id,
        ], 201);
    }
}
