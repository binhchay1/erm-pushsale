<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Users\UserHierarchyService;
use App\Services\Users\UserOrgRules;
use App\Support\TenantEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        $target = $this->route('user');
        if ($target instanceof User) {
            return app(UserHierarchyService::class)->canManage($actor, $target);
        }

        // Tạo mới: cần quyền toàn phần khu vực Nhân sự.
        return $actor->allows(PermissionArea::Hr, PermissionLevel::Full);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'team_id' => $this->input('team_id') ?: null,
            'manager_user_id' => $this->input('manager_user_id') ?: null,
            'org_level' => $this->input('org_level') ?: null,
        ]);

        $company = $this->user()?->company;
        if ($company instanceof Company && $this->has('email_local')) {
            $local = TenantEmail::normalizeLocalPart((string) $this->input('email_local', ''));
            $this->merge([
                'email_local' => $local,
                'email' => TenantEmail::build($local, $company),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email_local' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9]([a-z0-9._-]*[a-z0-9])?$/i'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'team_id' => ['nullable', 'exists:teams,id'],
            'manager_user_id' => ['nullable', 'exists:users,id', Rule::notIn([$userId])],
            'is_team_leader' => ['sometimes', 'boolean'],
            'org_level' => ['nullable', Rule::enum(OrgLevel::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'password' => [$userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['nullable', Rule::in([
                PermissionLevel::None->value,
                PermissionLevel::View->value,
                PermissionLevel::Full->value,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            UserOrgRules::validate($v);

            $actor = $this->user();
            if ($actor) {
                $assignable = array_map(
                    fn (UserRole $r) => $r->value,
                    app(UserHierarchyService::class)->assignableRoles($actor),
                );
                if (! in_array((string) $this->input('role'), $assignable, true)) {
                    $v->errors()->add('role', __('messages.user_role_not_allowed'));
                }
            }

            $company = $this->user()?->company;
            $email = (string) $this->input('email');

            if ($company instanceof Company && $email !== '' && ! TenantEmail::acceptsForCompany($email, $company)) {
                $v->errors()->add(
                    'email_local',
                    __('messages.tenant.invalid_email_suffix', ['suffix' => TenantEmail::suffixFor($company)]),
                );
            }
        });
    }
}
