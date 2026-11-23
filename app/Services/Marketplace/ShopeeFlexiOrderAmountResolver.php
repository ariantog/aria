<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Models\Setting;
use App\Services\Shopee\ShopeeAdminOpenApiService;
use App\Support\ShopeeAdminOAuthStore;
use Illuminate\Support\Facades\Log;

/**
 * Convert Shopee FLEXI order line amounts to IDR for Aria sell prefill.
 *
 * Escrow via affiliate shop, wallet ref (MY-{sn}), and Indonesia primary shop.
 */
class ShopeeFlexiOrderAmountResolver
{
    public const PRICE_SOURCE_ESCROW_FX = 'escrow_fx';

    public const PRICE_SOURCE_ESCROW_IDR = 'escrow_idr';

    public const PRICE_SOURCE_CATALOG = 'catalog_fallback';

    public const PRICE_SOURCE_SHOPEE_IDR = 'shopee_idr';

    public function __construct(
        private ShopeeOrderLineSummary $lineSummary,
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeSyncInvoicePrefix $invoicePrefix,
    ) {}

    /**
     * @param  array<string, mixed>  $orderRow  order_list[0] from get_order_detail
     * @param  array<string, mixed>|null  $escrowPayload  full decode from get_escrow_detail (optional)
     * @return array{
     *     lines: list<array{sku: string, qty: float, amount: float}>,
     *     line_count: int,
     *     total_amount: float|null,
     *     order_currency: ?string,
     *     order_total_original: float|null,
     *     price_source: string,
     *     exchange_rate: float|null,
     *     escrow_snippet: array<string, mixed>|null,
     *     banner: ?string,
     *     note_reference: ?string,
     *     escrow_loaded: bool,
     *     escrow_usable: bool,
     *     idr_escrow_total: float|null,
     *     escrow_fetch: array<string, mixed>|null,
     * }
     */
    public function resolve(
        int $shopId,
        string $orderSn,
        array $orderRow,
        ?array $escrowPayload = null,
        bool $fetchEscrowIfMissing = true,
    ): array {
        $rawSummary = $this->lineSummary->fromOrderRow($orderRow);
        $orderCurrency = $this->orderCurrency($orderRow);
        $orderTotalOriginal = $rawSummary['total_amount'];

        $escrowFetchMeta = null;
        $orderIncome = null;

        if ($escrowPayload !== null) {
            $orderIncome = $this->orderIncomeFromPayload($escrowPayload);
            $escrowFetchMeta = [
                'shop_id' => $shopId,
                'order_sn' => $orderSn,
                'usable' => $this->isUsableOrderIncome($orderIncome),
                'via_primary_shop' => false,
                'attempts' => [],
            ];
        } elseif ($fetchEscrowIfMissing && $shopId > 0 && $orderSn !== '') {
            $fetched = $this->fetchForFlexiOrder($shopId, $orderSn);
            $escrowPayload = $fetched['payload'];
            $orderIncome = $fetched['order_income'];
            $escrowFetchMeta = $fetched['meta'];
        }

        $escrowLoaded = $escrowPayload !== null;
        $escrowSnippet = $this->escrowSnippet($orderIncome);
        $exchangeRate = $this->positiveFloat($orderIncome['exchange_rate'] ?? null);
        $idrEscrowTotal = $orderIncome !== null
            ? $this->resolvedIdrEscrowTotal($orderIncome)
            : null;

        $escrowUsable = $this->isUsableOrderIncome($orderIncome);

        $needsConversion = $this->orderNeedsIdrConversion($orderCurrency, $orderIncome);

        $baseTail = [
            'order_currency' => $orderCurrency,
            'order_total_original' => $orderTotalOriginal,
            'escrow_snippet' => $escrowSnippet,
            'escrow_loaded' => $escrowLoaded,
            'escrow_usable' => $escrowUsable,
            'idr_escrow_total' => $idrEscrowTotal,
            'escrow_fetch' => $escrowFetchMeta,
        ];

        if ($needsConversion && $exchangeRate !== null && $exchangeRate > 0) {
            $lines = $this->convertLinesByRate($rawSummary['lines'], $exchangeRate);
            $totalIdr = $orderTotalOriginal !== null
                ? round((float) $orderTotalOriginal * $exchangeRate, 2)
                : round((float) collect($lines)->sum('amount'), 2);

            $priCurrency = strtoupper(trim((string) ($orderIncome['pri_currency'] ?? 'IDR')));
            $affCurrency = strtoupper(trim((string) ($orderIncome['aff_currency'] ?? $orderCurrency ?? 'MYR')));

            return array_merge([
                'lines' => $lines,
                'line_count' => count($lines),
                'total_amount' => $totalIdr,
                'price_source' => self::PRICE_SOURCE_ESCROW_FX,
                'exchange_rate' => $exchangeRate,
                'banner' => sprintf(
                    'Harga baris IDR dari kurs escrow Shopee (%s → %s, rate %s).',
                    $affCurrency !== '' ? $affCurrency : 'MYR',
                    $priCurrency !== '' ? $priCurrency : 'IDR',
                    $this->formatRate($exchangeRate),
                ),
                'note_reference' => $this->buildNoteReference($orderSn, $orderCurrency, $orderTotalOriginal, $exchangeRate, $idrEscrowTotal),
            ], $baseTail);
        }

        if ($needsConversion && $idrEscrowTotal !== null && $orderIncome !== null) {
            $lines = $this->linesFromIdrEscrow($orderIncome, $rawSummary['lines'], $idrEscrowTotal);
            $viaPrimary = (bool) ($escrowFetchMeta['via_primary_shop'] ?? false);
            $fetchShop = (int) ($escrowFetchMeta['shop_id'] ?? $shopId);
            $fetchSn = (string) ($escrowFetchMeta['order_sn'] ?? $orderSn);

            $banner = sprintf(
                'Penghasilan IDR dari escrow Shopee (Rp %s)%s.',
                number_format($idrEscrowTotal, 0, '.', ','),
                $viaPrimary
                    ? sprintf(' — diambil dari shop primary ID %s, ref %s', $fetchShop, $fetchSn)
                    : '',
            );

            return array_merge([
                'lines' => $lines,
                'line_count' => count($lines),
                'total_amount' => round((float) collect($lines)->sum('amount'), 2),
                'price_source' => self::PRICE_SOURCE_ESCROW_IDR,
                'exchange_rate' => null,
                'banner' => $banner,
                'note_reference' => $this->buildNoteReference($orderSn, $orderCurrency, $orderTotalOriginal, null, $idrEscrowTotal),
            ], $baseTail);
        }

        if ($needsConversion) {
            Log::debug('Shopee FLEXI escrow fallback to catalog', [
                'shop_id' => $shopId,
                'order_sn' => $orderSn,
                'escrow_attempts' => $escrowFetchMeta['attempts'] ?? [],
            ]);

            $lines = $this->applyCatalogPrices($rawSummary['lines']);
            $catalogTotal = round((float) collect($lines)->sum('amount'), 2);
            $displayCurrency = $orderCurrency ?? 'MYR';
            $displayTotal = $orderTotalOriginal;

            return array_merge([
                'lines' => $lines,
                'line_count' => count($lines),
                'total_amount' => $catalogTotal > 0 ? $catalogTotal : null,
                'price_source' => self::PRICE_SOURCE_CATALOG,
                'exchange_rate' => null,
                'banner' => sprintf(
                    'Shopee %s total %s — escrow IDR/kurs tidak ditemukan; harga pakai katalog Aria.',
                    $displayCurrency,
                    $this->formatMoney($displayTotal, $displayCurrency),
                ),
                'note_reference' => $this->buildNoteReference($orderSn, $orderCurrency, $orderTotalOriginal, null, null),
            ], $baseTail);
        }

        return array_merge([
            'lines' => $rawSummary['lines'],
            'line_count' => $rawSummary['line_count'],
            'total_amount' => $orderTotalOriginal,
            'price_source' => self::PRICE_SOURCE_SHOPEE_IDR,
            'exchange_rate' => null,
            'banner' => null,
            'note_reference' => null,
        ], $baseTail);
    }

    /**
     * @param  array<string, mixed>|null  $orderRow
     */
    private function orderCurrency(?array $orderRow): ?string
    {
        if (! is_array($orderRow)) {
            return null;
        }

        $currency = trim((string) ($orderRow['currency'] ?? ''));
        if ($currency !== '') {
            return strtoupper($currency);
        }

        $payment = $orderRow['payment_info'] ?? null;
        if (is_array($payment)) {
            $fromPayment = trim((string) ($payment['currency'] ?? ''));
            if ($fromPayment !== '') {
                return strtoupper($fromPayment);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $orderIncome
     * @param  list<array{sku: string, qty: float, amount: float}>  $rawLines
     * @return list<array{sku: string, qty: float, amount: float}>
     */
    private function linesFromIdrEscrow(array $orderIncome, array $rawLines, float $idrEscrowTotal): array
    {
        $fromItems = $this->linesFromEscrowItemIncome($orderIncome, $rawLines);
        if ($fromItems !== null) {
            $sum = round((float) collect($fromItems)->sum('amount'), 2);
            if ($sum > 0 && abs($sum - $idrEscrowTotal) <= max(1.0, $idrEscrowTotal * 0.02)) {
                return $fromItems;
            }
        }

        return $this->allocateIdrProportionally($rawLines, $idrEscrowTotal);
    }

    /**
     * @param  array<string, mixed>  $orderIncome
     * @param  list<array{sku: string, qty: float, amount: float}>  $rawLines
     * @return list<array{sku: string, qty: float, amount: float}>|null
     */
    private function linesFromEscrowItemIncome(array $orderIncome, array $rawLines): ?array
    {
        $items = $orderIncome['items'] ?? null;
        if (! is_array($items) || $items === []) {
            return null;
        }

        $bySku = [];
        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sku = strtoupper(trim((string) ($row['model_sku'] ?? $row['item_sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $qty = (float) ($row['quantity_purchased'] ?? $row['model_quantity_purchased'] ?? 1);
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $amount = $this->positiveFloat($row['escrow_amount_pri'] ?? null)
                ?? $this->positiveFloat($row['seller_income'] ?? null)
                ?? $this->positiveFloat($row['discounted_price_pri'] ?? null);

            if ($amount === null) {
                $unitPri = $this->positiveFloat($row['original_price_pri'] ?? null)
                    ?? $this->positiveFloat($row['discounted_price_pri'] ?? null);
                if ($unitPri !== null) {
                    $amount = round($unitPri * $qty, 2);
                }
            }

            if ($amount === null) {
                return null;
            }

            $bySku[$sku] = ($bySku[$sku] ?? 0.0) + $amount;
        }

        $out = [];
        foreach ($rawLines as $line) {
            $sku = strtoupper((string) ($line['sku'] ?? ''));
            if ($sku === '' || ! array_key_exists($sku, $bySku)) {
                return null;
            }

            $out[] = [
                'sku' => $sku,
                'qty' => (float) ($line['qty'] ?? 0),
                'amount' => round((float) $bySku[$sku], 2),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  list<array{sku: string, qty: float, amount: float}>  $lines
     * @return list<array{sku: string, qty: float, amount: float}>
     */
    private function allocateIdrProportionally(array $lines, float $totalIdr): array
    {
        if ($lines === []) {
            return [];
        }

        $weightSum = (float) collect($lines)->sum(fn (array $line) => max((float) ($line['amount'] ?? 0), (float) ($line['qty'] ?? 1)));

        if ($weightSum <= 0) {
            $each = round($totalIdr / count($lines), 2);
            $out = [];
            foreach ($lines as $line) {
                $out[] = [
                    'sku' => (string) ($line['sku'] ?? ''),
                    'qty' => (float) ($line['qty'] ?? 0),
                    'amount' => $each,
                ];
            }

            return $out;
        }

        $out = [];
        $allocated = 0.0;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $weight = max((float) ($line['amount'] ?? 0), (float) ($line['qty'] ?? 1));
            if ($index === $lastIndex) {
                $amount = round($totalIdr - $allocated, 2);
            } else {
                $amount = round($totalIdr * ($weight / $weightSum), 2);
                $allocated += $amount;
            }

            $out[] = [
                'sku' => (string) ($line['sku'] ?? ''),
                'qty' => (float) ($line['qty'] ?? 0),
                'amount' => $amount,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $orderIncome
     * @return array<string, mixed>|null
     */
    private function escrowSnippet(?array $orderIncome): ?array
    {
        if ($orderIncome === null) {
            return null;
        }

        $keys = [
            'escrow_amount',
            'currency',
            'exchange_rate',
            'escrow_amount_pri',
            'pri_currency',
            'aff_currency',
        ];

        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $orderIncome) && $orderIncome[$key] !== null && $orderIncome[$key] !== '') {
                $out[$key] = $orderIncome[$key];
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>|null  $orderIncome
     */
    private function orderNeedsIdrConversion(?string $orderCurrency, ?array $orderIncome): bool
    {
        if ($orderCurrency !== null && $orderCurrency !== '' && $orderCurrency !== 'IDR') {
            return true;
        }

        if ($orderIncome === null) {
            return false;
        }

        $aff = strtoupper(trim((string) ($orderIncome['aff_currency'] ?? '')));
        $pri = strtoupper(trim((string) ($orderIncome['pri_currency'] ?? '')));
        $escrowCurrency = strtoupper(trim((string) ($orderIncome['currency'] ?? '')));

        if ($aff !== '' && $aff !== 'IDR') {
            return true;
        }

        if ($escrowCurrency !== '' && $escrowCurrency !== 'IDR') {
            return true;
        }

        if ($pri !== '' && $pri !== 'IDR' && ($aff !== '' || $escrowCurrency !== '')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{sku: string, qty: float, amount: float}>  $lines
     * @return list<array{sku: string, qty: float, amount: float}>
     */
    private function convertLinesByRate(array $lines, float $exchangeRate): array
    {
        $out = [];
        foreach ($lines as $line) {
            $amount = round((float) ($line['amount'] ?? 0) * $exchangeRate, 2);
            $out[] = [
                'sku' => (string) ($line['sku'] ?? ''),
                'qty' => (float) ($line['qty'] ?? 0),
                'amount' => $amount,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{sku: string, qty: float, amount: float}>  $lines
     * @return list<array{sku: string, qty: float, amount: float}>
     */
    private function applyCatalogPrices(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $skus = collect($lines)->pluck('sku')->filter()->unique()->values()->all();
        $upperSkus = array_map('strtoupper', $skus);

        $items = Item::query()
            ->where(function ($query) use ($upperSkus) {
                foreach ($upperSkus as $sku) {
                    $query->orWhereRaw('UPPER(code) = ?', [$sku])
                        ->orWhereRaw('UPPER(legacy_code) = ?', [$sku]);
                }
            })
            ->get(['id', 'code', 'legacy_code', 'price']);

        $skuToPrice = [];
        foreach ($items as $item) {
            $price = (float) ($item->price ?? 0);
            $skuToPrice[strtoupper((string) $item->code)] = $price;
            $legacy = trim((string) $item->legacy_code);
            if ($legacy !== '') {
                $skuToPrice[strtoupper($legacy)] = $price;
            }
        }

        $out = [];
        foreach ($lines as $line) {
            $sku = strtoupper((string) ($line['sku'] ?? ''));
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                $qty = 1.0;
            }

            $catalogPrice = $skuToPrice[$sku] ?? 0.0;
            $amount = $catalogPrice > 0 ? round($catalogPrice * $qty, 2) : 0.0;

            $out[] = [
                'sku' => $sku,
                'qty' => $qty,
                'amount' => $amount,
            ];
        }

        return $out;
    }

    private function buildNoteReference(
        string $orderSn,
        ?string $currency,
        ?float $total,
        ?float $exchangeRate,
        ?float $idrEscrowTotal,
    ): ?string {
        if ($currency === null || $currency === '' || $currency === 'IDR') {
            if ($idrEscrowTotal !== null && $idrEscrowTotal > 0) {
                return sprintf(
                    'Shopee FLEXI (%s) — escrow IDR %s',
                    $orderSn,
                    number_format($idrEscrowTotal, 0, '.', ','),
                );
            }

            return null;
        }

        if ($total === null) {
            return 'Shopee FLEXI ('.$orderSn.') — ref '.$currency;
        }

        $ref = 'Shopee FLEXI ('.$orderSn.') — ref '.$currency.' '.number_format($total, 2, '.', '');
        if ($exchangeRate !== null && $exchangeRate > 0) {
            $ref .= ', rate '.$this->formatRate($exchangeRate);
        }
        if ($idrEscrowTotal !== null && $idrEscrowTotal > 0) {
            $ref .= ', escrow IDR '.number_format($idrEscrowTotal, 0, '.', ',');
        }

        return $ref;
    }

    private function formatRate(float $rate): string
    {
        $formatted = number_format($rate, 6, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private function formatMoney(?float $amount, string $currency): string
    {
        if ($amount === null) {
            return '—';
        }

        return $currency.' '.number_format($amount, 2, '.', ',');
    }

    /**
     * Strict FLEXI manual sell: escrow API must have loaded and amounts must not rely on catalog-only fallback for FX orders.
     *
     * @param  array<string, mixed>  $amounts  output of {@see resolve()}
     */
    public function amountsUsableForSellPrefill(array $amounts): bool
    {
        if (! ($amounts['escrow_loaded'] ?? false)) {
            return false;
        }

        $source = (string) ($amounts['price_source'] ?? '');
        if ($source === self::PRICE_SOURCE_ESCROW_FX) {
            $rate = $amounts['exchange_rate'] ?? null;

            return is_numeric($rate) && (float) $rate > 0;
        }

        if ($source === self::PRICE_SOURCE_ESCROW_IDR) {
            $idrTotal = $amounts['idr_escrow_total'] ?? null;
            if ($idrTotal !== null && (float) $idrTotal > 0) {
                return true;
            }

            $lines = $amounts['lines'] ?? [];

            return $lines !== [] && (float) collect($lines)->sum('amount') > 0;
        }

        if ($source === self::PRICE_SOURCE_SHOPEE_IDR) {
            $lines = $amounts['lines'] ?? [];

            return $lines !== [] && (float) collect($lines)->sum('amount') > 0;
        }

        return false;
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
    private function fetchForFlexiOrder(int $affiliateShopId, string $orderSn): array
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
    private function escrowOrderSnVariants(int $affiliateShopId, string $orderSn): array
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
    private function isUsableOrderIncome(?array $orderIncome): bool
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
    private function resolvedIdrEscrowTotal(array $orderIncome): ?float
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
    private function orderIncomeFromPayload(?array $payload): ?array
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
}