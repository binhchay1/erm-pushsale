<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SaleBulkCloseController extends Controller
{
    public function store(Request $request, OrderClosingService $closing): RedirectResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'distinct'],
            'confirm_insufficient_stock' => ['nullable', 'boolean'],
        ]);

        $ids = collect($validated['order_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $orders = Order::query()
            ->whereIn('id', $ids)
            ->with(['items', 'warehouse'])
            ->get()
            ->keyBy('id');

        $closed = 0;
        $failed = [];

        foreach ($ids as $id) {
            $order = $orders->get($id);
            if (! $order) {
                $failed[] = "#{$id}: không tìm thấy hoặc không thuộc đơn vị hiện tại";
                continue;
            }

            if ($order->closed_at) {
                $failed[] = ($order->order_code ?: "#{$id}").': đơn đã chốt';
                continue;
            }

            try {
                $closing->close($order, $request->user(), [
                    'confirm_insufficient_stock' => (bool) ($validated['confirm_insufficient_stock'] ?? false),
                ]);
                $closed++;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?: $exception->getMessage();
                $failed[] = "#{$id}: {$message}";
            } catch (\Throwable $exception) {
                report($exception);
                $failed[] = "#{$id}: không thể chốt đơn";
            }
        }

        if ($closed === 0) {
            throw ValidationException::withMessages([
                'order_ids' => implode('; ', array_slice($failed, 0, 10)) ?: 'Không có đơn nào được chốt.',
            ]);
        }

        $message = "Đã chốt {$closed} đơn.";
        if ($failed !== []) {
            $message .= ' Không xử lý được '.count($failed).' đơn: '.implode('; ', array_slice($failed, 0, 5));
        }

        return back()->with($failed === [] ? 'success' : 'warning', $message);
    }
}
