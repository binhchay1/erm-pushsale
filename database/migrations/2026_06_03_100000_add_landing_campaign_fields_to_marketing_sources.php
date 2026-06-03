<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->unique()->after('utm_campaign');
            $table->foreignId('created_by_user_id')->nullable()->after('marketer_user_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_approved')->default(false)->after('is_active');
        });

        DB::table('marketing_sources')
            ->whereNull('parent_id')
            ->whereNull('webhook_token')
            ->orderBy('id')
            ->each(function (object $row) {
                DB::table('marketing_sources')->where('id', $row->id)->update([
                    'webhook_token' => Str::lower(Str::random(32)),
                    'is_approved' => (bool) ($row->is_active ?? true),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('marketing_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['webhook_token', 'is_approved']);
        });
    }
};
