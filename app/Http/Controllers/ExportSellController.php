<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Report;
use App\Services\ExportSellExportService;
use App\Services\ExportSellQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ExportSellController extends Controller
{
    public function index(Request $request, ExportSellQueryService $queryService)
    {
        Gate::authorize(Report::getPermissions()['view-export-sell']);

        $perPage = $queryService->resolvePerPage($request);
        $filters = $queryService->filtersFromRequest($request);
        $rows = $queryService
            ->buildQuery($request, Auth::user())
            ->paginate($perPage)
            ->withQueryString();

        return view('transactions.export-sell', [
            'rows' => $rows,
            'filters' => $filters,
            'perPage' => $perPage,
            'typeOptions' => $queryService->typeOptions(),
        ]);
    }

    public function export(Request $request, ExportSellQueryService $queryService, ExportSellExportService $exportService)
    {
        Gate::authorize(Report::getPermissions()['view-export-sell']);

        $rows = $queryService
            ->buildQuery($request, Auth::user())
            ->get();

        return $exportService->download($rows);
    }

    public static function itemShowUrl(?ItemType $type, int $itemId): string
    {
        if ($type === ItemType::ASSET_LANCAR) {
            return route('assetlancar.show', $itemId);
        }

        return route('items.show', $itemId);
    }

    public static function addrbookShowUrl(?\App\Models\Addrbook $addrbook): ?string
    {
        if (! $addrbook) {
            return null;
        }

        return route('addrbook.type.show', [$addrbook->type_slug, $addrbook->id]);
    }
}
