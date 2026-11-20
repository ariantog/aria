<?php

namespace App\Support;

/**
 * Shopee Console "Push Test Data" / sandbox order_status_push samples.
 */
class ShopeeTestPushDetector
{
    public const COMPARE_NOTE = 'shopee_test_push';

    /**
     * @param  array<string, mixed>  $data  Push payload `data` object.
     * @param  array<string, mixed>  $payload  Full push JSON (optional).
     */
    public static function isTestPush(string $orderSn, array $data = [], array $payload = []): bool
    {
        $orderSn = trim($orderSn);
        if ($orderSn === '') {
            return false;
        }

        $allowlist = config('services.shopee_push.test_push_ordersn', []);
        if (is_array($allowlist) && in_array($orderSn, $allowlist, true)) {
            return true;
        }

        foreach (['is_test', 'is_sandbox', 'sandbox', 'test'] as $flag) {
            if (! empty($data[$flag]) || ! empty($payload[$flag])) {
                return true;
            }
        }

        return false;
    }
}