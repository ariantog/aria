<?php

namespace App\Http\Controllers;

use App\Models\Addrbook;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CashFlowController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-cash-flow']);
        $currentYear = $request->tahun ?? $request->year ?? now()->year;
        $month = $request->bulan ?? $request->month;
        if ($month === '0' || $month === 'all') {
            $month = null;
        }

        // 100% Consistent with CashFlowController.php Legacy Aggregation
        $selectRaw = "
            SUM(CASE WHEN type = '".Transaction::TYPE_CASH_IN."' THEN total ELSE 0 END) AS cash_in_total,
            SUM(CASE WHEN type = '".Transaction::TYPE_CASH_OUT."' THEN total ELSE 0 END) AS cash_out_total,
            SUM(CASE WHEN type = '".Transaction::TYPE_SELL."' AND sender_type = '".Addrbook::TYPE_WAREHOUSE."' THEN total ELSE 0 END) AS sell_total,
            SUM(CASE WHEN type = '".Transaction::TYPE_RETURN."' THEN total ELSE 0 END) AS return_total,
            SUM(CASE WHEN type = '".Transaction::TYPE_BUY."' THEN total ELSE 0 END) AS buy_total,
            SUM(CASE WHEN type = '".Transaction::TYPE_RETURN_SUPPLIER."' THEN total ELSE 0 END) AS return_suplier
        ";

        $queryBase = Transaction::whereYear('date', $currentYear)
            ->when($month, function ($q) use ($month) {
                $q->whereMonth('date', $month);
            })
            ->where('sender_type', '!=', '0')
            ->where('receiver_type', '!=', '0');

        $groupBySender = (clone $queryBase)
            ->whereIn('sender_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER, Addrbook::TYPE_BANK, Addrbook::TYPE_ACCOUNT])
            ->select('sender_type as type_id', DB::raw($selectRaw))
            ->groupBy('sender_type')
            ->get()
            ->map(function ($item) {
                $typeInfo = collect(Addrbook::getTypes())->firstWhere('id', $item->type_id);
                $item->type_name = $typeInfo['name'] ?? 'Other';

                return $item;
            });

        $groupByReceiver = (clone $queryBase)
            ->whereIn('receiver_type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER, Addrbook::TYPE_BANK, Addrbook::TYPE_ACCOUNT])
            ->select('receiver_type as type_id', DB::raw($selectRaw))
            ->groupBy('receiver_type')
            ->get()
            ->map(function ($item) {
                $typeInfo = collect(Addrbook::getTypes())->firstWhere('id', $item->type_id);
                $item->type_name = $typeInfo['name'] ?? 'Other';

                return $item;
            });

        return view('reports.cash-flow', [
            'groupBySender' => $groupBySender,
            'groupByReceiver' => $groupByReceiver,
            'filters' => ['month' => $month, 'year' => (int) $currentYear],
            'yearList' => range(date('Y'), 2019),
            'datesNow' => ['month' => now()->month, 'year' => now()->year],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
