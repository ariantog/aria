<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceChannelOrder;
use App\Models\MarketplaceOrderShadow;
use App\Models\MarketplaceWebhookEvent;
use App\Models\ShopeePushInbox;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only ingress timestamps for /omnichannel (Shopee push vs Jubelio queue row).
 *
 * Shopee push uses the earliest successful inbox or webhook receipt (WIB display).
 */
class OmnichannelReceivedTimes
{
    public function __construct(
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

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

        $inboxEarliest = $this->loadEarliestInboxByOrdersn($rows);
        $webhookEarliest = $this->loadEarliestWebhookByOrdersn($rows);
        $channelOrderEarliest = $this->loadEarliestChannelOrderReceived($rows);
        $jubelioById = $this->loadJubelioOrdersForShadows($rows);

        $out = [];
        foreach ($rows as $shadow) {
            $jubelio = $this->resolveJubelioOrder($shadow, $jubelioById);
            $sn = (string) $shadow->external_order_sn;
            $out[(int) $shadow->id] = $this->buildRow(
                $shadow,
                $inboxEarliest[$sn] ?? null,
                $webhookEarliest[$sn] ?? null,
                $channelOrderEarliest[$sn] ?? null,
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
        $sn = (string) $shadow->external_order_sn;
        $inboxAt = null;
        if (Schema::hasTable('shopee_push_inbox') && $sn !== '') {
            $inboxAt = ShopeePushInbox::query()
                ->where('push_code', self::PUSH_CODE_ORDER_STATUS)
                ->where('ordersn', $sn)
                ->orderBy('created_at')
                ->value('created_at');
        }

        $webhookAt = $this->earliestWebhookReceivedAt($sn);
        $channelOrderAt = $this->earliestChannelOrderReceivedAt($sn);

        if ($jubelioOrder === null) {
            $map = $this->loadJubelioOrdersForShadows(collect([$shadow]));
            $jubelioOrder = $this->resolveJubelioOrder($shadow, $map);
        }

        return $this->buildRow(
            $shadow,
            $inboxAt,
            $webhookAt,
            $channelOrderAt,
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
    private function loadEarliestInboxByOrdersn(Collection $shadows): array
    {
        if (! Schema::hasTable('shopee_push_inbox')) {
            return [];
        }

        $orderSns = $shadows->pluck('external_order_sn')->filter()->unique()->values()->all();
        if ($orderSns === []) {
            return [];
        }

        $earliest = [];
        ShopeePushInbox::query()
            ->where('push_code', self::PUSH_CODE_ORDER_STATUS)
            ->whereIn('ordersn', $orderSns)
            ->orderBy('created_at')
            ->get(['ordersn', 'created_at'])
            ->each(function (ShopeePushInbox $row) use (&$earliest): void {
                $sn = (string) $row->ordersn;
                if ($sn !== '' && ! array_key_exists($sn, $earliest)) {
                    $earliest[$sn] = $row->created_at;
                }
            });

        return $earliest;
    }

    /**
     * @param  Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<string, CarbonInterface|string|null>
     */
    private function loadEarliestWebhookByOrdersn(Collection $shadows): array
    {
        if (! Schema::hasTable('marketplace_webhook_events')) {
            return [];
        }

        $orderSns = $shadows->pluck('external_order_sn')->filter()->unique()->values()->all();
        if ($orderSns === []) {
            return [];
        }

        $earliest = [];
        MarketplaceWebhookEvent::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->whereIn('order_ref', $orderSns)
            ->where('signature_ok', true)
            ->orderBy('received_at')
            ->get(['order_ref', 'received_at'])
            ->each(function (MarketplaceWebhookEvent $row) use (&$earliest): void {
                $ref = (string) $row->order_ref;
                if ($ref !== '' && ! array_key_exists($ref, $earliest)) {
                    $earliest[$ref] = $row->received_at;
                }
            });

        return $earliest;
    }

    /**
     * @param  Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<string, CarbonInterface|string|null>
     */
    private function loadEarliestChannelOrderReceived(Collection $shadows): array
    {
        if (! Schema::hasTable('marketplace_channel_orders')) {
            return [];
        }

        $orderSns = $shadows->pluck('external_order_sn')->filter()->unique()->values()->all();
        if ($orderSns === []) {
            return [];
        }

        $earliest = [];
        MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->whereIn('order_ref', $orderSns)
            ->orderBy('first_received_at')
            ->get(['order_ref', 'first_received_at'])
            ->each(function (MarketplaceChannelOrder $row) use (&$earliest): void {
                $ref = (string) $row->order_ref;
                if ($ref !== '' && ! array_key_exists($ref, $earliest) && $row->first_received_at !== null) {
                    $earliest[$ref] = $row->first_received_at;
                }
            });

        return $earliest;
    }

    private function earliestWebhookReceivedAt(string $orderSn): ?CarbonInterface
    {
        if ($orderSn === '' || ! Schema::hasTable('marketplace_webhook_events')) {
            return null;
        }

        $value = MarketplaceWebhookEvent::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderSn)
            ->where('signature_ok', true)
            ->orderBy('received_at')
            ->value('received_at');

        return $this->castMoment($value);
    }

    private function earliestChannelOrderReceivedAt(string $orderSn): ?CarbonInterface
    {
        if ($orderSn === '' || ! Schema::hasTable('marketplace_channel_orders')) {
            return null;
        }

        $value = MarketplaceChannelOrder::query()
            ->where('channel', MarketplaceWebhookRecorder::CHANNEL_SHOPEE)
            ->where('order_ref', $orderSn)
            ->orderBy('first_received_at')
            ->value('first_received_at');

        return $this->castMoment($value);
    }

    /**
     * @param  Collection<int, MarketplaceOrderShadow>  $shadows
     * @return array<int, Jubelioorder>
     */
    private function loadJubelioOrdersForShadows(Collection $shadows): array
    {
        $ids = $shadows->pluck('jubelio_order_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $invoices = $shadows->pluck('jubelio_invoice')->filter()->unique()->values();

        $query = Jubelioorder::query()->where('type', 'SELL');

        $query->where(function ($q) use ($ids, $invoices, $shadows): void {
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
                $shopId = $shadow->shop_id !== null ? (int) $shadow->shop_id : null;
                $prefix = $this->shopeeInvoicePrefix->prefixForShopId($shopId > 0 ? $shopId : null);
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

        $shopId = $shadow->shop_id !== null ? (int) $shadow->shop_id : null;
        $prefix = $this->shopeeInvoicePrefix->prefixForShopId($shopId > 0 ? $shopId : null);
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
        CarbonInterface|string|null $webhookReceivedAt,
        CarbonInterface|string|null $channelOrderFirstReceivedAt,
        ?Jubelioorder $jubelio,
    ): array {
        [$shopeeAt, $shopeeSource] = $this->resolveShopeeReceivedAt(
            $shadow,
            $inboxCreatedAt,
            $webhookReceivedAt,
            $channelOrderFirstReceivedAt,
        );
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
        CarbonInterface|string|null $webhookReceivedAt,
        CarbonInterface|string|null $channelOrderFirstReceivedAt,
    ): array {
        $candidates = [];

        if ($inboxCreatedAt !== null) {
            $candidates[] = [$this->castMoment($inboxCreatedAt), 'inbox'];
        }
        if ($webhookReceivedAt !== null) {
            $candidates[] = [$this->castMoment($webhookReceivedAt), 'webhook'];
        }
        if ($channelOrderFirstReceivedAt !== null) {
            $candidates[] = [$this->castMoment($channelOrderFirstReceivedAt), 'channel_order'];
        }

        $best = null;
        foreach ($candidates as [$at, $source]) {
            if ($at === null) {
                continue;
            }
            if ($best === null || $at->getTimestamp() < $best[0]->getTimestamp()) {
                $best = [$at, $source];
            }
        }

        if ($best !== null) {
            return $best;
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