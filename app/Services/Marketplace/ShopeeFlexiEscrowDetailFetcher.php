<?php

namespace App\Services\Marketplace;

use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeAdminOAuthStore;
use Illuminate\Support\Facades\Log;

/**
 * Resolve FLEXI export escrow via affiliate shop and Indonesia primary shop (Local SIP).
 */
class ShopeeFlexiEscrowDetailFetcher
{
    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
    ) {}

    /**
     * @return array{
     *     payload: ?array,
     *     order_income: ?array,
     *     meta: array{
     *         shop_id: int,
     *         order_sn: string,
     *         usable: bool,
     *         via_primary_shop: bool,
     *         attempts: list<array{shop_id: int, order_sn: string, usable: bool}>,
     *     },
     * }
     */
    public function fetchForFlexiOrder(int $affiliateShopId, string $orderSn): array
    {
        $orderSn = trim($orderSn);
        $emptyMeta = [
            'shop_id' => $affiliateShopId,
            'order_sn' => $orderSn,
            'usable' => false,
            'via_primary_shop' => false,
            'attempts' => [],
        ];

        if ($affiliateShopId <= 0 || $orderSn === '') {
            return [
                'payload' => null,
                'order_income' => null,
                'meta' => $emptyMeta,
            ];
        }

        $oauth = $this->oauthPayload();
        $primaryShopId = ShopeeAdminOAuthStore::primaryShopId($oauth);
        $snVariants = $this->escrowOrderSnVariants($affiliateShopId, $orderSn);

        $attempts = [];
        $shopQueue = [$affiliateShopId];
        if ($primaryShopId > 0 && $primaryShopId !== $affiliateShopId) {
            $shopQueue[] = $primaryShopId;
        }

        $lastPayload = null;

        foreach ($shopQueue as $tryShopId) {
            foreach ($snVariants as $trySn) {
                $payload = $this->shopeeApi->getEscrowDetail($trySn, $tryShopId);
                if ($payload !== null) {
                    $lastPayload = $payload;
                }
                $income = $this->orderIncomeFromPayload($payload);
                $usable = $this->isUsableOrderIncome($income);

                $attempts[] = [
                    'shop_id' => $tryShopId,
                    'order_sn' => $trySn,
                    'usable' => $usable,
                ];

                if ($usable) {
                    Log::debug('Shopee FLEXI escrow resolved', [
                        'affiliate_shop_id' => $affiliateShopId,
                        'escrow_shop_id' => $tryShopId,
                        'escrow_order_sn' => $trySn,
                        'via_primary' => $tryShopId === $primaryShopId && $tryShopId !== $affiliateShopId,
                    ]);

                    return [
                        'payload' => $payload,
                        'order_income' => $income,
                        'meta' => [
                            'shop_id' => $tryShopId,
                            'order_sn' => $trySn,
                            'usable' => true,
                            'via_primary_shop' => $tryShopId === $primaryShopId && $tryShopId !== $affiliateShopId,
                            'attempts' => $attempts,
                        ],
                    ];
                }
            }
        }

        return [
            'payload' => $lastPayload,
            'order_income' => $this->orderIncomeFromPayload($lastPayload),
            'meta' => [
                'shop_id' => $affiliateShopId,
                'order_sn' => $orderSn,
                'usable' => false,
                'via_primary_shop' => false,
                'attempts' => $attempts,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function escrowOrderSnVariants(int $affiliateShopId, string $orderSn): array
    {
        $orderSn = trim($orderSn);
        $variants = [$orderSn];

        $prefix = strtoupper((string) $this->invoicePrefix->prefixForShopId($affiliateShopId));
        if (strlen($prefix) >= 4 && str_starts_with($prefix, 'SP')) {
            $walletRegion = substr($prefix, 2, 2);
            if ($walletRegion !== '') {
                $variants[] = $walletRegion.'-'.$orderSn;
            }
        }

        $walletRef = 'MY-'.$orderSn;
        if (! in_array($walletRef, $variants, true)) {
            $variants[] = $walletRef;
        }

        return array_values(array_unique(array_filter($variants, fn (string $v) => $v !== '')));
    }

    /**
     * @param  array<string, mixed>|null  $orderIncome
     */
    public function isUsableOrderIncome(?array $orderIncome): bool
    {
        if ($orderIncome === null || $orderIncome === []) {
            return false;
        }

        if ($this->positiveFloat($orderIncome['exchange_rate'] ?? null) !== null) {
            return true;
        }

        if ($this->resolvedIdrEscrowTotal($orderIncome) !== null) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $orderIncome
     */
    public function resolvedIdrEscrowTotal(array $orderIncome): ?float
    {
        $priCurrency = strtoupper(trim((string) ($orderIncome['pri_currency'] ?? '')));
        $escrowPri = $this->positiveFloat($orderIncome['escrow_amount_pri'] ?? null);
        if ($priCurrency === 'IDR' && $escrowPri !== null) {
            return $escrowPri;
        }

        $currency = strtoupper(trim((string) ($orderIncome['currency'] ?? '')));
        $escrowAmount = $this->positiveFloat($orderIncome['escrow_amount'] ?? null);
        if ($currency === 'IDR' && $escrowAmount !== null) {
            return $escrowAmount;
        }

        if ($priCurrency === '' && $currency === 'IDR' && $escrowPri !== null) {
            return $escrowPri;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function orderIncomeFromPayload(?array $payload): ?array
    {
        if ($payload === null || filled($payload['error'] ?? null)) {
            return null;
        }

        $response = $payload['response'] ?? null;
        if (! is_array($response)) {
            return null;
        }

        $income = $response['order_income'] ?? $response;
        if (! is_array($income) || $income === []) {
            return null;
        }

        return $income;
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthPayload(): array
    {
        $value = Setting::getValue(ShopeeAdminOpenApiService::OAUTH_SETTING_SLUG, []);

        return is_array($value) ? $value : [];
    }

    private function positiveFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }
}