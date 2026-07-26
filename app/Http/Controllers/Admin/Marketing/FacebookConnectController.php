<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LandingConnection;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\Pushsale\FacebookPostMapping;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class FacebookConnectController extends Controller
{
    public function connect(Request $request): Response
    {
        return Inertia::render('Pushsale/Pages/Marketing/FacebookConnectPage', [
            'activeMenuCode' => '2.5.2',
            'syncUrl' => '/admin/marketing/facebook/connect/sync',
            'postsUrl' => '/admin/marketing/facebook/posts',
            'pages' => $this->fanpages(),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('facebook_page_mappings')) {
            return back()->with('error', __('facebook.connection.table_missing'));
        }

        $user = $request->user();

        FacebookPageMapping::query()->updateOrCreate(
            ['page_id' => 'demo_page_'.$user->getKey()],
            [
                'page_name' => 'Demo Fanpage '.$user->name,
                'creator_name' => $user->name,
                'marketer_user_id' => $user->getKey(),
                'is_active' => true,
                'metadata' => [
                    'source' => 'manual_demo_sync',
                    'note' => 'This row keeps the Facebook sync page usable before a real Meta OAuth app is connected.',
                ],
                'created_by_user_id' => $user->getKey(),
                'updated_by_user_id' => $user->getKey(),
            ],
        );

        return back()->with('success', __('facebook.connection.synced'));
    }

    public function posts(Request $request): Response
    {
        $filters = [
            'page_id' => $request->string('page_id')->value(),
            'attached' => $request->string('attached')->value(),
            'search' => $request->string('search')->value(),
            'per_page' => $request->integer('per_page', 20),
        ];

        $perPage = max(10, min(100, (int) $filters['per_page']));
        $posts = Schema::hasTable('facebook_post_mappings')
            ? $this->postsQuery($request)
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (FacebookPostMapping $post): array => $this->serializePost($post))
            : new LengthAwarePaginator([], 0, $perPage, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

        return Inertia::render('Pushsale/Pages/Marketing/FacebookPostsPage', [
            'activeMenuCode' => '2.5.3',
            'filters' => $filters,
            'posts' => $posts,
            'pageOptions' => $this->fanpages(),
            'sourceOptions' => $this->landingSources(),
            'routeUrl' => '/admin/marketing/facebook/posts',
            'syncUrl' => '/admin/marketing/facebook/posts/sync',
            'recordsUrl' => '/admin/marketing/facebook/posts',
        ]);
    }

    public function syncPosts(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('facebook_post_mappings') || ! Schema::hasTable('facebook_page_mappings')) {
            return back()->with('error', __('facebook.connection.table_missing'));
        }

        $user = $request->user();
        $page = FacebookPageMapping::query()->first();

        if (! $page) {
            $page = FacebookPageMapping::query()->create([
                'page_id' => 'demo_page_'.$user->getKey(),
                'page_name' => 'Demo Fanpage '.$user->name,
                'creator_name' => $user->name,
                'marketer_user_id' => $user->getKey(),
                'is_active' => true,
                'metadata' => ['source' => 'manual_demo_sync'],
                'created_by_user_id' => $user->getKey(),
                'updated_by_user_id' => $user->getKey(),
            ]);
        }

        foreach ([1, 2, 3] as $index) {
            FacebookPostMapping::query()->updateOrCreate(
                ['post_id' => $page->page_id.'_post_'.$index],
                [
                    'facebook_page_mapping_id' => $page->getKey(),
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name,
                    'content' => match ($index) {
                        1 => 'Bài viết tư vấn sản phẩm đang chạy quảng cáo.',
                        2 => 'Bài viết remarketing khách hàng cũ.',
                        default => 'Bài viết test form lead Facebook.',
                    },
                    'posted_at' => now()->subDays($index),
                    'is_used' => false,
                    'status' => 'active',
                    'metadata' => ['source' => 'manual_demo_sync'],
                    'created_by_user_id' => $user->getKey(),
                    'updated_by_user_id' => $user->getKey(),
                ],
            );
        }

        return back()->with('success', __('facebook.posts.synced'));
    }

    public function updatePost(Request $request, FacebookPostMapping $post): RedirectResponse
    {
        $validated = $request->validate([
            'is_used' => ['nullable', 'boolean'],
            'landing_connection_id' => ['nullable', 'integer', 'exists:landing_connections,id'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $post->fill([
            'is_used' => (bool) ($validated['is_used'] ?? $post->is_used),
            'landing_connection_id' => $validated['landing_connection_id'] ?? $post->landing_connection_id,
            'status' => $validated['status'] ?? $post->status,
            'updated_by_user_id' => $request->user()?->getKey(),
        ])->save();

        return back()->with('success', __('facebook.posts.saved'));
    }

    /** @return list<array<string, mixed>> */
    private function fanpages(): array
    {
        if (! Schema::hasTable('facebook_page_mappings')) {
            return [];
        }

        return FacebookPageMapping::query()
            ->with('marketer:id,name,email')
            ->latest('id')
            ->get()
            ->map(fn (FacebookPageMapping $page): array => [
                'id' => $page->getKey(),
                'value' => $page->page_id,
                'label' => $page->page_name,
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'marketer_name' => $page->marketer?->name,
                'marketer_email' => $page->marketer?->email,
                'is_active' => (bool) $page->is_active,
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function landingSources(): array
    {
        if (! Schema::hasTable('landing_connections')) {
            return [];
        }

        return LandingConnection::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'connection_type'])
            ->map(fn (LandingConnection $connection): array => [
                'value' => (string) $connection->getKey(),
                'label' => $connection->name,
                'subLabel' => $connection->connection_type,
            ])
            ->values()
            ->all();
    }

    private function postsQuery(Request $request)
    {
        if (! Schema::hasTable('facebook_post_mappings')) {
            return FacebookPostMapping::query()->whereRaw('1 = 0');
        }

        return FacebookPostMapping::query()
            ->with(['fanpage:id,page_id,page_name', 'landingConnection:id,name'])
            ->when($request->filled('page_id'), fn ($query) => $query->where('page_id', $request->string('page_id')->value()))
            ->when($request->filled('attached'), function ($query) use ($request): void {
                $attached = $request->string('attached')->value();
                if ($attached === '1') {
                    $query->whereNotNull('landing_connection_id');
                } elseif ($attached === '0') {
                    $query->whereNull('landing_connection_id');
                }
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $keyword = trim($request->string('search')->value());
                $query->where(function ($query) use ($keyword): void {
                    $query->where('page_id', 'like', "%{$keyword}%")
                        ->orWhere('post_id', 'like', "%{$keyword}%")
                        ->orWhere('content', 'like', "%{$keyword}%");
                });
            })
            ->latest('posted_at')
            ->latest('id');
    }

    private function serializePost(FacebookPostMapping $post): array
    {
        return [
            'id' => $post->getKey(),
            'page_id' => $post->page_id,
            'page_name' => $post->page_name ?: $post->fanpage?->page_name,
            'post_id' => $post->post_id,
            'content' => $post->content,
            'posted_at' => $post->posted_at?->format('d/m/Y H:i'),
            'is_used' => (bool) $post->is_used,
            'landing_connection_id' => $post->landing_connection_id ? (string) $post->landing_connection_id : '',
            'landing_connection_name' => $post->landingConnection?->name,
            'status' => $post->status,
        ];
    }
}
