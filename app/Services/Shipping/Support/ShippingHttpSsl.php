<?php

namespace App\Services\Shipping\Support;

/**
 * Cấu hình verify SSL cho HTTP client gọi API vận chuyển.
 * Ưu tiên: tắt verify (dev) → CA bundle chỉ định → certs/cacert.pem trong project → true (php.ini / OS).
 */
final class ShippingHttpSsl
{
    public static function verifyOption(): bool|string
    {
        if (! config('shipping_partners.verify_ssl', true)) {
            return false;
        }

        $bundle = config('shipping_partners.ca_bundle');

        if (is_string($bundle) && $bundle !== '' && is_readable($bundle)) {
            return $bundle;
        }

        $projectBundle = base_path('certs/cacert.pem');

        if (is_readable($projectBundle)) {
            return $projectBundle;
        }

        return true;
    }
}
