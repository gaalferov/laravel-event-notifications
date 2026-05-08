<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    // `role` is server-controlled. The simulator endpoints set it explicitly
    // to 'member'; never accept it from request input in real product flows.
    protected $fillable = ['team_id', 'name', 'email', 'role'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function ownedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_id');
    }
}
