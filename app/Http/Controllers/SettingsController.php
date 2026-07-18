<?php

namespace App\Http\Controllers;

use App\Services\Settings\FeatureSettingsService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly FeatureSettingsService $featureSettings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Index', [
            'definition' => $this->featureSettings->definition(),
            'values' => $this->featureSettings->values(),
            'activityUrl' => route('admin.activity-logs.index', absolute: false),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $result = $this->featureSettings->save($validated['settings']);

        ActivityLogger::log(
            'settings.feature_settings_updated',
            properties: [
                'changed_count' => count($result['changed']),
                'changed_keys' => array_keys($result['changed']),
            ],
            subjectLabel: 'Cấu hình chức năng',
        );

        return back()->with('success', __('messages.saved'));
    }
}
