<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesEmails;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the POST /api/events/task-assigned payload.
 *
 * In addition to schema-level rules, a withValidator() hook enforces that both
 * the assigner and the assignee belong to the same team as the task. Doing this
 * here keeps the controller free of cross-model guard logic.
 */
class AssignTaskRequest extends FormRequest
{
    use NormalizesEmails;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'assigner_email' => $this->normalizeEmail($this->input('assigner_email')),
            'assignee_email' => $this->normalizeEmail($this->input('assignee_email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', Rule::exists('tasks', 'id')],
            'assigner_email' => ['required', 'email', Rule::exists('users', 'email')],
            'assignee_email' => ['required', 'email', Rule::exists('users', 'email')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $task = Task::find($this->input('task_id'));
            $assigner = User::where('email', $this->input('assigner_email'))->first();
            $assignee = User::where('email', $this->input('assignee_email'))->first();

            if ($task && $assigner && $assigner->team_id !== $task->team_id) {
                $validator->errors()->add(
                    'assigner_email',
                    'Assigner must belong to the same team as the task.',
                );
            }

            if ($task && $assignee && $assignee->team_id !== $task->team_id) {
                $validator->errors()->add(
                    'assignee_email',
                    'Assignee must belong to the same team as the task.',
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'task_id.exists' => 'Unknown task — check the ID.',
            'assigner_email.exists' => 'Unknown assigner — seed the database or use an existing user.',
            'assignee_email.exists' => 'Unknown assignee — the user does not exist.',
        ];
    }
}
