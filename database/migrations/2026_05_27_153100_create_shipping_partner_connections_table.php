<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_partner_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_partner_connections');
    }
};
