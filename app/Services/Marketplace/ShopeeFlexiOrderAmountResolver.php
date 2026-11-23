<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Services\Shopee\ShopeeAdminOpenApiService;

/**
 * Convert Shopee FLEXI order line amounts to IDR for Aria sell prefill.
 *
 * Uses escrow {@see get_escrow_detail} exchange_rate when Shopee provides SIP/CB settlement;
 * otherwise never treats MYR figures as IDR — catalog {@see Item::price} fallback.
 */
class ShopeeFlexiOrderAmountResolver
{
    public const PRICE_SOURCE_ESCROW_FX = 'escrow_fx';

    public const PRICE_SOURCE_CATALOG = 'catalog_fallback';

    public const PRICE_SOURCE_SHOPEE_IDR = 'shopee_idr';

    public function __construct(
        private ShopeeAdminOpenApiService $shopeeApi,
        private ShopeeOrderLineSummary $lineSummary,
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

        $escrowLoaded = $escrowPayload !== null;
        if ($escrowPayload === null && $fetchEscrowIfMissing && $shopId > 0 && $orderSn !== '') {
            $escrowPayload = $this->shopeeApi->getEscrowDetail($orderSn, $shopId);
            $escrowLoaded = $escrowPayload !== null;
        }

        $orderIncome = $this->orderIncomeFromEscrow($escrowPayload);
        $escrowSnippet = $this->escrowSnippet($orderIncome);
        $exchangeRate = $this->positiveFloat($orderIncome['exchange_rate'] ?? null);

        $needsConversion = $this->orderNeedsIdrConversion($orderCurrency, $orderIncome);

        if ($needsConversion && $exchangeRate !== null && $exchangeRate > 0) {
            $lines = $this->convertLinesByRate($rawSummary['lines'], $exchangeRate);
            $totalIdr = $orderTotalOriginal !== null
                ? round((float) $orderTotalOriginal * $exchangeRate, 2)
                : round((float) collect($lines)->sum('amount'), 2);

            $priCurrency = strtoupper(trim((string) ($orderIncome['pri_currency'] ?? 'IDR')));
            $affCurrency = strtoupper(trim((string) ($orderIncome['aff_currency'] ?? $orderCurrency ?? 'MYR')));

            return [
                'lines' => $lines,
                'line_count' => count($lines),
                'total_amount' => $totalIdr,
                'order_currency' => $orderCurrency,
                'order_total_original' => $orderTotalOriginal,
                'price_source' => self::PRICE_SOURCE_ESCROW_FX,
                'exchange_rate' => $exchangeRate,
                'escrow_snippet' => $escrowSnippet,
                'banner' => sprintf(
                    'Harga baris IDR dari kurs escrow Shopee (%s → %s, rate %s).',
                    $affCurrency !== '' ? $affCurrency : 'MYR',
                    $priCurrency !== '' ? $priCurrency : 'IDR',
                    $this->formatRate($exchangeRate),
                ),
                'note_reference' => $this->buildNoteReference($orderSn, $orderCurrency, $orderTotalOriginal, $exchangeRate),
                'escrow_loaded' => $escrowLoaded,
            ];
        }

        if ($needsConversion) {
            $lines = $this->applyCatalogPrices($rawSummary['lines']);
            $catalogTotal = round((float) collect($lines)->sum('amount'), 2);
            $displayCurrency = $orderCurrency ?? 'MYR';
            $displayTotal = $orderTotalOriginal;

            return [
                'lines' => $lines,
                'line_count' => count($lines),
                'total_amount' => $catalogTotal > 0 ? $catalogTotal : null,
                'order_currency' => $orderCurrency,
                'order_total_original' => $orderTotalOriginal,
                'price_source' => self::PRICE_SOURCE_CATALOG,
                'exchange_rate' => null,
                'escrow_snippet' => $escrowSnippet,
                'banner' => sprintf(
                    'Shopee %s total %s — tidak ada kurs SIP; harga pakai katalog Aria.',
                    $displayCurrency,
                    $this->formatMoney($displayTotal, $displayCurrency),
                ),
                'note_reference' => $this->buildNoteReference($orderSn, $orderCurrency, $orderTotalOriginal, null),
                'escrow_loaded' => $escrowLoaded,
            ];
        }

        return [
            'lines' => $rawSummary['lines'],
            'line_count' => $rawSummary['line_count'],
            'total_amount' => $orderTotalOriginal,
            'order_currency' => $orderCurrency,
            'order_total_original' => $orderTotalOriginal,
            'price_source' => self::PRICE_SOURCE_SHOPEE_IDR,
            'exchange_rate' => null,
            'escrow_snippet' => $escrowSnippet,
            'banner' => null,
            'note_reference' => null,
            'escrow_loaded' => $escrowLoaded,
        ];
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
     * @param  array<string, mixed>|null  $escrowPayload
     * @return array<string, mixed>|null
     */
    private function orderIncomeFromEscrow(?array $escrowPayload): ?array
    {
        if ($escrowPayload === null || filled($escrowPayload['error'] ?? null)) {
            return null;
        }

        $response = $escrowPayload['response'] ?? null;
        if (! is_array($response)) {
            return null;
        }

        $income = $response['order_income'] ?? $response;
        if (! is_array($income)) {
            return null;
        }

        return $income;
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

    private function buildNoteReference(string $orderSn, ?string $currency, ?float $total, ?float $exchangeRate): ?string
    {
        if ($currency === null || $currency === '' || $currency === 'IDR') {
            return null;
        }

        if ($total === null) {
            return 'Shopee FLEXI ('.$orderSn.') — ref '.$currency;
        }

        $ref = 'Shopee FLEXI ('.$orderSn.') — ref '.$currency.' '.number_format($total, 2, '.', '');
        if ($exchangeRate !== null && $exchangeRate > 0) {
            $ref .= ', rate '.$this->formatRate($exchangeRate);
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
}