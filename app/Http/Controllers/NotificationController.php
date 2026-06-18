<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Repositories\NotificationRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationRepository $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $items = $this->notifications
            ->latestForUser($request->user()->id, unreadOnly: $tab === 'unread')
            ->map(fn (UserNotification $n) => $this->toArray($n))
            ->values();

        return Inertia::render('Notifications/Index', [
            'tab' => $tab,
            'items' => $items,
            'unreadCount' => $this->notifications->unreadCount($request->user()->id),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->user()->id);

        return back()->with('success', __('messages.notifications_read_all'));
    }

    /** @return array<string, mixed> */
    private function toArray(UserNotification $n): array
    {
        return $n->toFrontendArray();
    }
}
