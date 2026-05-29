<?php

namespace App\Http\Middleware;

use App\Models\UserNotification;
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

            $notifications = $recent->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'url' => $n->url,
                'is_read' => (bool) $n->read_at,
                'created_at' => $n->created_at?->diffForHumans(),
            ])->all();

            $notificationsUnread = UserNotification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->roleLabel(),
                    'team' => $user->team?->name,
                    'manager' => $user->manager?->name,
                    'is_team_leader' => (bool) $user->is_team_leader,
                ] : null,
            ],
            'preferences' => $preferences,
            'brand' => config('saleops.brand'),
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
