<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemCatalogHubMockPresenter;
use App\Support\ItemCatalogTitleForm;
use App\Support\ItemPricing;
use Illuminate\Support\Facades\Gate;

class ItemCatalogMockController extends Controller
{
    public function __construct(
        protected ItemGroupHierarchyService $groupHierarchy,
        protected ItemCatalogHubMockPresenter $presenter,
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    public function index()
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        return view('items.mock.catalog-hub-index', [
            'scenarios' => ItemCatalogHubMockPresenter::staticScenarios(),
            'parentVariants' => ItemCatalogHubMockPresenter::parentVariantCatalog(),
            'colorwayVariants' => ItemCatalogHubMockPresenter::colorwayVariantCatalog(),
            'skuVariants' => ItemCatalogHubMockPresenter::skuVariantCatalog(),
        ]);
    }

    public function parentHub(ItemGroup $group)
    {
        return $this->renderParentHub($group, 'a');
    }

    public function parentHubVariant(ItemGroup $group, string $variant)
    {
        return $this->renderParentHub($group, strtolower($variant));
    }

    public function colorwayEdit(ItemGroup $group)
    {
        return $this->renderColorwayMock($group, 'a');
    }

    public function colorwayEditVariant(ItemGroup $group, string $variant)
    {
        return $this->renderColorwayMock($group, strtolower($variant));
    }

    public function skuEdit(Item $item)
    {
        return $this->renderSkuMock($item, 'a');
    }

    public function skuEditVariant(Item $item, string $variant)
    {
        return $this->renderSkuMock($item, strtolower($variant));
    }

    protected function renderParentHub(ItemGroup $group, string $variant)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $variant = $this->normalizeParentVariant($variant);
        $payload = $this->parentHubPayload($group);

        $view = match ($variant) {
            'b' => 'items.mock.parent-hub-b',
            'c' => 'items.mock.parent-hub-c',
            'd' => 'items.mock.parent-hub-d',
            default => 'items.mock.parent-hub',
        };

        return view($view, array_merge($payload, [
            'mockVariant' => $variant,
        ]));
    }

    protected function renderColorwayMock(ItemGroup $group, string $variant)
    {
        Gate::authorize(ItemGroup::getPermissions()['view']);

        $variant = $this->normalizeColorwayVariant($variant);
        $context = $this->presenter->colorwayMockContext($group);

        abort_if($context === null, 404);

        $view = match ($variant) {
            'b' => 'items.mock.colorway-edit-b',
            'c' => 'items.mock.colorway-edit-c',
            default => 'items.mock.colorway-edit',
        };

        return view($view, array_merge($context, [
            'canEdit' => auth()->user()->can(ItemGroup::getPermissions()['edit']),
            'mockVariant' => $variant,
        ]));
    }

    protected function renderSkuMock(Item $item, string $variant)
    {
        $p = Item::getPermissions();
        Gate::authorize($item->type === \App\Enums\ItemType::ASSET_LANCAR ? $p['asset-lancar-view'] : $p['view']);

        $variant = $this->normalizeSkuVariant($variant);
        $item->load(['group', 'tags']);
        $titleForm = ItemCatalogTitleForm::forItem($item, $this->identityBuilder);
        $parentKey = $this->identityBuilder->itemParentKey($item);
        $anchorGroupId = $item->group_id > 0
            ? ($this->groupHierarchy->anchorGroupIdForParentKey($parentKey) ?? $item->group_id)
            : null;

        return view('items.mock.sku-edit', [
            'item' => $item,
            'titleForm' => $titleForm,
            'parentProductName' => $titleForm['parent_title'],
            'parentGroupUrl' => $anchorGroupId ? route('items.group-parent-detail', $anchorGroupId) : null,
            'colorwayEditUrl' => $item->group_id > 0 ? route('items.colorway-edit', $item->group_id) : null,
            'activeVariant' => $variant,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parentHubPayload(ItemGroup $group): array
    {
        $detail = $this->groupHierarchy->parentDetailForAnchorGroup($group, fetchJubelio: false);

        abort_if($detail === null, 404);

        $sampleGroup = ItemGroup::query()->find($detail['anchor_group_id']);

        return [
            'detail' => $detail,
            'colorwayCatalogRows' => $this->presenter->colorwayCatalogRows($detail),
            'canEditGroup' => auth()->user()->can(ItemGroup::getPermissions()['edit']),
            'parentPricingState' => ItemPricing::formStateForParent(
                $detail['parent_key'],
                Item::query()->whereIn('group_id', $detail['group_ids'])->with('group')->first(),
            ),
            'parentBrand' => $sampleGroup?->brand,
            'parentGenre' => (int) ($sampleGroup?->genre ?? 0),
        ];
    }

    protected function normalizeParentVariant(string $variant): string
    {
        return in_array($variant, ['a', 'b', 'c', 'd'], true) ? $variant : 'a';
    }

    protected function normalizeColorwayVariant(string $variant): string
    {
        return in_array($variant, ['a', 'b', 'c'], true) ? $variant : 'a';
    }

    protected function normalizeSkuVariant(string $variant): string
    {
        return in_array($variant, ['a', 'b'], true) ? $variant : 'a';
    }
}
