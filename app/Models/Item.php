<?php

namespace App\Models;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\FillsProductionColumnDefaults;
use App\Support\ItemCatalog;
use App\Support\ItemInventorySettings;
use App\Support\ItemImageResolver;
use App\Support\ItemPricing;
use App\Support\ItemProductTitle;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Item extends Model
{
    use FillsProductionColumnDefaults, HasFactory, SoftDeletes;

    const TYPE_ITEM = 1;

    const TYPE_ASSET_LANCAR = 2;

    const TYPE_ASSET_TETAP = 3;

    const TYPE_SERVICE = 5;

    protected $fillable = [
        'group_id',
        'name',
        'alias',
        'code',
        'legacy_code',
        'pcode',
        // Leftover mirrors of item_group (see ItemCatalog::LEFTOVER_ITEM_COLUMNS).
        'brand',
        'type',
        'size',
        'genre',
        'price',
        'cost',
        'cost_cnh',
        'qty',
        'tag_ids',
        'description',
        'description2',
        'reseller_price',
        'jubelio_item_id',
        'shopee_item_id',
        'shopee_model_id',
        'restock_urgent_threshold',
        'track_inventory',
        'allow_decimal_quantity',
    ];

    protected function casts(): array
    {
        return [
            'brand' => ItemBrand::class,
            'type' => ItemType::class,
            'size' => 'integer',
            'genre' => 'integer',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'cost_cnh' => 'decimal:2',
            'reseller_price' => 'decimal:2',
            'qty' => 'decimal:2',
            'track_inventory' => 'boolean',
            'allow_decimal_quantity' => 'boolean',
            'jubelio_item_id' => 'integer',
            'shopee_item_id' => 'integer',
            'shopee_model_id' => 'integer',
        ];
    }

    protected $appends = ['image_url', 'item_code'];

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'items-list',
            'create' => 'items-create',
            'edit' => 'items-edit',
            'delete' => 'items-delete',
            'convert-legacy' => 'items-convert-legacy',

            // Asset Lancar
            'asset-lancar-view' => 'assetLancar-list',
            'asset-lancar-create' => 'assetLancar-create',
            'asset-lancar-edit' => 'assetLancar-edit',
            'asset-lancar-delete' => 'assetLancar-delete',

            // Asset Tetap
            'asset-tetap-view' => 'assetTetap-list',
            'asset-tetap-create' => 'assetTetap-create',
            'asset-tetap-edit' => 'assetTetap-edit',
            'asset-tetap-delete' => 'assetTetap-delete',
            'asset-tetap-depreciate' => 'assetTetap-depreciate',

            'services-view' => 'services-list',
            'services-create' => 'services-create',
            'services-edit' => 'services-edit',
            'services-delete' => 'services-delete',
        ];
    }

    public function tracksInventory(): bool
    {
        $type = $this->type instanceof ItemType ? $this->type : ItemType::coerce($this->type);

        return $type !== ItemType::SERVICE;
    }

    public function allowsDecimalQuantity(): bool
    {
        $type = $this->type instanceof ItemType ? $this->type : ItemType::coerce($this->type);
        if ($type === null || ! ItemInventorySettings::decimalQuantityEnabledForType($type)) {
            return false;
        }

        return (bool) ($this->allow_decimal_quantity ?? false);
    }

    public function isService(): bool
    {
        return $this->type === ItemType::SERVICE;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tag');
    }

    public function warehouseItems(): HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    public function jubelioLinkAttempts(): HasMany
    {
        return $this->hasMany(JubelioItemLinkAttempt::class);
    }

    public function shopeeLinkAttempts(): HasMany
    {
        return $this->hasMany(ShopeeItemLinkAttempt::class);
    }

    public function latestJubelioLinkAttempt(): HasOne
    {
        return $this->hasOne(JubelioItemLinkAttempt::class)->latestOfMany();
    }

    public function depreciation(): HasOne
    {
        return $this->hasOne(Depreciation::class, 'item_id');
    }

    // Scopes
    public function scopeSearch(Builder $query, string $term): void
    {
        $contains = LikeSearch::contains($term);
        $prefix = LikeSearch::prefix($term);

        $query->where(function ($q) use ($term, $contains, $prefix) {
            $q->where('name', 'like', $contains)
                ->orWhere('code', 'like', $prefix)
                ->orWhere('legacy_code', 'like', $prefix)
                ->orWhere('pcode', 'like', $prefix);

            if (ctype_digit(trim($term))) {
                $q->orWhere('id', (int) trim($term));
            }
        });
    }

    public function scopeFilterIndexLookup(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $contains = LikeSearch::contains($term, allowPercentWildcards: true);
        if (LikeSearch::isMatchAll($contains)) {
            return;
        }

        $query->where(function ($q) use ($term, $contains) {
            $q->where($q->qualifyColumn('code'), 'like', $contains)
                ->orWhere($q->qualifyColumn('legacy_code'), 'like', $contains);

            if (ctype_digit($term)) {
                $q->orWhere($q->qualifyColumn('id'), (int) $term);
            }
        });
    }

    public function scopeFilterDisplayName(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $pattern = LikeSearch::containsInsensitive($term, allowPercentWildcards: true);
        if (LikeSearch::isMatchAll($pattern)) {
            return;
        }

        $itemName = $query->getGrammar()->wrap($query->qualifyColumn('name'));

        $query->where(function ($q) use ($pattern, $itemName) {
            $q->whereRaw("LOWER({$itemName}) LIKE ?", [$pattern])
                ->orWhereExists(function ($sub) use ($pattern) {
                    $sub->selectRaw('1')
                        ->from('item_group')
                        ->whereColumn('item_group.id', 'items.group_id')
                        ->where('items.group_id', '>', 0)
                        ->where(function ($group) use ($pattern) {
                            $group->whereRaw('LOWER(item_group.name) LIKE ?', [$pattern]);

                            if (Schema::hasColumn('item_group', 'alias')) {
                                $group->orWhereRaw('LOWER(item_group.alias) LIKE ?', [$pattern]);
                            }
                        });
                });
        });
    }

    public function scopeFilterDescription(Builder $query, string $term): void
    {
        $contains = LikeSearch::contains($term);
        if ($contains === '%') {
            return;
        }

        $query->where(function (Builder $q) use ($contains) {
            $q->whereExists(function ($sub) use ($contains) {
                $sub->selectRaw('1')
                    ->from('item_group')
                    ->whereColumn('item_group.id', 'items.group_id')
                    ->where('items.group_id', '>', 0)
                    ->where('item_group.description', 'like', $contains);
            })->orWhere(function (Builder $ungrouped) use ($contains) {
                $ungrouped
                    ->where(function (Builder $noGroup) {
                        $noGroup->whereNull('items.group_id')
                            ->orWhere('items.group_id', '<=', 0);
                    })
                    ->where($ungrouped->qualifyColumn('description'), 'like', $contains);
            });
        });
    }

    public function scopeFilterBrand(Builder $query, int $brand): void
    {
        ItemCatalog::constrainBrand($query, $brand);
    }

    public function scopeFilterByTags(Builder $query, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        // Optimized "Match All" strategy:
        // Filter items that have at least one of the tags,
        // then group by item and ensure the count of matching tags equals the count of requested tags.
        // This is generally more performant than multiple EXISTS subqueries for large datasets.

        $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        }, '=', count($tagIds));
    }

    // Helper Accessors (from legacy logic)
    public function getImageUrlAttribute(): string
    {
        return app(ItemImageResolver::class)->resolveUrlForItem($this);
    }

    public function getImagePathAttribute(): string
    {
        return app(ItemImageResolver::class)->resolveDiskPathForItem($this);
    }

    public function getItemCode(): string
    {
        return $this->code ?? $this->legacy_code ?? (string) $this->id;
    }

    public function getItemCodeAttribute(): string
    {
        return $this->getItemCode();
    }

    /**
     * Distinct preserved SKU for item detail display.
     * Empty values and copies of the current code are hidden to avoid confusion.
     */
    public function distinctLegacyCode(): ?string
    {
        $legacy = trim((string) ($this->legacy_code ?? ''));
        $code = trim((string) ($this->code ?? ''));

        if ($legacy === '' || strcasecmp($legacy, $code) === 0) {
            return null;
        }

        return $legacy;
    }

    /**
     * Catalog colorway / notes / brand / genre. Grouped SKUs use item_group;
     * leftover items.* columns are mirrors (ItemCatalog::MIRROR_ITEM_COLUMNS).
     */
    public function catalogDescription(): string
    {
        return ItemCatalog::description($this);
    }

    public function catalogDescription2(): string
    {
        return ItemCatalog::description2($this);
    }

    public function catalogResellerPrice(): float
    {
        return ItemCatalog::resellerPrice($this);
    }

    public function effectivePrice(): float
    {
        return ItemPricing::resolve($this, 'price');
    }

    public function effectiveCost(): float
    {
        return ItemPricing::resolve($this, 'cost');
    }

    public function effectiveCostCnh(): float
    {
        return ItemPricing::resolve($this, 'cost_cnh');
    }

    /**
     * Shape returned by GET /items?json=1 (transaction name autocomplete and similar).
     *
     * @return array<string, mixed>
     */
    public function toItemSearchJson(): array
    {
        $this->loadMissing(['warehouseItems', 'group']);

        $payload = $this->toArray();
        $payload['price'] = $this->effectivePrice();
        $payload['cost'] = $this->effectiveCost();
        $payload['name'] = $this->effectiveDisplayName();
        $payload['reseller_sell_price'] = $this->resellerSellPrice();
        $payload['track_inventory'] = $this->tracksInventory() && (bool) ($this->track_inventory ?? true);
        $payload['allow_decimal_quantity'] = $this->allowsDecimalQuantity();
        $payload['warehouse_item'] = $this->warehouseItems->map(fn ($wi) => [
            'warehouse_id' => (string) $wi->warehouse_id,
            'quantity' => (float) $wi->quantity,
        ])->values()->all();

        return $payload;
    }

    public function resellerSellPrice(): float
    {
        return ItemCatalog::sellPriceForReseller($this);
    }

    public function catalogBrand(): ItemBrand
    {
        return ItemCatalog::brand($this);
    }

    public function catalogGenre(): int
    {
        return ItemCatalog::genre($this);
    }

    public function hasCatalogGroup(): bool
    {
        return (int) $this->group_id > 0 && $this->group !== null;
    }

    public function getItemName(): string
    {
        $stored = trim((string) $this->name);

        if ($stored !== '' && ItemProductTitle::shouldPreferStoredDisplayName($this)) {
            return $stored;
        }

        $built = ItemProductTitle::buildDisplayName($this);
        if ($built !== '') {
            return $built;
        }

        return $stored;
    }

    public function effectiveDisplayName(): string
    {
        return $this->getItemName();
    }

    public function isAssetLancar(): bool
    {
        return $this->type === ItemType::ASSET_LANCAR;
    }

    public function isAssetTetap(): bool
    {
        return $this->type === ItemType::ASSET_TETAP;
    }

    public function showUrl(): string
    {
        return match (true) {
            $this->isService() => route('services.show', $this),
            $this->isAssetLancar() => route('assetlancar.show', $this),
            $this->isAssetTetap() => route('assettetap.show', $this),
            default => route('items.show', $this),
        };
    }

    public function editUrl(): string
    {
        return match (true) {
            $this->isService() => route('services.edit', $this),
            $this->isAssetLancar() => route('assetlancar.edit', $this),
            $this->isAssetTetap() => route('assettetap.edit', $this),
            default => route('items.edit', $this),
        };
    }

    public function groupParentUrl(): ?string
    {
        if ((int) $this->group_id <= 0) {
            return null;
        }

        return route('items.group-parent-detail', $this->group_id);
    }

    public function colorwayEditUrl(): ?string
    {
        if ((int) $this->group_id <= 0) {
            return null;
        }

        return route('items.colorway-edit', $this->group_id);
    }

    /**
     * Parent group title for item lists: stored parent product name, else TYPE + master label.
     */
    public function listParentGroupLabel(): string
    {
        $this->loadMissing(['group', 'tags']);

        $builder = app(ItemIdentityBuilder::class);
        $parentKey = $builder->itemParentKey($this);
        $parentTitle = ItemProductTitle::parentProductNameForKey($parentKey);
        if ($parentTitle !== '') {
            return $parentTitle;
        }

        return $builder->itemParentLabel($this);
    }

    /** Parent master label (TYPE + pcode) for list link tooltips. */
    public function listParentGroupLinkTitle(): string
    {
        $this->loadMissing(['group', 'tags']);

        return app(ItemIdentityBuilder::class)->itemParentLabel($this);
    }

    /**
     * Colorway label for item lists: custom colorway product name or colorway pcode.
     */
    public function listColorwayLabel(): string
    {
        $this->loadMissing(['group', 'tags']);

        $itemType = $this->type instanceof ItemType ? $this->type : ItemType::coerce($this->type) ?? ItemType::ITEM;
        $pcode = strtoupper(trim((string) $this->pcode));
        $group = $this->group;

        if ($group === null) {
            return $pcode !== '' ? $pcode : '—';
        }

        $storedName = trim((string) $group->name);
        if ($storedName !== '' && ! ItemProductTitle::isPcodePlaceholderName($itemType, $storedName, $pcode)) {
            return strtoupper(app(ItemIdentityBuilder::class)->productDisplayName(
                $itemType,
                $storedName,
                (string) ($group->variant ?? ''),
                (string) ($group->master ?? ''),
            ));
        }

        if ($pcode !== '') {
            return $pcode;
        }

        return $storedName !== '' ? strtoupper($storedName) : '—';
    }

    /**
     * Resolve an item by preserved legacy SKU first, then canonical code (Jubelio / imports).
     */
    public static function findBySku(string $sku): ?self
    {
        $normalized = strtoupper(trim($sku));

        if ($normalized === '') {
            return null;
        }

        $byLegacy = static::query()
            ->whereRaw('UPPER(legacy_code) = ?', [$normalized])
            ->first();

        if ($byLegacy) {
            return $byLegacy;
        }

        return static::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();
    }

    /**
     * Resolve an item by SKU (code / legacy_code), then exact name when SKU misses.
     * Name match requires a single row — ambiguous duplicates return null.
     */
    public static function findBySkuOrName(string $value): ?self
    {
        $item = static::findBySku($value);
        if ($item) {
            return $item;
        }

        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return null;
        }

        $matches = static::query()
            ->whereRaw('UPPER(name) = ?', [$normalized])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Batch-resolve items keyed by uppercase SKU (legacy_code match, then code).
     *
     * @param  array<int, string>  $skus
     * @return Collection<string, self>
     */
    public static function findManyBySkus(array $skus): Collection
    {
        $normalized = collect($skus)
            ->map(fn ($sku) => strtoupper(trim((string) $sku)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return collect();
        }

        $items = static::query()
            ->where(function (Builder $query) use ($normalized) {
                $query->whereIn(DB::raw('UPPER(code)'), $normalized)
                    ->orWhereIn(DB::raw('UPPER(legacy_code)'), $normalized);
            })
            ->get(['id', 'code', 'legacy_code', 'name']);

        $needles = array_fill_keys($normalized, true);
        $legacyMap = [];
        $codeMap = [];

        foreach ($items as $item) {
            $legacyKey = strtoupper(trim((string) ($item->legacy_code ?? '')));
            if ($legacyKey !== '' && isset($needles[$legacyKey])) {
                $legacyMap[$legacyKey] = $item;
            }

            $codeKey = strtoupper(trim((string) $item->code));
            if ($codeKey !== '' && isset($needles[$codeKey])) {
                $codeMap[$codeKey] = $item;
            }
        }

        $keyed = collect();
        foreach ($normalized as $sku) {
            $item = $legacyMap[$sku] ?? $codeMap[$sku] ?? null;
            if ($item) {
                $keyed[$sku] = $item;
            }
        }

        return $keyed;
    }

    public function scopeWhereSku(Builder $query, string $sku): Builder
    {
        $normalized = strtoupper(trim($sku));

        return $query->where(function (Builder $inner) use ($normalized) {
            $inner->whereRaw('UPPER(code) = ?', [$normalized])
                ->orWhereRaw('UPPER(legacy_code) = ?', [$normalized]);
        });
    }
}
