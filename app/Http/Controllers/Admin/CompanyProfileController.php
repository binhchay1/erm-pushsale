<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Admin/Company/Profile', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'status' => $company->status,
                'plan' => $company->plan,
                'max_users' => $company->max_users,
                'contact_email' => $company->contact_email,
                'contact_phone' => $company->contact_phone,
                'tax_code' => $company->tax_code,
                'address' => $company->address,
                'website' => $company->website,
                'representative_name' => $company->representative_name,
                'representative_title' => $company->representative_title,
                'expires_at' => $company->expires_at?->toDateString(),
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'tax_code' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'representative_name' => ['nullable', 'string', 'max:160'],
            'representative_title' => ['nullable', 'string', 'max:160'],
        ]);

        $company->update($data);

        return back()->with('success', 'Đã cập nhật thông tin đơn vị.');
    }
}
