<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\LoginController;
use App\Models\UserPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $prefs = $user->preferences()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'theme' => UserPreference::THEME_DEFAULT,
                'appearance' => UserPreference::APPEARANCE_SYSTEM,
                'notifications' => UserPreference::defaultNotifications(),
            ]
        );

        return Inertia::render('Settings/Index', [
            'preferences' => $prefs->toFrontendArray(),
            'themes' => config('saleops.themes'),
            'settingsBackUrl' => LoginController::homeFor($user),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:'.implode(',', array_keys(config('saleops.themes')))],
            'appearance' => ['required', 'in:system,light,dark'],
            'notifications' => ['required', 'array'],
            'notifications.new_lead' => ['boolean'],
            'notifications.order_update' => ['boolean'],
            'notifications.reminder' => ['boolean'],
            'notifications.delivery_issue' => ['boolean'],
            'notifications.kpi_alert' => ['boolean'],
            'notifications.sound' => ['boolean'],
            'notifications.desktop' => ['boolean'],
            'notifications.email_digest' => ['boolean'],
        ]);

        $prefs = $request->user()->preferences()->firstOrCreate(['user_id' => $request->user()->id]);

        $prefs->update([
            'theme' => $validated['theme'],
            'appearance' => $validated['appearance'],
            'notifications' => $validated['notifications'],
        ]);

        return back()->with('success', 'Đã lưu cài đặt.');
    }
}
