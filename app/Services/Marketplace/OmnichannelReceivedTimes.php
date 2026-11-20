<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Models\ShopeePushInbox;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only ingress timestamps for /omnichannel (Shopee push vs Jubelio queue row).
 */
class OmnichannelReceivedTimes
{
    public const PUSH_CODE_ORDER_STATUS = 3;

    public const TZ = 'Asia/Jakarta';

    /**
     * @param  LengthAwarePaginator<int, MarketplaceOrderShadow>|EloquentCollection<int, MarketplaceOrderShadow>|Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<int, array<string, mixed>>
     */
    public function forShadows($shadows): array
    {
        $rows = $shadows instanceof LengthAwarePaginator
            ? $shadows->getCollection()
            : collect($shadows);

        if ($rows->isEmpty()) {
            return [];
        }

        $inboxLatest = $this->loadLatestInboxByOrdersn($rows);
        $jubelioById = $this->loadJubelioOrdersForShadows($rows);

        $out = [];
        foreach ($rows as $shadow) {
            $jubelio = $this->resolveJubelioOrder($shadow, $jubelioById);
            $out[(int) $shadow->id] = $this->buildRow(
                $shadow,
                $inboxLatest[$shadow->external_order_sn] ?? null,
                $jubelio,
            );
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function forSingle(MarketplaceOrderShadow $shadow, ?Jubelioorder $jubelioOrder = null): array
    {
        $inboxAt = null;
        if (Schema::hasTable('shopee_push_inbox')) {
            $inboxAt = ShopeePushInbox::query()
                ->where('push_code', self::PUSH_CODE_ORDER_STATUS)
                ->where('ordersn', $shadow->external_order_sn)
                ->orderByDesc('created_at')
                ->value('created_at');
        }

        if ($jubelioOrder === null) {
            $map = $this->loadJubelioOrdersForShadows(collect([$shadow]));
            $jubelioOrder = $this->resolveJubelioOrder($shadow, $map);
        }

        return $this->buildRow(
            $shadow,
            $inboxAt,
            $jubelioOrder,
        );
    }

    public function formatWib(?CarbonInterface $moment): ?string
    {
        if ($moment === null) {
            return null;
        }

        return $moment->copy()->timezone(self::TZ)->format('d M Y H:i:s');
    }

    /**
     * @param  Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<string, CarbonInterface|string|null>
     */
    private function loadLatestInboxByOrdersn(Collection $shadows): array
    {
        if (! Schema::hasTable('shopee_push_inbox')) {
            return [];
        }

        $orderSns = $shadows->pluck('external_order_sn')->filter()->unique()->values()->all();
        if ($orderSns === []) {
            return [];
        }

        $latest = [];
        ShopeePushInbox::query()
            ->where('push_code', self::PUSH_CODE_ORDER_STATUS)
            ->whereIn('ordersn', $orderSns)
            ->orderByDesc('created_at')
            ->get(['ordersn', 'created_at'])
            ->each(function (ShopeePushInbox $row) use (&$latest): void {
                $sn = (string) $row->ordersn;
                if ($sn !== '' && ! array_key_exists($sn, $latest)) {
                    $latest[$sn] = $row->created_at;
                }
            });

        return $latest;
    }

    /**
     * @param  Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<int, Jubelioorder>
     */
    private function loadJubelioOrdersForShadows(Collection $shadows): array
    {
        $ids = $shadows->pluck('jubelio_order_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $invoices = $shadows->pluck('jubelio_invoice')->filter()->unique()->values();

        $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));

        $query = Jubelioorder::query()->where('type', 'SELL');

        $query->where(function ($q) use ($ids, $invoices, $shadows, $prefix): void {
            $first = true;
            if ($ids->isNotEmpty()) {
                $q->whereIn('id', $ids->all());
                $first = false;
            }
            if ($invoices->isNotEmpty()) {
                $method = $first ? 'whereIn' : 'orWhereIn';
                $q->{$method}('invoice', $invoices->all());
                $first = false;
            }
            foreach ($shadows as $shadow) {
                $sn = (string) $shadow->external_order_sn;
                if ($sn === '') {
                    continue;
                }
                $base = $prefix.'-'.$sn;
                $method = $first ? 'where' : 'orWhere';
                $q->{$method}(function ($inner) use ($base, $sn, $prefix): void {
                    $inner->where('invoice', $base)
                        ->orWhere('invoice', 'like', $prefix.'-'.$sn.'-%');
                });
                $first = false;
            }
        });

        $byId = [];
        foreach ($query->get(['id', 'invoice', 'created_at']) as $order) {
            $byId[(int) $order->id] = $order;
        }

        return $byId;
    }

    /**
     * @param  array<int, Jubelioorder>  $jubelioById
     */
    private function resolveJubelioOrder(MarketplaceOrderShadow $shadow, array $jubelioById): ?Jubelioorder
    {
        if ($shadow->jubelio_order_id !== null) {
            $hit = $jubelioById[(int) $shadow->jubelio_order_id] ?? null;
            if ($hit !== null) {
                return $hit;
            }
        }

        if (filled($shadow->jubelio_invoice)) {
            foreach ($jubelioById as $order) {
                if ($order->invoice === $shadow->jubelio_invoice) {
                    return $order;
                }
            }
        }

        $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));
        $baseInvoice = $prefix.'-'.$shadow->external_order_sn;
        $best = null;
        foreach ($jubelioById as $order) {
            $invoice = (string) $order->invoice;
            if ($invoice === $baseInvoice || str_starts_with($invoice, $prefix.'-'.$shadow->external_order_sn.'-')) {
                if ($best === null || (int) $order->id > (int) $best->id) {
                    $best = $order;
                }
            }
        }

        return $best;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(
        MarketplaceOrderShadow $shadow,
        CarbonInterface|string|null $inboxCreatedAt,
        ?Jubelioorder $jubelio,
    ): array {
        [$shopeeAt, $shopeeSource] = $this->resolveShopeeReceivedAt($shadow, $inboxCreatedAt);
        $jubelioAt = $jubelio !== null ? $this->castMoment($jubelio->created_at) : null;

        $shopeeFormatted = $this->formatWib($shopeeAt);
        $jubelioFormatted = $this->formatWib($jubelioAt);

        $deltaSeconds = null;
        $deltaLabel = null;
        if ($shopeeAt !== null && $jubelioAt !== null) {
            $deltaSeconds = $shopeeAt->getTimestamp() - $jubelioAt->getTimestamp();
            if ($deltaSeconds > 0) {
                $deltaLabel = '+'.$deltaSeconds.'s';
            } elseif ($deltaSeconds < 0) {
                $deltaLabel = ($deltaSeconds).'s';
            }
        }

        return [
            'shopee_at' => $shopeeAt,
            'shopee_formatted' => $shopeeFormatted,
            'shopee_source' => $shopeeSource,
            'jubelio_at' => $jubelioAt,
            'jubelio_formatted' => $jubelioFormatted,
            'delta_seconds' => $deltaSeconds,
            'delta_label' => $deltaLabel,
            'jubelio_earlier' => $deltaSeconds !== null && $deltaSeconds > 0,
        ];
    }

    /**
     * @return array{0: ?CarbonInterface, 1: ?string}
     */
    private function resolveShopeeReceivedAt(
        MarketplaceOrderShadow $shadow,
        CarbonInterface|string|null $inboxCreatedAt,
    ): array {
        if ($inboxCreatedAt !== null) {
            return [$this->castMoment($inboxCreatedAt), 'inbox'];
        }

        if ($shadow->last_event_at !== null) {
            return [$this->castMoment($shadow->last_event_at), 'last_event_at'];
        }

        if ($shadow->updated_at !== null) {
            return [$this->castMoment($shadow->updated_at), 'updated_at'];
        }

        return [null, null];
    }

    private function castMoment(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        return Carbon::parse($value);
    }
}
