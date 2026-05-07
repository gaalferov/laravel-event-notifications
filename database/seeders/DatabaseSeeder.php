<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Team::create(['name' => 'Acme Engineering']);
        $globex = Team::create(['name' => 'Globex Product']);

        // Acme team members
        $alice = User::create(['team_id' => $acme->id, 'name' => 'Alice Chen', 'email' => 'alice@acme.test', 'role' => 'owner']);
        $bob = User::create(['team_id' => $acme->id, 'name' => 'Bob Patel', 'email' => 'bob@acme.test', 'role' => 'member']);
        $carol = User::create(['team_id' => $acme->id, 'name' => 'Carol Santos', 'email' => 'carol@acme.test', 'role' => 'member']);

        // Globex team members
        $dave = User::create(['team_id' => $globex->id, 'name' => 'Dave Kim', 'email' => 'dave@globex.test', 'role' => 'owner']);
        $erin = User::create(['team_id' => $globex->id, 'name' => 'Erin O\'Neill', 'email' => 'erin@globex.test', 'role' => 'member']);

        // Acme tasks
        $task1 = Task::create([
            'team_id' => $acme->id,
            'owner_id' => $alice->id,
            'assignee_id' => $bob->id,
            'title' => 'Ship the Q2 release notes',
            'description' => 'Collect changelog entries from each squad and publish.',
            'status' => 'open',
        ]);

        Task::create([
            'team_id' => $acme->id,
            'owner_id' => $alice->id,
            'assignee_id' => $carol->id,
            'title' => 'Review onboarding flow copy',
            'description' => 'Sign-off on the new copy before marketing links it.',
            'status' => 'open',
        ]);

        // Globex task
        Task::create([
            'team_id' => $globex->id,
            'owner_id' => $dave->id,
            'assignee_id' => $erin->id,
            'title' => 'Draft Q3 roadmap outline',
            'description' => 'One-pager for next week\'s leadership review.',
            'status' => 'open',
        ]);

        // A comment to prime the activity feed
        Comment::create([
            'task_id' => $task1->id,
            'author_id' => $carol->id,
            'body' => 'I can pick up the performance section if it helps.',
        ]);
    }
}
