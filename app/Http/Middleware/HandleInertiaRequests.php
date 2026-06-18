<?php

namespace App\Http\Middleware;

use App\Models\UserNotification;
use App\Services\LabelRegistry;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $preferences = null;
        $notifications = [];
        $notificationsUnread = 0;

        if ($user) {
            $preferences = $user->ensurePreferences()->toFrontendArray();
            $user->loadMissing(['team:id,name', 'manager:id,name']);

            $recent = UserNotification::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(5)
                ->get();

            $notifications = $recent->map(fn (UserNotification $n) => $n->toFrontendArray())->all();

            $notificationsUnread = UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();
        }

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'locales' => config('saleops.locales'),
            'labels' => app(LabelRegistry::class)->all(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->roleLabel(),
                    'org_level' => $user->org_level?->value,
                    'team' => $user->team?->name,
                    'manager' => $user->manager?->name,
                    'is_team_leader' => (bool) $user->is_team_leader,
                    'avatar_url' => $user->avatarUrl(),
                    'initials' => $user->initials(),
                    'org_level_label' => $user->orgLevelLabel(),
                ] : null,
            ],
            'navigation' => app(NavigationService::class)->forUser($user),
            'preferences' => $preferences,
            'brand' => array_merge(config('saleops.brand'), [
                'tagline' => __('brand.tagline'),
            ]),
            'themes' => config('saleops.themes'),
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => env('REVERB_HOST', 'localhost'),
                'port' => (int) env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => $notifications,
            'notificationsUnread' => $notificationsUnread,
        ];
    }
}
