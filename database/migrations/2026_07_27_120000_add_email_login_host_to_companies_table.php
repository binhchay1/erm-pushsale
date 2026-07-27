<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'email_login_host')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('email_login_host', 120)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('companies', 'email_login_host')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('email_login_host');
        });
    }
};
