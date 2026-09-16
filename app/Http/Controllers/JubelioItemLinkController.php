<?php

namespace App\Http\Controllers;

use App\Models\ItemGroup;
use App\Models\Jubelio;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Jubelio\JubelioItemLinkCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioItemLinkController extends Controller
{
    public function __construct(
        protected ItemGroupHierarchyService $groupHierarchy,
    ) {}
    public function index(Request $request, JubelioItemLinkCheckService $service): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $view = $request->query('view', 'group') === 'items' ? 'items' : 'group';

        if ($view === 'items') {
            $filters = [
                'q' => $request->query('q', ''),
                'link' => $request->query('link', 'all'),
            ];

            return view('jubelio.item-links.index', [
                'view' => 'items',
                'items' => $service->paginateItems($filters, 25),
                'filters' => $filters,
                'parents' => null,
                'flash' => ['success' => session('success'), 'error' => session('error')],
            ]);
        }

        $filters = [
            'kode' => $request->query('kode', ''),
            'product_name' => $request->query('product_name', ''),
            'desc' => $request->query('desc', ''),
        ];

        return view('jubelio.item-links.index', [
            'view' => 'group',
            'parents' => $service->paginateParentGroups($filters, 20),
            'filters' => $filters,
            'items' => null,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function showGroup(ItemGroup $group, JubelioItemLinkCheckService $service): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $detail = $this->groupHierarchy->parentDetailForAnchorGroup($group, fetchJubelio: false);

        abort_if($detail === null, 404);

        $rows = $service->skuRowsForParentAnchor($group);
        $linked = collect($rows)->where('linked', true)->count();
        $total = count($rows);

        return view('jubelio.item-links.group', [
            'group' => $group,
            'detail' => $detail,
            'rows' => $rows,
            'stats' => [
                'linked' => $linked,
                'unlinked' => max(0, $total - $linked),
                'total' => $total,
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
