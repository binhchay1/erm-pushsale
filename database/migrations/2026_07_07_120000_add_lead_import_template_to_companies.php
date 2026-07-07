<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // File mẫu import lead do admin doanh nghiệp upload (đường dẫn trên disk local) + tên gốc để hiển thị.
            $table->string('lead_import_template_path')->nullable()->after('contact_phone');
            $table->string('lead_import_template_name')->nullable()->after('lead_import_template_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['lead_import_template_path', 'lead_import_template_name']);
        });
    }
};
