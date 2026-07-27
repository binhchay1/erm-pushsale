<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

final class ClearAllBusinessDataKeepAccountsCommand extends Command
{
    private const SUPERADMIN_EMAIL = 'superadmin@saleops.local';

    protected $signature = 'data:clear-all-keep-accounts {--force : Skip confirmation prompt}';

    protected $description = 'Xoa toan bo du lieu, chi giu tai khoan superadmin va don vi noi bo';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $ok = $this->confirm(
                'Xoa TOAN BO du lieu nghiep vu va TAT CA tai khoan demo, chi giu superadmin@saleops.local?',
                false,
            );
            if (! $ok) {
                $this->warn('Da huy.');

                return self::SUCCESS;
            }
        }

        $tables = collect(Schema::getTables())
            ->map(function ($row): string {
                if (is_array($row)) {
                    return (string) ($row['name'] ?? $row['table_name'] ?? reset($row));
                }

                return (string) $row;
            })
            ->filter(fn (string $name): bool => $name !== '' && $name !== 'migrations')
            ->values();

        Schema::disableForeignKeyConstraints();
        $cleared = 0;

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $cleared++;
            $this->line("Truncated: {$table}");
        }

        $company = CompanyProvisioningService::internalCompany();
        $company->forceFill([
            'name' => TenantEmail::internalName(),
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'internal',
            'contact_email' => self::SUPERADMIN_EMAIL,
            'email_login_host' => TenantEmail::domain(),
        ])->save();

        $superadmin = User::query()->create([
            'company_id' => $company->id,
            'name' => 'Super Admin',
            'email' => self::SUPERADMIN_EMAIL,
            'password' => Hash::make(TenantEmail::defaultPassword()),
            'role' => UserRole::Admin,
            'is_owner' => true,
            'is_platform_admin' => true,
            'is_active' => true,
            'job_title' => 'Super Admin',
        ]);
        $superadmin->ensurePreferences();

        $company->update(['owner_user_id' => $superadmin->id]);

        Schema::enableForeignKeyConstraints();

        $this->info("Da xoa {$cleared} bang va khoi phuc superadmin + don vi noi bo.");

        return self::SUCCESS;
    }
}
