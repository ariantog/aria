<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Report;
use App\Services\FixedAssetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssetTetapReportController extends Controller
{
    public function __invoke(Request $request, FixedAssetService $fixedAssets)
    {
        Gate::authorize(Report::getPermissions()['view-asset-tetap']);

        $now = Carbon::now();
        $month = $request->query('month');
        $year = (int) ($request->query('year') ?? $now->year);

        if ($month && $month !== 'all') {
            $asOf = Carbon::createFromDate($year, (int) $month, 1)->endOfMonth();
        } else {
            $asOf = Carbon::createFromDate($year, 12, 31)->endOfYear();
            $month = null;
        }

        $items = Item::query()
            ->where('type', ItemType::ASSET_TETAP)
            ->with(['depreciation.warehouse'])
            ->orderBy('name')
            ->get();

        $rows = $fixedAssets->presentRegisterRows($items, $asOf);
        $totals = [
            'cost' => $rows->sum(fn ($row) => (float) ($row['register']?->buy_price ?? 0)),
            'accumulated' => $rows->sum(fn ($row) => (float) $row['accumulated']),
            'nbv' => $rows->sum(fn ($row) => (float) $row['nbv']),
        ];

        return view('reports.asset-tetap', [
            'rows' => $rows,
            'totals' => $totals,
            'asOf' => $asOf,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'yearList' => range((int) date('Y'), 2019),
        ]);
    }
}
