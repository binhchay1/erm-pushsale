<?php

namespace App\Http\Controllers\Platform;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $provisioning,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $companies = Company::query()
            ->withCount('users')
            ->with('owner:id,name,email')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'plan' => $company->plan,
                'is_internal' => $company->isInternal(),
                'is_active' => $company->isActive(),
                'users_count' => $company->users_count,
                'max_users' => $company->max_users,
                'owner' => $company->owner ? [
                    'name' => $company->owner->name,
                    'email' => $company->owner->email,
                ] : null,
                'expires_at' => $company->expires_at?->toDateString(),
                'created_at' => $company->created_at?->toDateString(),
                'lead_import_template_name' => $company->lead_import_template_name,
                'has_lead_import_template' => filled($company->lead_import_template_path),
            ]);

        return Inertia::render('Platform/Companies', [
            'companies' => $companies,
            'filters' => ['search' => $search],
            'stats' => [
                'total' => Company::query()->count(),
                'active' => Company::query()->where('status', Company::STATUS_ACTIVE)->count(),
                'users' => User::query()->withoutGlobalScope(TenantScope::class)
                    ->whereNotNull('company_id')->count(),
            ],
            'emailDomain' => TenantEmail::domain(),
            'internalSlug' => TenantEmail::internalSlug(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:companies,slug'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['nullable', 'email', 'max:160', 'unique:users,email'],
            'owner_password' => ['nullable', 'string', 'min:8'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'plan' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (($data['slug'] ?? null) === TenantEmail::internalSlug()) {
            return back()->withErrors(['slug' => __('messages.platform.slug_reserved')]);
        }

        $result = $this->provisioning->createCommercialCompany(
            companyName: $data['name'],
            ownerName: $data['owner_name'],
            slug: $data['slug'] ?? null,
            ownerEmail: $data['owner_email'] ?? null,
            password: $data['owner_password'] ?? null,
            contactEmail: $data['contact_email'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            plan: $data['plan'] ?? 'trial',
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : now()->addDays(30),
        );

        return redirect()
            ->route('platform.companies.index')
            ->with('success', __('messages.platform.company_created'))
            ->with('provisioned', [
                'company_name' => $result['company']->name,
                'company_slug' => $result['company']->slug,
                'owner_name' => $result['owner']->name,
                'owner_email' => $result['owner']->email,
                'default_password' => $result['default_password'],
                'suggested_accounts' => $result['suggested_accounts'],
            ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plan' => ['nullable', 'string', 'max:40'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'lead_import_template' => ['nullable', 'file', 'max:5120', 'mimes:csv,txt,xls,xlsx'],
            'remove_lead_import_template' => ['nullable', 'boolean'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'plan' => $data['plan'] ?? $company->plan,
            'max_users' => $data['max_users'] ?? $company->max_users,
            'expires_at' => $data['expires_at'] ?? $company->expires_at,
        ];

        if ($request->boolean('remove_lead_import_template')) {
            $this->deleteTemplate($company);
            $attributes['lead_import_template_path'] = null;
            $attributes['lead_import_template_name'] = null;
        }

        if ($request->hasFile('lead_import_template')) {
            $this->deleteTemplate($company);
            $file = $request->file('lead_import_template');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
            $path = $file->storeAs('lead-templates', 'company-'.$company->id.'.'.$ext, 'local');

            $attributes['lead_import_template_path'] = $path;
            $attributes['lead_import_template_name'] = $file->getClientOriginalName();
        }

        $company->update($attributes);

        return back()->with('success', __('messages.platform.company_updated'));
    }

    private function deleteTemplate(Company $company): void
    {
        if ($company->lead_import_template_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($company->lead_import_template_path)
        ) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($company->lead_import_template_path);
        }
    }

    public function toggle(Company $company): RedirectResponse
    {
        if ($company->isInternal()) {
            return back()->with('error', __('messages.platform.cannot_suspend_internal'));
        }

        $company->update([
            'status' => $company->status === Company::STATUS_ACTIVE
                ? Company::STATUS_SUSPENDED
                : Company::STATUS_ACTIVE,
        ]);

        return back()->with('success', __('messages.platform.company_status_changed'));
    }

    /** Gợi ý định danh tài khoản theo quy ước email của công ty. */
    public function accounts(Company $company): Response
    {
        return Inertia::render('Platform/CompanyAccounts', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'is_internal' => $company->isInternal(),
            ],
            'suggested_accounts' => TenantEmail::suggestedAccounts($company),
            'default_password' => TenantEmail::defaultPassword(),
        ]);
    }

    /** Super admin quản lý nhiều admin (giám đốc) cho từng công ty. */
    public function admins(Company $company): Response
    {
        return Inertia::render('Platform/CompanyAdmins', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'is_internal' => $company->isInternal(),
            ],
            'admins' => $this->companyAdmins($company),
            'suggested_email' => TenantEmail::forRole(UserRole::Admin, $company),
            'email_suffix' => TenantEmail::suffixFor($company),
            'default_password' => TenantEmail::defaultPassword(),
        ]);
    }

    public function storeAdmin(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $email = $data['email'] ?? null;
        if ($email !== null && ! TenantEmail::acceptsForCompany($email, $company)) {
            return back()->withErrors([
                'email' => __('messages.tenant.invalid_email_suffix', ['suffix' => TenantEmail::suffixFor($company)]),
            ]);
        }

        $this->provisioning->createCompanyAdmin(
            company: $company,
            name: $data['name'],
            email: $email,
            password: $data['password'] ?? null,
        );

        return back()->with('success', __('messages.platform.admin_created'));
    }

    public function updateAdmin(Request $request, Company $company, User $admin): RedirectResponse
    {
        abort_unless($this->isCompanyAdmin($company, $admin), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $admin->name = $data['name'];
        if (! empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }
        $admin->save();

        return back()->with('success', __('messages.platform.admin_updated'));
    }

    public function destroyAdmin(Company $company, User $admin): RedirectResponse
    {
        abort_unless($this->isCompanyAdmin($company, $admin), 404);

        if ($admin->is_owner) {
            return back()->with('error', __('messages.platform.cannot_delete_owner'));
        }

        $admin->delete();

        return back()->with('success', __('messages.platform.admin_deleted'));
    }

    private function isCompanyAdmin(Company $company, User $admin): bool
    {
        return $admin->company_id === $company->id
            && $admin->role === UserRole::Admin
            && ! $admin->is_platform_admin;
    }

    /** @return array<int, array<string, mixed>> */
    private function companyAdmins(Company $company): array
    {
        return User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('company_id', $company->id)
            ->where('role', UserRole::Admin)
            ->where('is_platform_admin', false)
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_owner', 'created_at'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_owner' => (bool) $u->is_owner,
                'created_at' => $u->created_at?->toDateString(),
            ])
            ->all();
    }
}
