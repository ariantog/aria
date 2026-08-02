<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\StatSell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ItemSaleReportController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-item-sales']);

        $bulan = $request->bulan;
        $tahun = $request->tahun ?? now()->year;

        $query = StatSell::with(['group', 'sender:id,name'])
            ->where('tahun', $tahun);

        if ($bulan && $bulan !== '0') {
            $query->where('bulan', $bulan);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search_group) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search_group}%");
            });
        }

        $dataList = $query->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->paginate(100)
            ->withQueryString();

        // Map data to match the frontend expectations
        $dataList->getCollection()->transform(function ($row) {
            return [
                'id' => $row->id,
                'year' => $row->tahun,
                'month' => $row->bulan,
                'group_id' => $row->group_id,
                'customer_id' => $row->sender_id,
                'type' => $row->type,
                'type_name' => $row->type_name,
                'sum_qty' => (float) $row->sum_qty,
                'sum_total' => (float) $row->sum_total,
                'group' => $row->group,
                'customer' => $row->sender,
            ];
        });

        return view('reports.item-sales', [
            'dataList' => $dataList,
            'filters' => [
                'bulan' => $bulan ?? '0',
                'tahun' => $tahun,
                'type' => $request->type ?? '0',
                'search_group' => $request->search_group ?? '',
            ],
            'yearList' => range(date('Y'), 2019),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
