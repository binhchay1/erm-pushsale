<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\VietnameseMobilePhone;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $company = $user->company;
        abort_unless($company, 404);

        $isInternal = $company->slug === TenantEmail::internalSlug();

        return Inertia::render('Admin/Company/Profile', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'plan' => $company->plan,
                'max_users' => $company->max_users,
                'contact_email' => $company->contact_email,
                'email_login_host' => Schema::hasColumn('companies', 'email_login_host')
                    ? ($company->email_login_host ?: TenantEmail::hostFor($company))
                    : TenantEmail::hostFor($company),
                'contact_phone' => $company->contact_phone,
                'tax_code' => $company->tax_code,
                'product_field' => Schema::hasColumn('companies', 'product_field') ? $company->product_field : null,
                'address' => $company->address,
                'address_2' => Schema::hasColumn('companies', 'address_2') ? $company->address_2 : null,
                'use_two_level_address' => Schema::hasColumn('companies', 'use_two_level_address') ? (bool) $company->use_two_level_address : false,
                'province_name' => Schema::hasColumn('companies', 'province_name') ? $company->province_name : null,
                'district_name' => Schema::hasColumn('companies', 'district_name') ? $company->district_name : null,
                'ward_name' => Schema::hasColumn('companies', 'ward_name') ? $company->ward_name : null,
                'website' => $company->website,
                'representative_name' => $company->representative_name,
                'representative_title' => $company->representative_title,
                'expires_at' => $company->expires_at?->toDateString(),
                'is_internal' => $isInternal,
            ],
            'emailIdentity' => [
                'suffix' => TenantEmail::suffixFor($company),
                'host' => TenantEmail::hostFor($company),
                'defaultHost' => TenantEmail::domain(),
                'isInternal' => $isInternal,
            ],
            'activeMenuCode' => '1.1.1',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        $company = $user->company;
        abort_unless($company, 404);

        $isInternal = $company->slug === TenantEmail::internalSlug();

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40', new VietnameseMobilePhone],
            'tax_code' => ['nullable', 'string', 'max:40'],
            'product_field' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'address_2' => ['nullable', 'string', 'max:500'],
            'use_two_level_address' => ['nullable', 'boolean'],
            'province_name' => ['nullable', 'string', 'max:120'],
            'district_name' => ['nullable', 'string', 'max:120'],
            'ward_name' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:160'],
            'representative_title' => ['nullable', 'string', 'max:160'],
        ];

        if (Schema::hasColumn('companies', 'email_login_host')) {
            $rules['email_login_host'] = [
                'required',
                'string',
                'max:120',
                Rule::regex('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i'),
            ];
        }

        $data = $request->validate($rules);

        $data['use_two_level_address'] = (bool) ($data['use_two_level_address'] ?? false);

        foreach (['contact_phone', 'product_field', 'address', 'address_2', 'province_name', 'district_name', 'ward_name', 'contact_email', 'tax_code', 'website', 'representative_name', 'representative_title'] as $optional) {
            if (array_key_exists($optional, $data) && (is_string($data[$optional]) ? trim($data[$optional]) === '' : $data[$optional] === null)) {
                $data[$optional] = null;
            }
        }

        if (isset($data['email_login_host'])) {
            $data['email_login_host'] = strtolower(ltrim(trim((string) $data['email_login_host']), '@')) ?: null;
        }

        $persistable = array_filter(
            $data,
            static fn (string $key): bool => Schema::hasColumn('companies', $key),
            ARRAY_FILTER_USE_KEY,
        );

        $company->update($persistable);

        return back()->with('success', 'Đã cập nhật thông tin đơn vị.');
    }
}
