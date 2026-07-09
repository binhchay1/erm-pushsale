<?php

namespace App\Enums;

/**
 * Các khu vực chức năng của doanh nghiệp dùng cho phân quyền chi tiết.
 * Gộp tất cả các phần: báo cáo, tác nghiệp, marketing, chia số, kho, vận chuyển,
 * kế toán, khách hàng, sản phẩm, nhân sự, kết nối, nhật ký.
 */
enum PermissionArea: string
{
    case Reports = 'reports';
    case Telesale = 'telesale';
    case Orders = 'orders';
    case Marketing = 'marketing';
    case Leads = 'leads';
    case Warehouse = 'warehouse';
    case Shipping = 'shipping';
    case Accounting = 'accounting';
    case Customers = 'customers';
    case CustomerChat = 'customer_chat';
    case Products = 'products';
    case Hr = 'hr';
    case Integrations = 'integrations';
    case Pancake = 'pancake';
    case Activity = 'activity';

    /** Key i18n frontend cho nhãn khu vực. */
    public function labelKey(): string
    {
        return 'permissions.area.'.$this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
