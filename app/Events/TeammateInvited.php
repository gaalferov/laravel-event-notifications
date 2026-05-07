<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class TeammateInvited
{
    use Dispatchable;

    public function __construct(
        public readonly User $inviter,
        public readonly User $invitee,
    ) {}
}
