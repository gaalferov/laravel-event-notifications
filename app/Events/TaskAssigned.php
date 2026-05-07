<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class TaskAssigned
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
        public readonly User $assigner,
        public readonly User $assignee,
    ) {}
}
