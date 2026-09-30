<?php

namespace App\Services\Shipping\Gateways\NetShip;

/**
 * Gom mọi mã định danh NetShip trả về (customerCode, linkId, externalCode, bill, …)
 * để file đối soát khớp được dù hãng thêm trường mới, miễn tên trường là một mã.
 */
class NetShipPartnerRefs
{
    /**
     * @param  array<mixed>  $payload
     * @return list<string>
     */
    public static function collect(array $payload): array
    {
        $refs = [];
        $walk = function (mixed $node) use (&$walk, &$refs): void {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                    continue;
                }
                if (! is_string($key) || ! self::isRefKey($key)) {
                    continue;
                }
                if (is_int($value) || (is_float($value) && floor($value) === $value)) {
                    $value = (string) (int) $value;
                }
                if (! is_string($value)) {
                    continue;
                }
                $value = trim($value);
                $min = preg_match('/(^id$|order.?id|link.?id)/i', $key) ? 3 : 6;
                if (strlen($value) < $min || strlen($value) > 40) {
                    continue;
                }
                if (preg_match('/^[A-Za-z0-9][A-Za-z0-9\-]*$/', $value) !== 1) {
                    continue;
                }
                $refs[] = $value;
            }
        };
        $walk($payload);

        return array_values(array_unique($refs));
    }

    private static function isRefKey(string $key): bool
    {
        if (preg_match('/(route|unit|province|district|ward|status|fee|price|weight|phone|name|address)/i', $key)) {
            return false;
        }

        return preg_match('/(code|tracking|bill|waybill|linkid|barcode|label|^id$|orderid)/i', $key) === 1;
    }
}
