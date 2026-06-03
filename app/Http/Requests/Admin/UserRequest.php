<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'team_id' => $this->input('team_id') ?: null,
            'manager_user_id' => $this->input('manager_user_id') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'team_id' => ['nullable', 'exists:teams,id'],
            'manager_user_id' => ['nullable', 'exists:users,id', Rule::notIn([$userId])],
            'is_team_leader' => ['sometimes', 'boolean'],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }
}
