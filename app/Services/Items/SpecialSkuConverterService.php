<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemIdentityConversionRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SpecialSkuConverterService
{
    public const PAGE_SIZE = 500;

    public function __construct(
        private readonly SpecialSkuConverterRules $rules,
        private readonly SpecialSkuIdentityParser $parser,
        private readonly LegacyItemConverterService $legacyConverter,
    ) {}

    public function candidateQuery(bool $withRelations = false): Builder
    {
        $query = Item::query()
            ->whereNull('deleted_at')
            ->where('type', ItemType::ASSET_LANCAR)
            ->where(function (Builder $query) {
                foreach (SpecialSkuConverterRules::pcodePrefixes() as $prefix) {
                    $query->orWhere('code', 'like', $prefix.'%');
                }
            })
            ->orderByDesc('items.id');

        if ($withRelations) {
            $query->with(['tags', 'group']);
        }

        return $query;
    }

    public function isPendingConversion(Item $item): bool
    {
        return $this->legacyConverter->isPendingConversion($item);
    }

    public function isEligible(Item $item): bool
    {
        if (! $this->isPendingConversion($item)) {
            return false;
        }

        return $this->rules->matchesLegacyCode((string) $item->code);
    }

    public function countEligible(): int
    {
        $count = 0;

        $this->candidateQuery()
            ->select(['id', 'code', 'legacy_code'])
            ->chunkByIdDesc(500, function ($items) use (&$count) {
                foreach ($items as $item) {
                    if ($this->isEligible($item)) {
                        $count++;
                    }
                }
            }, 'id');

        return $count;
    }

    public function paginatePending(?int $total = null): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query('page', 1));
        $total ??= $this->countEligible();
        $pageItems = $this->eligibleItemsForPage($page, self::PAGE_SIZE);

        return new LengthAwarePaginator(
            $pageItems,
            $total,
            self::PAGE_SIZE,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @return Collection<int, Item>
     */
    public function eligibleItemsForPage(int $page, int $perPage = self::PAGE_SIZE): Collection
    {
        $page = max(1, $page);
        $start = ($page - 1) * $perPage;
        $eligibleIndex = 0;
        $pageItemIds = [];

        $this->candidateQuery()
            ->select(['id', 'code', 'legacy_code'])
            ->chunkByIdDesc(500, function ($items) use ($start, $perPage, &$eligibleIndex, &$pageItemIds) {
                foreach ($items as $item) {
                    if (! $this->isEligible($item)) {
                        continue;
                    }

                    if ($eligibleIndex >= $start && count($pageItemIds) < $perPage) {
                        $pageItemIds[] = $item->id;
                    }

                    $eligibleIndex++;
                }

                return count($pageItemIds) < $perPage;
            }, 'id');

        if ($pageItemIds === []) {
            return collect();
        }

        return $this->candidateQuery(withRelations: true)
            ->whereIn('items.id', $pageItemIds)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, $pageItemIds, true))
            ->values();
    }

    /**
     * @return Collection<int, array{item: Item, parse: LegacyParseResult}>
     */
    public function previewItems(Collection $items): Collection
    {
        return $items
            ->map(fn (Item $item) => $item->loadMissing(['tags', 'group']))
            ->map(fn (Item $item) => [
                'item' => $item,
                'parse' => $this->parser->parse($item),
            ]);
    }

    /**
     * @return Collection<int, Item>
     */
    public function itemsForIds(array $itemIds): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return $this->candidateQuery(withRelations: true)
            ->whereIn('items.id', $itemIds)
            ->get()
            ->filter(fn (Item $item) => $this->isEligible($item))
            ->sortBy(fn (Item $item) => array_search($item->id, $itemIds, true))
            ->values();
    }

    public function runItems(Collection $items, User $user, bool $dryRun = false): ItemIdentityConversionRun
    {
        $items = $items
            ->filter(fn (Item $item) => $this->isEligible($item))
            ->values();

        $run = ItemIdentityConversionRun::query()->create([
            'item_type' => ItemType::ASSET_LANCAR,
            'dry_run' => $dryRun,
            'batch_size' => $items->count(),
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        foreach ($items as $item) {
            $parse = $this->parser->parse($item->fresh(['tags', 'group']));
            $this->legacyConverter->convertWithParse($item, $run, $parse, $dryRun);
        }

        $run->update(['finished_at' => now()]);

        return $run->fresh(['results']);
    }
}
