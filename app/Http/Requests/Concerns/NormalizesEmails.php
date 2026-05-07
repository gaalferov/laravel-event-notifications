<?php

namespace App\Http\Requests\Concerns;

trait NormalizesEmails
{
    protected function normalizeEmail(mixed $value): mixed
    {
        return is_string($value) ? strtolower(trim($value)) : $value;
    }
}
