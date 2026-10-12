<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\Items\ItemGroupHierarchyService;
use App\Support\ItemCatalogHubMockPresenter;
use App\Support\ItemPricing;
use Illuminate\Support\Facades\Gate;

class ItemCatalogMockController extends Controller
{
    public function __construct(
        protected ItemGroupHierarchyService $groupHierarchy,
        protected ItemCatalogHubMockPresenter $presenter,
    ) {}

    public function index()
    {
        Gate::authorize(\App\Models\ItemGroup::getPermissions()['view']);

        return view('items.mock.catalog-hub-index', [
            'scenarios' => ItemCatalogHubMockPresenter::staticScenarios(),
        ]);
    }

    public function parentHub(ItemGroup $group)
    {
        Gate::authorize(\App\Models\ItemGroup::getPermissions()['view']);

        $detail = $this->groupHierarchy->parentDetailForAnchorGroup($group, fetchJubelio: false);

        abort_if($detail === null, 404);

        $sampleGroup = ItemGroup::query()->find($detail['anchor_group_id']);
        $itemType = $detail['item_type'];

        return view('items.mock.parent-hub', [
            'detail' => $detail,
            'colorwayCatalogRows' => $this->presenter->colorwayCatalogRows($detail),
            'canEditGroup' => auth()->user()->can(\App\Models\ItemGroup::getPermissions()['edit']),
            'parentPricingState' => ItemPricing::formStateForParent(
                $detail['parent_key'],
                Item::query()->whereIn('group_id', $detail['group_ids'])->with('group')->first(),
            ),
            'parentBrand' => $sampleGroup?->brand,
            'parentGenre' => (int) ($sampleGroup?->genre ?? 0),
        ]);
    }

    public function colorwayEdit(ItemGroup $group)
    {
        Gate::authorize(\App\Models\ItemGroup::getPermissions()['view']);

        $context = $this->presenter->colorwayMockContext($group);

        abort_if($context === null, 404);

        return view('items.mock.colorway-edit', array_merge($context, [
            'canEdit' => auth()->user()->can(\App\Models\ItemGroup::getPermissions()['edit']),
        ]));
    }
}
