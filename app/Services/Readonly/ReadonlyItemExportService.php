<?php

namespace App\Services\Readonly;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemPricing;
use App\Support\ItemProductTitle;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReadonlyItemExportService
{
    public function __construct(
        private readonly ItemIdentityBuilder $identityBuilder,
        private readonly ReadonlyLastBuyResolver $lastBuys,
        private readonly ReadonlyCostFlagResolver $costFlags,
    ) {}

    public function baseQuery(Request $request): Builder
    {
        $query = Item::query()->with(['group', 'tags']);

        $type = strtolower((string) $request->query('type', 'all'));
        match ($type) {
            'item' => $query->where('type', ItemType::ITEM->value),
            'assetlancar' => $query->where('type', ItemType::ASSET_LANCAR->value),
            'all' => $query->whereIn('type', [
                ItemType::ITEM->value,
                ItemType::ASSET_LANCAR->value,
                ItemType::ASSET_TETAP->value,
                ItemType::SERVICE->value,
            ]),
            default => abort(422, 'Invalid type filter.'),
        };

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $contains = LikeSearch::contains($search, allowPercentWildcards: true);
            $query->where(function (Builder $outer) use ($search, $contains): void {
                $outer->where(function (Builder $q) use ($search): void {
                    $q->search($search);
                });

                if (! LikeSearch::isMatchAll($contains)) {
                    $outer->orWhere(function (Builder $q) use ($search): void {
                        $q->filterDisplayName($search);
                    })
                        ->orWhere('items.pcode', 'like', $contains)
                        ->orWhere('items.alias', 'like', $contains);
                }
            });
        }

        if ($request->filled('updated_since')) {
            $since = Carbon::parse((string) $request->query('updated_since'))->startOfDay();
            $query->where('items.updated_at', '>=', $since);
        }

        return $query->orderBy('items.id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rowFromItem(Item $item, ?array $lastBuy = null): array
    {
        $item->loadMissing(['group', 'tags']);

        $type = ItemType::coerce($item->type);
        $baseCost = $item->effectiveCost();
        $price = $item->effectivePrice();
        $jubelioId = (int) ($item->jubelio_item_id ?? 0);

        $lastBuy ??= null;
        $lastBuyPrice = $lastBuy['unit_price'] ?? null;
        $lastBuyDate = $lastBuy['date'] ?? null;

        return [
            'id' => $item->id,
            'type' => $type?->label() ?? 'Unknown',
            'code' => (string) $item->code,
            'barcode' => (string) ($item->alias !== '' && $item->alias !== null
                ? $item->alias
                : ($item->legacy_code ?: (string) $item->id)),
            'group_code' => $this->identityBuilder->itemParentKey($item),
            'name' => ItemProductTitle::resolveBareTitle($item),
            'display_name' => $item->effectiveDisplayName(),
            'colorway' => $item->listColorwayLabel(),
            'parent_group' => $item->listParentGroupLabel(),
            'price' => $price,
            'ongkir' => (float) ($item->ongkir ?? 0),
            'base_cost' => $baseCost,
            'cost_updated_at' => $item->updated_at?->toIso8601String(),
            'cost_updated_at_source' => 'items.updated_at',
            'cost_source' => $this->resolveCostSource($item, $baseCost, $lastBuyPrice),
            'last_buy_price' => $lastBuyPrice,
            'last_buy_date' => $lastBuyDate,
            'qty_total' => (float) $item->qty,
            'jubelio_item_id' => $jubelioId > 0 ? $jubelioId : null,
            'jubelio_linked' => $jubelioId > 0,
            'shopee_item_id' => $this->nullableInt($item->shopee_item_id),
            'shopee_model_id' => $this->nullableInt($item->shopee_model_id),
            'is_active' => $item->deleted_at === null,
            'cost_flag' => $this->costFlags->flag($baseCost, $price),
        ];
    }

    /**
     * @param  iterable<int, Item>  $items
     * @return list<array<string, mixed>>
     */
    public function rowsForItems(iterable $items): array
    {
        $collection = collect($items);
        $ids = $collection->pluck('id')->map(fn ($id) => (int) $id)->all();
        $lastBuyMap = $this->lastBuys->forItems($ids);

        return $collection
            ->map(fn (Item $item) => $this->rowFromItem($item, $lastBuyMap[$item->id] ?? null))
            ->values()
            ->all();
    }

    private function resolveCostSource(Item $item, float $baseCost, ?float $lastBuyPrice): string
    {
        if ($baseCost <= 0) {
            return 'missing';
        }

        $scope = ItemPricing::detectScope($item, 'cost');
        if ($scope === ItemPricing::SCOPE_SIZE) {
            return 'manual';
        }

        if ($scope === ItemPricing::SCOPE_GROUP) {
            return 'group_default';
        }

        if ($lastBuyPrice !== null && abs($lastBuyPrice - $baseCost) < 0.01) {
            return 'purchase';
        }

        return 'unknown';
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
