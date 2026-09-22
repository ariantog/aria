<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExportSellController;
use App\Models\Item;
use App\Models\Report;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\InventoryHealth\InventoryHealthQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InventoryHealthController extends Controller
{
    public function index(Request $request, InventoryHealthQueryService $queryService): View
    {
        Gate::authorize(Report::getPermissions()['view-inventory-health']);

        $filters = $queryService->filtersFromRequest($request);
        $windows = $queryService->resolveWindows($request);
        $sort = $queryService->resolveSort($request);
        $rows = $queryService->paginate($request, Auth::user());
        $meta = $queryService->pageMeta($request);

        return view('reports.inventory-health', [
            'rows' => $rows,
            'filters' => $filters,
            'windows' => $windows,
            'sort' => $sort['column'],
            'direction' => $sort['direction'],
            'source' => $meta['source'],
            'syncedAt' => $meta['synced_at'],
            'stale' => $meta['stale'],
            'hasSnapshots' => $meta['has_snapshots'],
            'perPage' => $queryService->resolvePerPage($request),
            'typeOptions' => $queryService->typeOptions(),
            'statusOptions' => InventoryHealthClassifier::statusOptions(),
            'warehouseOptions' => $queryService->warehouseOptionsForFilter(Auth::user()),
            'selectedWarehouseId' => $filters['warehouse_id'] ?? '',
            'itemLookupUrl' => route('items.index'),
            'selectedItem' => $this->resolveSelectedItem($filters['item_id'] ?? null),
        ]);
    }

    public static function itemShowUrl(?ItemType $type, int $itemId): string
    {
        return ExportSellController::itemShowUrl($type, $itemId);
    }

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function resolveSelectedItem(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $term = trim((string) $value);
        if ($term === '' || ! ctype_digit($term)) {
            return null;
        }

        $item = Item::query()->find((int) $term);
        if (! $item) {
            return null;
        }

        $code = $item->code ?: (string) $item->id;
        $name = trim($code.' — '.$item->name, ' —');

        return [
            'id' => $item->id,
            'name' => $name,
            'code' => $item->code,
        ];
    }
}
