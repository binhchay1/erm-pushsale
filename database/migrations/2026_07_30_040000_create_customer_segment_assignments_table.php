<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_segment_assignments')) {
            return;
        }

        Schema::create('customer_segment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('phone_key', 32)->index();
            $table->unsignedInteger('segment_id')->index();
            $table->string('segment_name', 120);
            $table->unsignedBigInteger('successful_order_value')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'phone_key'], 'customer_segment_assignments_company_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segment_assignments');
    }
};
