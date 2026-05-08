<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmails;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the POST /api/events/invite payload.
 *
 * Emails are normalized (trimmed + lowercased) in prepareForValidation() so that
 * uniqueness/existence checks are case-insensitive and the seeded-user lookup in
 * the controller matches regardless of how the caller cased the input.
 */
class InviteTeammateRequest extends FormRequest
{
    use NormalizesEmails;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inviter_email' => $this->normalizeEmail($this->input('inviter_email')),
            'invitee_email' => $this->normalizeEmail($this->input('invitee_email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'inviter_email' => ['required', 'email', Rule::exists('users', 'email')],
            'invitee_name' => ['required', 'string', 'max:255'],
            'invitee_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'inviter_email.exists' => 'Unknown inviter - seed the database or use an existing user.',
            'invitee_email.unique' => 'A user with this email already exists.',
        ];
    }
}
