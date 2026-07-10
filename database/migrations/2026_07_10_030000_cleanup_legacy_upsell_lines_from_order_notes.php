<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Bản cũ từng nối "[Upsale] <tên sản phẩm>" vào customer_note. Dữ
         * liệu sản phẩm đã nằm trong order_items/lead_ingestions nên các dòng
         * hệ thống này vừa trùng, vừa làm cột Tin nhắn bị hiểu sai.
         */
        DB::table('orders')
            ->where(function ($query): void {
                $query->where('customer_note', 'like', '%[Upsale]%')
                    ->orWhere('customer_note', 'like', '%[Upsell]%')
                    ->orWhere('customer_note', 'like', '%SP:%');
            })
            ->orderBy('id')
            ->chunkById(300, function ($orders): void {
                foreach ($orders as $order) {
                    $lines = preg_split('/\R/u', (string) $order->customer_note) ?: [];
                    $clean = collect($lines)
                        ->map(static fn (string $line): string => trim($line))
                        ->reject(static fn (string $line): bool =>
                            preg_match('/^(?:\[(?:upsale|upsell)\]|SP:)\s*/iu', $line) === 1
                        )
                        ->filter(static fn (string $line): bool => $line !== '')
                        ->implode(PHP_EOL);

                    DB::table('orders')->where('id', $order->id)->update([
                        'customer_note' => $clean !== '' ? $clean : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Không thể dựng lại note hệ thống một cách an toàn; dữ liệu sản phẩm
        // nguyên gốc vẫn còn đầy đủ trong order_items và lead_ingestions.
    }
};
