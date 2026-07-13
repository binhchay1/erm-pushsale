<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Các bảng generic của bản giao diện thử nghiệm không đại diện cho nghiệp vụ
        // của từng màn hình. V5 dùng bảng/model chuyên biệt nên loại bỏ hoàn toàn.
        Schema::dropIfExists('pushsale_module_records');
        Schema::dropIfExists('legacy_module_records');
    }

    public function down(): void
    {
        // Không khôi phục bảng generic đã bị loại bỏ có chủ đích.
    }
};
