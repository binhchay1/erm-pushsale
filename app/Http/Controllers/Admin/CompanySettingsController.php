<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingsController extends Controller
{
    /** Admin doanh nghiệp upload file mẫu import lead cho công ty của mình. */
    public function updateLeadTemplate(Request $request): RedirectResponse
    {
        $company = $request->user()?->company;
        abort_if(! $company, 404);

        $data = $request->validate([
            'lead_import_template' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xls,xlsx'],
        ]);

        $this->deleteTemplate($company);

        $file = $data['lead_import_template'];
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = $file->storeAs('lead-templates', 'company-'.$company->id.'.'.$ext, 'local');

        $company->update([
            'lead_import_template_path' => $path,
            'lead_import_template_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('success', __('messages.company.template_uploaded'));
    }

    public function destroyLeadTemplate(Request $request): RedirectResponse
    {
        $company = $request->user()?->company;
        abort_if(! $company, 404);

        $this->deleteTemplate($company);
        $company->update([
            'lead_import_template_path' => null,
            'lead_import_template_name' => null,
        ]);

        return back()->with('success', __('messages.company.template_removed'));
    }

    private function deleteTemplate(Company $company): void
    {
        if ($company->lead_import_template_path
            && Storage::disk('local')->exists($company->lead_import_template_path)
        ) {
            Storage::disk('local')->delete($company->lead_import_template_path);
        }
    }
}
