<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Pushsale\PartnerConnection;
use App\Models\User;
use App\Services\Marketing\PartnerConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class PartnerConnectionController extends Controller
{
    public function __construct(
        private readonly PartnerConnectionService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request->user());

        $partner = (string) $request->input('partner', $this->service->defaultProviderSlug());
        if ($this->service->provider($partner) === null) {
            $partner = $this->service->defaultProviderSlug();
        }

        $filters = [
            'search' => (string) $request->input('search', ''),
            'partner' => $partner,
        ];

        $result = $this->service->listConnections(
            $partner,
            $filters,
            max(10, min(100, $request->integer('per_page', 20))),
        );

        return Inertia::render('Admin/Marketing/PartnerConnections', [
            'providers' => $this->service->providers(),
            'selectedPartner' => $partner,
            'connections' => [
                'data' => $result['data'],
                'meta' => $result['meta'],
            ],
            'filters' => $filters,
            'marketers' => User::query()
                ->whereIn('role', [UserRole::Marketing, UserRole::Admin])
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'products' => Product::query()
                ->where('is_active', true)
                ->where('available_marketing', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
            'canManage' => $this->canManage($request->user()),
            'routeUrl' => '/admin/marketing/partner-connections',
            'activeMenuCode' => '2.6.3',
        ]);
    }

    public function toggleProvider(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request->user());

        $data = $request->validate([
            'partner' => ['required', 'string', Rule::in(array_keys(config('partner_providers', [])))],
            'is_active' => ['required', 'boolean'],
        ]);

        $provider = $this->service->setProviderActive($data['partner'], (bool) $data['is_active']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $provider['is_active']
                    ? 'Đã bật kết nối đối tác '.$provider['name'].'.'
                    : 'Đã tắt kết nối đối tác '.$provider['name'].'.',
                'provider' => $provider,
            ]);
        }

        return back()->with('success', $provider['is_active']
            ? 'Đã bật kết nối đối tác '.$provider['name'].'.'
            : 'Đã tắt kết nối đối tác '.$provider['name'].'.');
    }

    public function eligibleSources(Request $request): JsonResponse
    {
        $this->authorizeView($request->user());

        $data = $request->validate([
            'partner' => ['required', 'string', Rule::in(array_keys(config('partner_providers', [])))],
            'search' => ['nullable', 'string', 'max:255'],
            'marketer_user_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'connection_type' => ['nullable', 'string', Rule::in(['facebook', 'landing', 'website', ''])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $result = $this->service->eligibleLandings(
            $data['partner'],
            [
                'search' => (string) ($data['search'] ?? ''),
                'marketer_user_id' => (string) ($data['marketer_user_id'] ?? ''),
                'product_id' => (string) ($data['product_id'] ?? ''),
                'connection_type' => (string) ($data['connection_type'] ?? ''),
                'page' => (int) ($data['page'] ?? 1),
            ],
            (int) ($data['per_page'] ?? 10),
        );

        return response()->json($result);
    }

    public function attachSources(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request->user());

        $data = $request->validate([
            'partner' => ['required', 'string', Rule::in(array_keys(config('partner_providers', [])))],
            'landing_connection_ids' => ['required', 'array', 'min:1'],
            'landing_connection_ids.*' => ['integer', 'distinct'],
        ]);

        $created = $this->service->attachLandingConnections(
            $data['partner'],
            $data['landing_connection_ids'],
            $request->user(),
        );

        $message = 'Đã gắn '.count($created).' nguồn dữ liệu vào đối tác.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'count' => count($created)]);
        }

        return redirect()
            ->to('/admin/marketing/partner-connections?partner='.urlencode($data['partner']))
            ->with('success', $message);
    }

    public function updateFlags(Request $request, PartnerConnection $record): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request->user());

        $data = $request->validate([
            'manual_import' => ['sometimes', 'boolean'],
            'is_approved' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->service->updateFlags($record, $data, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đã cập nhật trạng thái kết nối.',
                'connection' => $this->service->serializeConnection($updated),
            ]);
        }

        return back()->with('success', 'Đã cập nhật trạng thái kết nối.');
    }

    public function destroy(Request $request, PartnerConnection $record): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request->user());
        $this->service->destroy($record);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã gỡ nguồn khỏi đối tác.']);
        }

        return back()->with('success', 'Đã gỡ nguồn khỏi đối tác.');
    }

    private function authorizeView(?User $user): void
    {
        abort_unless($user && (
            $user->isAdmin()
            || $user->role === UserRole::Marketing
            || $user->hasPermission(PermissionArea::Marketing, PermissionLevel::View)
        ), 403);
    }

    private function authorizeManage(?User $user): void
    {
        abort_unless($this->canManage($user), 403);
    }

    private function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission(PermissionArea::Marketing, PermissionLevel::Full);
    }
}
