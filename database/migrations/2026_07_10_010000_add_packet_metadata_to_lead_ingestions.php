<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->string('packet_type', 24)->default('lead')->after('status');
            $table->boolean('counts_as_lead')->default(true)->after('packet_type');
            $table->foreignId('parent_ingestion_id')->nullable()->after('order_id')
                ->constrained('lead_ingestions')->nullOnDelete();
            $table->foreignId('related_order_id')->nullable()->after('parent_ingestion_id')
                ->constrained('orders')->nullOnDelete();
            $table->boolean('requires_review')->default(false)->after('related_order_id');
            $table->timestamp('reviewed_at')->nullable()->after('requires_review');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->string('review_resolution', 32)->nullable()->after('reviewed_by_user_id');
            $table->text('review_note')->nullable()->after('review_resolution');

            $table->index(['counts_as_lead', 'status', 'created_at'], 'lead_ingestions_countable_status_created_idx');
            $table->index(['packet_type', 'created_at'], 'lead_ingestions_packet_type_created_idx');
            $table->index(['requires_review', 'reviewed_at', 'created_at'], 'lead_ingestions_review_queue_idx');
            $table->index(['related_order_id', 'created_at'], 'lead_ingestions_related_order_created_idx');
        });

        $this->backfillLandingPackets();
    }

    public function down(): void
    {
        Schema::table('lead_ingestions', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropForeign(['related_order_id']);
            $table->dropForeign(['parent_ingestion_id']);

            $table->dropIndex('lead_ingestions_countable_status_created_idx');
            $table->dropIndex('lead_ingestions_packet_type_created_idx');
            $table->dropIndex('lead_ingestions_review_queue_idx');
            $table->dropIndex('lead_ingestions_related_order_created_idx');

            $table->dropColumn([
                'packet_type',
                'counts_as_lead',
                'parent_ingestion_id',
                'related_order_id',
                'requires_review',
                'reviewed_at',
                'reviewed_by_user_id',
                'review_resolution',
                'review_note',
            ]);
        });
    }

    /**
     * Chuẩn hóa dữ liệu Landing cũ mà không tải toàn bộ bảng vào RAM.
     *
     * - Chỉ packet đầu tiên không mang dấu hiệu upsell mới là lead chính.
     * - Mọi packet bổ sung cùng order không được tính thêm contact/lead.
     * - Late/orphan upsell được giữ nguyên payload và đưa vào hàng kiểm tra.
     */
    private function backfillLandingPackets(): void
    {
        /*
         * Mỗi order chỉ có tối đa một packet lead chính. Ưu tiên bản ghi đầu
         * tiên KHÔNG mang dấu hiệu upsell; không mặc định lấy row đầu tiên vì
         * dữ liệu legacy có thể nhận upsell trước base do queue chạy lệch thứ tự.
         */
        DB::table('lead_ingestions')
            ->where('platform', 'landing')
            ->whereNotNull('order_id')
            ->select('order_id')
            ->distinct()
            ->orderBy('order_id')
            ->chunkById(300, function ($orderRows): void {
                foreach ($orderRows as $orderRow) {
                    $orderId = (int) $orderRow->order_id;
                    $packets = DB::table('lead_ingestions')
                        ->where('platform', 'landing')
                        ->where('order_id', $orderId)
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->get();

                    $primary = $packets->first(
                        fn (object $packet): bool => ! $this->looksLikeUpsell($packet),
                    );
                    $primaryId = $primary ? (int) $primary->id : null;

                    foreach ($packets as $packet) {
                        $isPrimary = $primaryId !== null && (int) $packet->id === $primaryId;
                        $isUpsell = $this->looksLikeUpsell($packet);

                        DB::table('lead_ingestions')->where('id', $packet->id)->update([
                            'packet_type' => $isPrimary
                                ? 'lead'
                                : ($isUpsell ? 'upsell' : 'follow_up'),
                            'counts_as_lead' => $isPrimary,
                            'parent_ingestion_id' => $isPrimary ? null : $primaryId,
                        ]);
                    }
                }
            }, 'order_id', 'order_id');

        /*
         * Packet bổ sung chưa có order tuyệt đối không được tính thành lead.
         * Có conflict_order_id => late upsell của đơn cũ; không có => orphan
         * chờ xử lý tay. Bản ghi Failed giữ nguyên trạng thái để không biến lỗi
         * kỹ thuật thành công việc nghiệp vụ giả.
         */
        DB::table('lead_ingestions')
            ->where('platform', 'landing')
            ->whereNull('order_id')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->chunkById(300, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->payload, true);
                    if (! is_array($payload) || ! $this->looksLikeUpsell($row, $payload)) {
                        continue;
                    }

                    $relatedOrderId = filter_var(
                        $payload['conflict_order_id'] ?? null,
                        FILTER_VALIDATE_INT,
                    ) ?: null;

                    $parentId = null;
                    if ($relatedOrderId) {
                        $parentId = DB::table('lead_ingestions')
                            ->where('platform', 'landing')
                            ->where('order_id', $relatedOrderId)
                            ->where('counts_as_lead', true)
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->value('id');
                    }

                    $isFailed = (string) $row->status === 'failed';
                    DB::table('lead_ingestions')->where('id', $row->id)->update([
                        'status' => $isFailed ? 'failed' : 'needs_review',
                        'packet_type' => $relatedOrderId ? 'late_upsell' : 'orphan_upsell',
                        'counts_as_lead' => false,
                        'parent_ingestion_id' => $parentId,
                        'related_order_id' => $relatedOrderId,
                        'requires_review' => ! $isFailed,
                        'processed_at' => $row->processed_at ?? $row->created_at,
                    ]);
                }
            });
    }

    /** @param array<string, mixed>|null $payload */
    private function looksLikeUpsell(object $row, ?array $payload = null): bool
    {
        $externalId = strtolower((string) ($row->external_id ?? ''));
        if (str_ends_with($externalId, ':upsell') || str_ends_with($externalId, ':follow_up')) {
            return true;
        }

        $payload ??= json_decode((string) ($row->payload ?? ''), true);
        if (! is_array($payload)) {
            return false;
        }

        $flag = strtolower((string) ($payload['is_upsell'] ?? ''));
        if (in_array($flag, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (strtolower((string) ($payload['item_type'] ?? '')) === 'upsell') {
            return true;
        }

        foreach (array_keys($payload) as $key) {
            if (preg_match('/^(mua_them|upsell|addon)_/i', (string) $key)) {
                return true;
            }
        }

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (is_array($item) && strtolower((string) ($item['item_type'] ?? '')) === 'upsell') {
                return true;
            }
        }

        return false;
    }
};
