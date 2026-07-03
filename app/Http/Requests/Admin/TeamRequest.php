<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionLevel;
use App\Enums\TeamType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'leader_user_id' => $this->input('leader_user_id') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $teamId = $this->route('team')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TeamType::class)],
            'parent_id' => [
                'nullable',
                'exists:teams,id',
                Rule::notIn(array_filter([$teamId])),
            ],
            'leader_user_id' => ['nullable', 'exists:users,id'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['nullable', Rule::in([
                PermissionLevel::None->value,
                PermissionLevel::View->value,
                PermissionLevel::Full->value,
            ])],
        ];
    }
}
