<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;

class JubelioOrderSyncStatus
{
    public const ERROR_SKU = 1;

    public const ERROR_DUPLICATE = 2;

    /** API fetch empty (SELL) or original sell missing (RETURN). */
    public const ERROR_PAYLOAD = 3;

    public const SUCCESS = 10;

    public const MESSAGE_SELL_API_EMPTY = 'Tidak dapat memuat order dari API Jubelio (fetch gagal atau respons kosong). Kolom payload kosong di database adalah normal — Aria selalu mengambil data lewat API. Periksa token/koneksi Jubelio, klik Refresh payload, lalu Buat Transaksi Manual.';

    public const MESSAGE_RETURN_SELL_MISSING = 'Transaksi jual (asal) tidak ditemukan untuk retur ini';

    public static function badgeLabel(int $status, ?int $errorType, string $type = 'SELL', ?string $error = null): string
    {
        if ($status === 2 && $errorType === self::SUCCESS) {
            return 'Success';
        }
        if ($status === 2 && $errorType === self::ERROR_DUPLICATE) {
            return 'Duplicate';
        }
        if ($status === 1 && $errorType === self::ERROR_SKU) {
            return 'Error SKU';
        }
        if ($status === 1 && $errorType === self::ERROR_PAYLOAD) {
            if ($type === 'RETURN' && self::isReturnMissingSourceSaleError($error)) {
                return 'Original sale missing';
            }

            return 'API failed';
        }
        if ($status === 0) {
            return 'Pending';
        }

        return 'Error sync';
    }

    public static function shouldShowErrorLine(Jubelioorder $order): bool
    {
        if ($order->error === null || $order->error === '') {
            return false;
        }

        if ($order->status === 2 && $order->error_type === self::ERROR_DUPLICATE) {
            return true;
        }

        if ($order->status === 1 && in_array($order->error_type, [self::ERROR_SKU, self::ERROR_PAYLOAD], true)) {
            return true;
        }

        return false;
    }

    public static function isReturnMissingSourceSaleError(?string $error): bool
    {
        if ($error === null || $error === '') {
            return false;
        }

        return str_contains($error, self::MESSAGE_RETURN_SELL_MISSING)
            || str_contains($error, 'jual (asal)');
    }

    public static function payloadErrorBadgeTitle(string $type, ?string $error = null): string
    {
        if ($type === 'RETURN' && self::isReturnMissingSourceSaleError($error)) {
            return 'No matching sell transaction in Aria for this return invoice';
        }

        return 'Could not load order payload from Jubelio API';
    }
}
