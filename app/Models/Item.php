<?php

namespace App\Models;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Support\FillsProductionColumnDefaults;
use App\Support\ItemImageResolver;
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
        'code',
        'legacy_code',
        'pcode',
        'brand',
        'type',
        'size',
        'genre',
        'price',
        'cost',
        'qty',
        'tag_ids',
        'description',
        'description2',
        'jubelio_item_id',
        'restock_urgent_threshold',
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
            'qty' => 'decimal:2',
            'jubelio_item_id' => 'integer',
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
        ];
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
     * Catalog colorway / notes. Grouped SKUs use item_group; items.description
     * is leftover per-row text and is not the source of truth.
     */
    public function catalogDescription(): string
    {
        if ($this->hasCatalogGroup()) {
            return trim((string) ($this->group->description ?? ''));
        }

        return trim((string) ($this->description ?? ''));
    }

    public function catalogDescription2(): string
    {
        if ($this->hasCatalogGroup()) {
            return trim((string) ($this->group->description2 ?? ''));
        }

        return trim((string) ($this->description2 ?? ''));
    }

    public function catalogBrand(): ItemBrand
    {
        if ($this->hasCatalogGroup()) {
            $groupBrand = $this->normalizeBrand($this->group->brand);

            if ($groupBrand !== ItemBrand::NO_BRAND) {
                return $groupBrand;
            }
        }

        return $this->normalizeBrand($this->brand);
    }

    public function catalogGenre(): int
    {
        if ($this->hasCatalogGroup() && (int) ($this->group->genre ?? 0) > 0) {
            return (int) $this->group->genre;
        }

        return (int) ($this->genre ?? 0);
    }

    public function hasCatalogGroup(): bool
    {
        return (int) $this->group_id > 0 && $this->group !== null;
    }

    private function normalizeBrand(mixed $brand): ItemBrand
    {
        if ($brand instanceof ItemBrand) {
            return $brand;
        }

        return ItemBrand::tryFrom((int) $brand) ?? ItemBrand::NO_BRAND;
    }

    public function getItemName(): string
    {
        if ($this->type === ItemType::ASSET_LANCAR || $this->type === ItemType::ASSET_TETAP) {
            return $this->name;
        }

        $alias = trim((string) ($this->group?->alias ?? ''));

        return $alias !== '' ? $alias : $this->name;
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
            $this->isAssetLancar() => route('assetlancar.show', $this),
            $this->isAssetTetap() => route('assettetap.show', $this),
            default => route('items.show', $this),
        };
    }

    public function editUrl(): string
    {
        return match (true) {
            $this->isAssetLancar() => route('assetlancar.edit', $this),
            $this->isAssetTetap() => route('assettetap.edit', $this),
            default => route('items.edit', $this),
        };
    }

    /**
     * Resolve an item by canonical code or preserved legacy SKU (Jubelio / imports).
     */
    public static function findBySku(string $sku): ?self
    {
        $normalized = strtoupper(trim($sku));

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->where(function (Builder $query) use ($normalized) {
                $query->whereRaw('UPPER(code) = ?', [$normalized])
                    ->orWhereRaw('UPPER(legacy_code) = ?', [$normalized]);
            })
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
     * Batch-resolve items keyed by uppercase SKU (matches code or legacy_code).
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

        $keyed = collect();

        foreach ($items as $item) {
            $keyed[strtoupper($item->code)] = $item;

            if ($item->legacy_code) {
                $keyed[strtoupper($item->legacy_code)] = $item;
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
