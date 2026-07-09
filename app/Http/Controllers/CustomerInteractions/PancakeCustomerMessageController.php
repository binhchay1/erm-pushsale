<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\PermissionArea;
use App\Events\CustomerInteractions\PancakeCustomerMessageCreated;
use App\Enums\PermissionLevel;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Pancake\PancakeCustomerChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use App\Models\PancakeCustomerMessage;

class PancakeCustomerMessageController extends Controller
{
    public function __construct(
        protected PancakeCustomerChatService $chat,
    ) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $result = $this->chat->messagesForOrder($order, $request->user());

        return response()->json([
            ...$result,
            'canWrite' => $this->canWrite($request->user()) && (bool) $result['connected'],
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'orderCode' => $order->order_code,
            ],
        ]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->canWrite($user), 403, __('operations.customer_interactions.pancake_read_only'));

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $content = trim($validated['message']);
        if ($content === '') {
            throw ValidationException::withMessages([
                'message' => __('operations.customer_interactions.message_required'),
            ]);
        }

        try {
            $message = $this->chat->send($order, $user, $content);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (isset($message['id'])) {
            $model = PancakeCustomerMessage::query()->find($message['id']);
            if ($model) {
                event(new PancakeCustomerMessageCreated($model));
            }
        }

        return response()->json([
            'message' => $message,
        ], 201);
    }

    private function canWrite(?User $user): bool
    {
        return $user !== null
            && $user->allows(PermissionArea::CustomerChat, PermissionLevel::Full);
    }
}
