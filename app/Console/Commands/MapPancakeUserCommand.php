<?php

namespace App\Console\Commands;

use App\Enums\IntegrationPlatform;
use App\Models\Company;
use App\Models\IntegrationConnection;
use App\Models\PancakeUserMapping;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Pancake\PancakeAssignmentResolver;
use App\Support\TenantManager;
use Illuminate\Console\Command;

class MapPancakeUserCommand extends Command
{
    protected $signature = 'pancake:map-user
        {--company-id= : ID doanh nghiệp}
        {--pancake-user-id= : ID nhân sự/agent bên Pancake}
        {--pancake-user-email= : Email nhân sự/agent bên Pancake}
        {--pancake-user-name= : Tên hiển thị bên Pancake}
        {--sale-email= : Email sale nội bộ SaleOps}
        {--sale-id= : ID sale nội bộ SaleOps}
        {--shop-id= : Giới hạn mapping theo shop Pancake}
        {--page-id= : Giới hạn mapping theo page Pancake}
        {--inactive : Tạo/cập nhật mapping ở trạng thái tắt}';

    protected $description = 'Map nhân sự/agent Pancake sang sale nội bộ để webhook/polling chia đúng sale.';

    public function handle(PancakeAssignmentResolver $resolver): int
    {
        $companyId = (int) ($this->option('company-id') ?: Company::query()->orderBy('id')->value('id'));
        if (! $companyId) {
            $this->error('Không tìm thấy company.');

            return self::FAILURE;
        }

        return app(TenantManager::class)->forCompany($companyId, function () use ($resolver, $companyId): int {
            $sale = $this->resolveSale();
            if (! $sale?->isSales()) {
                $this->error('Sale nội bộ không hợp lệ hoặc không thuộc role sales.');

                return self::FAILURE;
            }

            $pancakeKey = $this->option('pancake-user-id')
                ?: $this->option('pancake-user-email')
                ?: $this->option('pancake-user-name');

            if (! is_string($pancakeKey) || trim($pancakeKey) === '') {
                $this->error('Cần truyền --pancake-user-id hoặc --pancake-user-email hoặc --pancake-user-name.');

                return self::FAILURE;
            }

            $connection = IntegrationConnection::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('platform', IntegrationPlatform::Pancake->value)
                ->first();

            if (! $connection) {
                $connection = new IntegrationConnection;
                $connection->forceFill([
                    'company_id' => $companyId,
                    'platform' => IntegrationPlatform::Pancake->value,
                    'is_enabled' => false,
                    'credentials' => [],
                ])->save();
            }

            $mapping = PancakeUserMapping::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'integration_connection_id' => $connection->id,
                    'shop_id' => $this->option('shop-id') ?: null,
                    'page_id' => $this->option('page-id') ?: null,
                    'pancake_user_key' => $resolver->normalizeUserKey($pancakeKey),
                ],
                [
                    'pancake_user_id' => $this->option('pancake-user-id') ?: null,
                    'pancake_user_email' => $this->option('pancake-user-email') ?: null,
                    'pancake_user_name' => $this->option('pancake-user-name') ?: null,
                    'internal_user_id' => $sale->id,
                    'is_active' => ! (bool) $this->option('inactive'),
                    'metadata' => [
                        'created_from' => 'artisan',
                        'created_by' => 'pancake:map-user',
                    ],
                ],
            );

            $this->info(sprintf(
                'Đã map Pancake user [%s] → sale [%s <%s>] (mapping #%d).',
                $mapping->pancake_user_key,
                $sale->name,
                $sale->email,
                $mapping->id,
            ));

            return self::SUCCESS;
        });
    }

    private function resolveSale(): ?User
    {
        if ($this->option('sale-id')) {
            return User::query()->find((int) $this->option('sale-id'));
        }

        if ($this->option('sale-email')) {
            return User::query()->where('email', (string) $this->option('sale-email'))->first();
        }

        return null;
    }
}
