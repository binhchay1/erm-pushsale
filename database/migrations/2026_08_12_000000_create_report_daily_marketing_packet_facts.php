<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_daily_marketing_packet_facts')) {
            Schema::create('report_daily_marketing_packet_facts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->date('metric_date');
                $table->unsignedBigInteger('marketing_source_id')->default(0);
                $table->unsignedBigInteger('parent_marketing_source_id')->default(0);
                $table->unsignedBigInteger('landing_connection_id')->default(0);
                $table->unsignedBigInteger('landing_connection_source_id')->default(0);
                $table->unsignedBigInteger('marketer_user_id')->default(0);
                $table->unsignedBigInteger('team_id')->default(0);
                $table->string('ad_channel', 120)->default('');
                $table->string('source_type', 40)->default('');
                $table->string('channel', 120)->default('');
                $table->string('utm_source', 180)->default('');
                $table->string('utm_campaign', 220)->default('');
                $table->string('utm_medium', 180)->default('');
                $table->string('utm_term', 220)->default('');
                $table->string('utm_content', 220)->default('');
                $table->string('status', 30)->default('');
                $table->char('dimension_hash', 64);
                $table->unsignedBigInteger('packet_count')->default(0);
                $table->unsignedBigInteger('primary_packet_count')->default(0);
                $table->unsignedBigInteger('upsale_packet_count')->default(0);
                $table->unsignedBigInteger('processed_count')->default(0);
                $table->unsignedBigInteger('rejected_count')->default(0);
                $table->unsignedBigInteger('failed_count')->default(0);
                $table->unsignedBigInteger('no_phone_count')->default(0);
                $table->unsignedBigInteger('phone_count')->default(0);
                $table->unsignedBigInteger('unique_phone_count')->default(0);
                $table->unsignedBigInteger('duplicate_packet_count')->default(0);
                $table->timestamp('first_received_at')->nullable();
                $table->timestamp('last_received_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'metric_date', 'dimension_hash'], 'report_daily_marketing_packets_unique');
                $table->index(['company_id', 'metric_date', 'marketing_source_id'], 'report_mkt_packets_company_day_source_idx');
                $table->index(['company_id', 'metric_date', 'marketer_user_id'], 'report_mkt_packets_company_day_marketer_idx');
                $table->index(['company_id', 'metric_date', 'landing_connection_id'], 'report_mkt_packets_company_day_landing_idx');
                $table->index(['company_id', 'metric_date', 'utm_source'], 'report_mkt_packets_company_day_utm_source_idx');
            });
        }

        Schema::table('report_daily_closures', function (Blueprint $table): void {
            if (! Schema::hasColumn('report_daily_closures', 'marketing_packet_rows')) {
                $table->unsignedBigInteger('marketing_packet_rows')->default(0)->after('lead_rows');
            }
        });

        if (Schema::hasTable('inbound_events') && ! $this->indexExists('inbound_events', 'inbound_events_source_channel_created_idx')) {
            Schema::table('inbound_events', function (Blueprint $table): void {
                $table->index(['source', 'channel', 'created_at'], 'inbound_events_source_channel_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inbound_events') && $this->indexExists('inbound_events', 'inbound_events_source_channel_created_idx')) {
            Schema::table('inbound_events', function (Blueprint $table): void {
                $table->dropIndex('inbound_events_source_channel_created_idx');
            });
        }

        Schema::table('report_daily_closures', function (Blueprint $table): void {
            if (Schema::hasColumn('report_daily_closures', 'marketing_packet_rows')) {
                $table->dropColumn('marketing_packet_rows');
            }
        });

        Schema::dropIfExists('report_daily_marketing_packet_facts');
    }

    private function indexExists(string $table, string $name): bool
    {
        if (! method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            return false;
        }

        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
