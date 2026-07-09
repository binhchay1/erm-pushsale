<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Events\CustomerInteractions\CustomerInternalMessageCreated;
use App\Http\Controllers\Controller;
use App\Models\CustomerInternalMessage;
use App\Jobs\CustomerMessages\NotifyCustomerInternalMessageJob;
use App\Models\Order;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Models\User;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\CustomerInteractions\CustomerConversationChannel;
use App\Services\CustomerInteractions\CustomerInternalMessagePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerInternalMessageController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $phone = CustomerIdentity::phoneKey($order);

        $messages = CustomerInternalMessage::query()
            ->with(['author:id,name,role,org_level', 'order:id,order_code'])
            ->where('customer_phone', $phone)
            ->latest('created_at')
            ->latest('id')
            ->limit(300)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->effectiveShippingAddress(),
                'note' => $order->customer_note,
                'orderCode' => $order->order_code,
            ],
            'messages' => CustomerInternalMessagePresenter::collection($messages, $request->user()),
            'canWrite' => $this->canWrite($request->user()),
            'realtime' => [
                'channel' => CustomerConversationChannel::internalForOrder($order),
                'event' => '.customer.internal-message.created',
                'pollMs' => 15000,
            ],
        ]);
    }

    public function store(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->canWrite($user), 403, __('operations.customer_interactions.read_only'));

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $content = trim($validated['message']);

        if ($content === '') {
            throw ValidationException::withMessages([
                'message' => __('operations.customer_interactions.message_required'),
            ]);
        }

        $message = CustomerInternalMessage::query()->create([
            'company_id' => $order->company_id ?? $user->company_id,
            'order_id' => $order->id,
            'author_user_id' => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role?->value,
            'customer_phone' => CustomerIdentity::phoneKey($order),
            'message' => $content,
        ]);

        NotifyCustomerInternalMessageJob::dispatch($message->id);
        event(new CustomerInternalMessageCreated($message));

        $message->load(['author:id,name,role,org_level', 'order:id,order_code']);

        return response()->json([
            'message' => CustomerInternalMessagePresenter::toArray($message, $user),
        ], 201);
    }

    private function canWrite(?User $user): bool
    {
        // Dùng chung cơ chế phân quyền linh động của hệ thống:
        // customers:view = chỉ xem, customers:full = được gửi tin nhắn.
        return $user !== null
            && $user->allows(PermissionArea::Customers, PermissionLevel::Full);
    }
}
