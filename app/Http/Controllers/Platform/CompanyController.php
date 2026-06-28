<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $company->update($data);

        return back()->with('success', __('messages.platform.company_updated'));
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
}
