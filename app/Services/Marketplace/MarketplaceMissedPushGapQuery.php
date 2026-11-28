<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceMissedPushGap;
use App\Models\Shopeesync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MarketplaceMissedPushGapQuery
{
    /**
     * @return array<string, mixed>
     */
    public function parseFilters(Request $request): array
    {
        $channel = strtolower(trim((string) $request->query('channel', 'shopee')));
        if (! in_array($channel, ['all', 'shopee', 'tiktok'], true)) {
            $channel = 'shopee';
        }

        $dateTo = $this->parseDate($request->query('date_to'), Carbon::now()->endOfDay());
        $dateFrom = $this->parseDate(
            $request->query('date_from'),
            Carbon::now()->subDays(14)->startOfDay(),
        );

        if (is_string($request->query('date_to')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($request->query('date_to')))) {
            $dateTo = $dateTo->copy()->endOfDay();
        }

        if (is_string($request->query('date_from')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($request->query('date_from')))) {
            $dateFrom = $dateFrom->copy()->startOfDay();
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $shopRef = trim((string) $request->query('shop_ref', ''));
        $shopRef = $shopRef !== '' ? $shopRef : null;

        $orderRef = trim((string) $request->query('order_ref', ''));
        $orderRef = $orderRef !== '' ? $orderRef : null;

        $perPage = min(max((int) $request->query('per_page', 25), 10), 100);

        return [
            'channel' => $channel,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'shop_ref' => $shopRef,
            'order_ref' => $orderRef,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<MarketplaceMissedPushGap>
     */
    public function openGapsQuery(array $filters): Builder
    {
        $query = MarketplaceMissedPushGap::query()
            ->whereNull('ingested_at')
            ->orderByDesc('detected_at')
            ->orderByDesc('id');

        $this->applyChannel($query, $filters['channel'] ?? 'shopee');
        $this->applyDateRange($query, $filters);
        $this->applyShopRef($query, $filters['shop_ref'] ?? null);
        $this->applyOrderRef($query, $filters['order_ref'] ?? null);

        return $query;
    }

    /**
     * @return array<int, string|null> shop_ref => last_order_poll_at iso
     */
    public function shopeeLastReconcileByShop(): array
    {
        if (! Schema::hasTable('shopee_syncs') || ! Schema::hasColumn('shopee_syncs', 'last_order_poll_at')) {
            return [];
        }

        $map = [];
        Shopeesync::query()
            ->where('shop_id', '>', 0)
            ->orderBy('shop_id')
            ->get(['shop_id', 'last_order_poll_at'])
            ->each(function (Shopeesync $row) use (&$map): void {
                $polled = $row->last_order_poll_at;
                $map[(int) $row->shop_id] = $polled !== null && $polled !== ''
                    ? Carbon::parse($polled)->toIso8601String()
                    : null;
            });

        return $map;
    }

    /**
     * @param  Builder<MarketplaceMissedPushGap>  $query
     */
    private function applyChannel(Builder $query, string $channel): void
    {
        if ($channel === 'all') {
            return;
        }

        $query->where('channel', $channel);
    }

    /**
     * @param  Builder<MarketplaceMissedPushGap>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRange(Builder $query, array $filters): void
    {
        $query->whereBetween('detected_at', [$filters['date_from'], $filters['date_to']]);
    }

    /**
     * @param  Builder<MarketplaceMissedPushGap>  $query
     */
    private function applyShopRef(Builder $query, ?string $shopRef): void
    {
        if ($shopRef === null) {
            return;
        }

        $query->where('shop_ref', $shopRef);
    }

    /**
     * @param  Builder<MarketplaceMissedPushGap>  $query
     */
    private function applyOrderRef(Builder $query, ?string $orderRef): void
    {
        if ($orderRef === null) {
            return;
        }

        $query->where('order_ref', 'like', '%'.$orderRef.'%');
    }

    private function parseDate(mixed $value, Carbon $default): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        try {
            return Carbon::parse(trim($value), config('app.timezone'));
        } catch (\Throwable) {
            return $default;
        }
    }
}