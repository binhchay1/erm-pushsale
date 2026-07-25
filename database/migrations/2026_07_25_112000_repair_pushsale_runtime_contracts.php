<?php

use App\Support\RuntimeSchemaContract;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(RuntimeSchemaContract::class)->ensure();
    }

    public function down(): void
    {
        // Non-destructive compatibility repair; do not remove columns on rollback.
    }
};
