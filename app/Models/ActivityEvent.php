<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEvent extends Model
{
    public const TYPE_TEAMMATE_INVITED = 'teammate_invited';

    public const TYPE_TASK_ASSIGNED = 'task_assigned';

    public const TYPE_COMMENT_POSTED = 'comment_posted';

    protected $fillable = ['team_id', 'actor_id', 'type', 'payload', 'occurred_at'];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
