<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Operations\OrderInteractionLockService;
use App\Services\Operations\SalesVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderInteractionLockController extends Controller
{
    public function __construct(
        private readonly OrderInteractionLockService $locks,
        private readonly SalesVisibilityScope $visibility,
    ) {}

    public function store(Request $request, Order $order): JsonResponse
    {
        $this->assertCanSee($request, $order);
        $data = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->locks->acquire($order, $request->user(), $data['action'] ?? 'dialog');

        return response()->json($result);
    }

    public function heartbeat(Request $request, Order $order): JsonResponse
    {
        $this->assertCanSee($request, $order);
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        return response()->json($this->locks->heartbeat($order, $request->user(), $data['token']));
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $this->assertCanSee($request, $order);
        $token = $request->input('token') ?? $request->header('X-Interaction-Lock-Token');
        $this->locks->release($order, $request->user(), is_string($token) ? $token : null);

        return response()->json(['released' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        return response()->json([
            'locks' => $this->locks->holdersForOrders($data['ids']),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->assertCanSee($request, $order);

        return response()->json([
            'holder' => $this->locks->getHolder($order),
        ]);
    }

    private function assertCanSee(Request $request, Order $order): void
    {
        $actor = $request->user();
        if ($actor?->isSales() && ! $this->visibility->canOperateOrder($actor, $order)) {
            abort(403, 'Bạn không có quyền thao tác đơn này.');
        }
    }
}
