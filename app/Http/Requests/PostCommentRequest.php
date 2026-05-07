<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmails;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the POST /api/events/comment payload.
 *
 * Emails are normalized up front so downstream lookups and rule checks are
 * case-insensitive. A withValidator() hook enforces that the author belongs to
 * the same team as the task.
 */
class PostCommentRequest extends FormRequest
{
    use NormalizesEmails;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'author_email' => $this->normalizeEmail($this->input('author_email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')],
            'author_email' => ['required', 'email', Rule::exists('users', 'email')],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $task = Task::find($this->input('task_id'));
            $author = User::where('email', $this->input('author_email'))->first();

            if ($task && $author && $author->team_id !== $task->team_id) {
                $validator->errors()->add(
                    'author_email',
                    'Author must belong to the same team as the task.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'task_id.exists' => 'Unknown task — check the ID.',
            'author_email.exists' => 'Unknown author — seed the database or use an existing user.',
        ];
    }
}
