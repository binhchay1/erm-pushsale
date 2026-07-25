<?php

use App\Support\RuntimeSchemaContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        app(RuntimeSchemaContract::class)->ensure();
    }

    public function down(): void
    {
        // Deliberately non-destructive. These columns are now part of the runtime
        // business contract and are required by seeders/audits/pages.
    }
};
