<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Support\TenantEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $domain = TenantEmail::domain();
        $slug = TenantEmail::internalSlug();

        return Inertia::render('Platform/Settings', [
            'tenant' => [
                'internal_name' => TenantEmail::internalName(),
                'internal_slug' => $slug,
                'email_domain' => $domain,
                'default_password' => TenantEmail::defaultPassword(),
            ],
            // Ví dụ định danh để super admin thấy ngay quy ước chung.
            'preview' => [
                'internal' => 'admin@'.$domain,
                'company' => 'admin@acme.'.$domain,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'internal_name' => ['required', 'string', 'max:120'],
            // Định danh đăng nhập CHUNG: dùng cho cả nội bộ và doanh nghiệp khác.
            'email_domain' => ['required', 'string', 'max:80', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i'],
            'default_password' => ['nullable', 'string', 'min:6', 'max:64'],
        ], [
            'email_domain.regex' => __('messages.platform.invalid_domain'),
        ]);

        AppSetting::setPlatform('tenant.internal_name', trim($data['internal_name']));
        AppSetting::setPlatform('tenant.email_domain', strtolower(trim($data['email_domain'])));

        if (! empty($data['default_password'])) {
            AppSetting::setPlatform('tenant.default_password', $data['default_password']);
        }

        return back()->with('success', __('messages.platform.settings_saved'));
    }
}
