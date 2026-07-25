<?php

namespace App\Services\BusinessFlow;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\LandingConnection;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingPartnerConnection;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Audit nhanh cấu hình vận hành end-to-end: nhân sự → sản phẩm/gói → landing
 * → chia số → Pancake → kho/giao vận → báo cáo. Dịch vụ này không sửa dữ liệu;
 * nó chỉ chỉ ra các điểm làm gãy luồng tự động để admin xử lý trước khi chạy thật.
 */
class BusinessFlowContractService
{
    /**
     * @return array{ok: bool, companies: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function audit(?int $companyId = null): array
    {
        $companies = Company::query()
            ->when($companyId, fn ($query) => $query->whereKey($companyId))
            ->orderBy('id')
            ->get();

        $rows = $companies->map(fn (Company $company): array => $this->auditCompany($company))->values();
        $issues = $rows->sum(fn (array $row): int => count($row['issues']));
        $warnings = $rows->sum(fn (array $row): int => count($row['warnings']));

        return [
            'ok' => $issues === 0,
            'companies' => $rows->all(),
            'totals' => [
                'companies' => $rows->count(),
                'issues' => $issues,
                'warnings' => $warnings,
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function auditCompany(Company $company): array
    {
        $issues = collect();
        $warnings = collect();

        $userColumns = ['id', 'name', 'role'];
        $hasUserActive = Schema::hasColumn('users', 'is_active');
        if ($hasUserActive) {
            $userColumns[] = 'is_active';
        }

        $users = User::query()->where('company_id', $company->id)->get($userColumns);
        if (! $hasUserActive) {
            $users->each(fn (User $user) => $user->setAttribute('is_active', true));
        }
        $this->checkRoles($users, $issues, $warnings);
        $this->checkProducts($company, $issues, $warnings);
        $this->checkLandingConnections($company, $issues, $warnings);
        $this->checkPhoneOwnership($company, $warnings);
        $this->checkShipping($company, $issues, $warnings);

        return [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'ok' => $issues->isEmpty(),
            'issues' => $issues->values()->all(),
            'warnings' => $warnings->values()->all(),
        ];
    }

    /** @param Collection<int, User> $users */
    protected function checkRoles(Collection $users, Collection $issues, Collection $warnings): void
    {
        foreach ([UserRole::Admin, UserRole::Marketing, UserRole::Sales, UserRole::Warehouse] as $role) {
            $exists = $users->contains(fn (User $user): bool => $user->role === $role && (bool) $user->is_active);
            if (! $exists) {
                $issues->push("Thiếu nhân sự active vai trò {$role->value}; luồng tự động có thể bị đứt.");
            }
        }

        $accountingExists = $users->contains(fn (User $user): bool => $user->role === UserRole::Accounting && (bool) $user->is_active);
        if (! $accountingExists) {
            $warnings->push('Chưa có tài khoản kế toán active để đối soát doanh thu/COD.');
        }
    }

    protected function checkProducts(Company $company, Collection $issues, Collection $warnings): void
    {
        $products = Product::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'unit_price', 'cost_price', 'type']);

        if ($products->isEmpty()) {
            $issues->push('Chưa có sản phẩm active; Marketing không thể cấu hình kết nối landing chuẩn.');
            return;
        }

        $withoutPrice = $products->filter(fn (Product $product): bool => (int) $product->unit_price <= 0);
        if ($withoutPrice->isNotEmpty()) {
            $warnings->push('Có sản phẩm chưa có giá bán VND: '.$withoutPrice->pluck('name')->take(5)->implode(', '));
        }

        $withoutCost = $products->filter(fn (Product $product): bool => (int) $product->cost_price <= 0 && ($product->type ?? 'product') !== 'combo');
        if ($withoutCost->isNotEmpty()) {
            $warnings->push('Có sản phẩm chưa có giá vốn; báo cáo lợi nhuận sẽ thiếu chính xác: '.$withoutCost->pluck('name')->take(5)->implode(', '));
        }
    }

    protected function checkLandingConnections(Company $company, Collection $issues, Collection $warnings): void
    {
        $connections = LandingConnection::query()
            ->with(['marketingSource', 'sources', 'products.product', 'sales.user'])
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        if ($connections->isEmpty()) {
            $warnings->push('Chưa có kết nối landing active.');
            return;
        }

        foreach ($connections as $connection) {
            $label = "Kết nối #{$connection->id} {$connection->name}";
            $source = $connection->marketingSource;

            if (! $source instanceof MarketingSource) {
                $issues->push("{$label}: thiếu marketing_source nội bộ.");
                continue;
            }

            if ((bool) $connection->is_approved !== (bool) $source->is_approved) {
                $issues->push("{$label}: trạng thái duyệt giữa landing_connections và marketing_sources đang lệch.");
            }

            $main = $connection->sources->first(fn ($row): bool => $row->source_type === 'main' && (bool) $row->is_active);
            if (! $main) {
                $issues->push("{$label}: thiếu nguồn Landing chính active.");
            }

            if ($connection->products->where('product.is_active', true)->isEmpty()) {
                $issues->push("{$label}: chưa mapping sản phẩm/gói sản phẩm backend.");
            }

            if ((int) $connection->budget_amount <= 0) {
                $warnings->push("{$label}: chưa nhập ngân sách chạy, ROAS/CPL/CPA sẽ không đủ nghĩa.");
            }

            if (in_array($connection->allocation_method, ['round_robin', 'priority'], true)
                && $connection->sales->where('is_active', true)->filter(fn ($row) => $row->user?->isSales())->isEmpty()) {
                $issues->push("{$label}: đang bật chia số tự động nhưng chưa có sale nhận số.");
            }

            if ($connection->sources->where('source_type', 'upsell')->isNotEmpty()) {
                $upsellWithoutProducts = $connection->sources
                    ->where('source_type', 'upsell')
                    ->filter(fn ($sourceRow) => $connection->products->where('landing_connection_source_id', $sourceRow->id)->isEmpty());
                if ($upsellWithoutProducts->isNotEmpty()) {
                    $warnings->push("{$label}: có trang upsale chưa mapping sản phẩm riêng, hệ thống sẽ dùng mapping chung nếu có.");
                }
            }
        }
    }


    protected function checkPhoneOwnership(Company $company, Collection $warnings): void
    {
        if (! Schema::hasTable('customer_phone_locks')) {
            $warnings->push('Chưa có bảng customer_phone_locks; cần migrate V20 để tránh hai Sale gọi cùng một SĐT.');
            return;
        }

        $conflicts = Order::withoutTenant()
            ->where('company_id', $company->id)
            ->whereNotNull('customer_phone')
            ->whereNotNull('sale_user_id')
            ->where(function ($query): void {
                $query->whereNull('delivery_status')
                    ->orWhereNotIn('delivery_status', ['delivered', 'paid', 'returned', 'cancel_waybill', 'cancelled', 'canceled']);
            })
            ->selectRaw('customer_phone, COUNT(DISTINCT sale_user_id) as sale_count')
            ->groupBy('customer_phone')
            ->havingRaw('COUNT(DISTINCT sale_user_id) > 1')
            ->limit(5)
            ->pluck('sale_count', 'customer_phone');

        if ($conflicts->isNotEmpty()) {
            $warnings->push('Có SĐT active đang nằm ở nhiều Sale khác nhau trước V20, cần reallocate/khóa lại: '.$conflicts->keys()->implode(', '));
        }
    }

    protected function checkShipping(Company $company, Collection $issues, Collection $warnings): void
    {
        if (! Schema::hasTable('shipping_partner_connections')) {
            $warnings->push('Chưa tìm thấy bảng shipping_partner_connections để audit cấu hình giao vận.');
            return;
        }

        $partners = ShippingPartnerConnection::query()
            ->where('company_id', $company->id)
            ->where('is_enabled', true)
            ->get();

        if ($partners->isEmpty()) {
            $warnings->push('Chưa có cấu hình giao vận active; kho vẫn xử lý thủ công nhưng không tự đồng bộ COD/phí.');
            return;
        }

        $withoutWebhookSecurity = $partners->filter(function ($partner): bool {
            $settings = is_array($partner->settings ?? null) ? $partner->settings : [];
            $credentials = is_array($partner->credentials ?? null) ? $partner->credentials : [];

            return blank($partner->webhook_secret)
                && blank(Arr::get($settings, 'webhook_secret'))
                && blank(Arr::get($credentials, 'webhook_secret'))
                && blank(Arr::get($settings, 'hmac_secret'))
                && blank(Arr::get($credentials, 'hmac_secret'));
        });

        if ($withoutWebhookSecurity->isNotEmpty()) {
            $warnings->push('Một số hãng vận chuyển chưa cấu hình webhook secret/HMAC: '.$withoutWebhookSecurity->pluck('provider')->take(5)->implode(', '));
        }
    }
}
