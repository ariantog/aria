<?php

namespace App\Support;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\Items\ItemIdentityBuilder;

/**
 * Read-only data for catalog UX mock pages (no writes).
 *
 * @see doc/item-catalog-hierarchy.md
 */
final class ItemCatalogHubMockPresenter
{
    public function __construct(
        private ItemIdentityBuilder $identityBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $detail  From ItemGroupHierarchyService::parentDetailForAnchorGroup
     * @return list<array<string, mixed>>
     */
    public function colorwayCatalogRows(array $detail): array
    {
        $rows = [];
        $parentTitle = ItemProductTitle::parentProductNameForKey((string) $detail['parent_key']);
        $itemType = $detail['item_type'] instanceof ItemType
            ? $detail['item_type']
            : ItemType::coerce($detail['item_type']) ?? ItemType::ITEM;

        foreach ($detail['colors'] as $color) {
            $groupId = (int) ($color['group_id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $sample = Item::query()
                ->where('group_id', $groupId)
                ->whereNull('deleted_at')
                ->with(['group', 'tags'])
                ->orderBy('id')
                ->first();

            if ($sample === null) {
                continue;
            }

            $titleForm = ItemCatalogTitleForm::forItem($sample, $this->identityBuilder);
            $source = $this->titleSourceLabel($sample, $titleForm, $parentTitle);
            $sampleDisplay = ItemProductTitle::buildDisplayName($sample);

            $rows[] = [
                'group_id' => $groupId,
                'warna_code' => $color['code'] ?? '',
                'warna_name' => $color['name'] ?? '',
                'pcode' => $color['pcode'] ?? (string) $sample->pcode,
                'image_url' => $color['image_url'] ?? null,
                'effective_title' => $titleForm['effective_title'],
                'stored_colorway_title' => $titleForm['stored_title'],
                'title_source' => $source['key'],
                'title_source_label' => $source['label'],
                'inherits_parent' => $titleForm['inherits_parent'],
                'uses_placeholder' => $titleForm['uses_placeholder'],
                'sample_sku_code' => $color['size_rows'][0]['code'] ?? ($color['no_size_items'][0]['code'] ?? ''),
                'sample_display_name' => $sampleDisplay,
                'size_count' => count($color['size_rows'] ?? []) + count($color['no_size_items'] ?? []),
            ];
        }

        return $rows;
    }

    /**
     * Static scenarios for the mock index (no database).
     *
     * @return list<array<string, mixed>>
     */
    /**
     * @return list<array{key: string, label: string, summary: string}>
     */
    public static function parentVariantCatalog(): array
    {
        return [
            ['key' => 'a', 'label' => 'A · Tabbed hub', 'summary' => 'Separate tabs for colorways, style defaults, overview.'],
            ['key' => 'b', 'label' => 'B · Colorway-first', 'summary' => 'Thin style header; focus on color list + title column (near live page).'],
            ['key' => 'c', 'label' => 'C · Single scroll', 'summary' => 'One page with scope chips (style vs colorway).'],
            ['key' => 'd', 'label' => 'D · Live + labels', 'summary' => 'Current parent layout; renamed fields only.'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, summary: string}>
     */
    public static function colorwayVariantCatalog(): array
    {
        return [
            ['key' => 'a', 'label' => 'A · Split panels', 'summary' => 'Identity vs catalog + live name preview.'],
            ['key' => 'b', 'label' => 'B · Matrix first', 'summary' => 'Size price grid on top.'],
            ['key' => 'c', 'label' => 'C · Scope chips', 'summary' => 'Single form; each field tagged size vs colorway.'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, summary: string}>
     */
    public static function skuVariantCatalog(): array
    {
        return [
            ['key' => 'a', 'label' => 'A · SKU-only (current)', 'summary' => 'Links to parent/colorway; price/restock here.'],
            ['key' => 'b', 'label' => 'B · Inherit readout', 'summary' => 'Shows style → colorway → effective title before SKU fields.'],
        ];
    }

    public static function staticScenarios(): array
    {
        return [
            [
                'key' => 'no-marketing-title',
                'heading' => 'New colorway — no marketing title',
                'parent_title' => '',
                'colorway_title' => '',
                'style_code' => 'CX90233-23',
                'warna' => 'BLUE',
                'size' => 'M',
                'title_source_label' => 'Style code fallback',
                'preview' => 'CX90233-23 - BLUE - M',
                'helper' => 'Staff set pcode only; lists show style code until parent or colorway title is filled.',
            ],
            [
                'key' => 'inherits-parent',
                'heading' => 'Colorway inherits parent title',
                'parent_title' => 'RUNNING SHIRT',
                'colorway_title' => '',
                'style_code' => 'CX90233-23',
                'warna' => 'BLUE',
                'size' => 'M',
                'title_source_label' => 'Inherits parent',
                'preview' => 'RUNNING SHIRT - BLUE - M',
                'helper' => 'Colorway title field empty; effective bare title comes from item_parent_prices.product_name.',
            ],
            [
                'key' => 'colorway-override',
                'heading' => 'Colorway-specific title',
                'parent_title' => 'RUNNING SHIRT',
                'colorway_title' => 'LIMITED RUN',
                'style_code' => 'CX90233-23',
                'warna' => 'BLUE',
                'size' => 'M',
                'title_source_label' => 'Custom colorway',
                'preview' => 'LIMITED RUN - BLUE - M',
                'helper' => 'Only this color uses LIMITED RUN; sibling colorways can still inherit RUNNING SHIRT.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $titleForm
     * @return array{key: string, label: string}
     */
    private function titleSourceLabel(Item $item, array $titleForm, string $parentTitle): array
    {
        $alias = trim((string) ($item->getAttributes()['alias'] ?? ''));
        if ($alias !== '') {
            return ['key' => 'sku', 'label' => 'SKU override'];
        }

        if (! $titleForm['uses_placeholder'] && ($titleForm['stored_title'] ?? '') !== '') {
            return ['key' => 'colorway', 'label' => 'Custom colorway'];
        }

        if ($titleForm['inherits_parent'] || ($parentTitle !== '' && $titleForm['uses_placeholder'])) {
            return ['key' => 'parent', 'label' => 'Inherits parent'];
        }

        return ['key' => 'pcode', 'label' => 'Style code fallback'];
    }

    public function colorwayMockContext(ItemGroup $group): ?array
    {
        $group->load(['items.tags']);
        $items = $group->items->sortBy(fn (Item $item) => $this->identityBuilder->itemSizeCode($item) ?? '')->values();
        $sample = $items->first();

        if ($sample === null) {
            return null;
        }

        $titleForm = ItemCatalogTitleForm::forItem($sample, $this->identityBuilder);
        $color = $this->identityBuilder->itemColorInfo($sample);
        $parentGroupId = app(\App\Services\Items\ItemGroupHierarchyService::class)
            ->anchorGroupIdForParentKey($this->identityBuilder->itemParentKey($sample)) ?? $group->id;

        $parentKey = $this->identityBuilder->itemParentKey($sample);
        $parentProductName = ItemProductTitle::parentProductNameForKey($parentKey);

        $previewRows = $items->map(function (Item $item) {
            $sizeTag = $item->tags->firstWhere('type', \App\Models\Tag::TYPE_SIZE);

            return [
                'code' => $item->code,
                'size_code' => $sizeTag?->code ?? '—',
            ];
        })->values()->all();

        return [
            'group' => $group,
            'sample' => $sample,
            'titleForm' => $titleForm,
            'color' => $color,
            'parentGroupId' => $parentGroupId,
            'parentProductName' => $parentProductName,
            'productTitle' => $titleForm['stored_title'],
            'usesPlaceholder' => $titleForm['uses_placeholder'],
            'previewRows' => $previewRows,
            'isAsset' => $sample->type === ItemType::ASSET_LANCAR,
        ];
    }
}
