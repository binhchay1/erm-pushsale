<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $items = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($tab === 'unread', fn ($q) => $q->unread())
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (UserNotification $n) => $this->toArray($n))
            ->values();

        return Inertia::render('Notifications/Index', [
            'tab' => $tab,
            'items' => $items,
            'unreadCount' => $this->unreadCount($request),
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
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }

    private function unreadCount(Request $request): int
    {
        return UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();
    }

    /** @return array<string, mixed> */
    private function toArray(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'url' => $n->url,
            'is_read' => (bool) $n->read_at,
            'created_at' => $n->created_at?->diffForHumans(),
        ];
    }
}
