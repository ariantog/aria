<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\AddrbookDaily;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize(AddrbookDaily::getPermissions()['view_nett_cash']);

        // =========================
        // 1. FILTER TANGGAL
        // =========================
        $datesNow = Carbon::now();
        $month = $request->month;
        $year = $request->year ?? $datesNow->year;

        if ($month && $month !== 'all') {
            $date = Carbon::createFromDate($year, $month, 1);
            $startDate = $date->startOfMonth()->toDateString();
            $endDate = $date->endOfMonth()->toDateString();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString();
            $month = null;
        }

        // =========================
        // 2. AMBIL ID DARI TRANSAKSI
        // =========================
        $transactionCustomerIds = Transaction::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('sender_id as id')
            ->union(
                Transaction::whereBetween('date', [$startDate, $endDate])
                    ->selectRaw('receiver_id as id')
            )
            ->pluck('id')
            ->unique()
            ->toArray();

        // =========================
        // 3. AMBIL ADDRBOOK (SUPPLIER & ACCOUNT)
        // =========================
        $customers = Addrbook::withTrashed()
            ->whereIn('type', [
                Addrbook::TYPE_SUPPLIER,
                Addrbook::TYPE_ACCOUNT,
            ])
            ->where(function ($q) use ($transactionCustomerIds) {
                $q->whereNull('deleted_at') // aktif
                    ->orWhereIn('id', $transactionCustomerIds); // deleted tapi dipakai
            })
            ->get();

        $allIds = $customers->pluck('id')->toArray();

        // =========================
        // 4. SPLIT DATA
        // =========================
        $supplierList = $customers->where('type', Addrbook::TYPE_SUPPLIER)->values();
        $accountList = $customers->where('type', Addrbook::TYPE_ACCOUNT)->values();

        $supplierMap = $supplierList->pluck('id')->flip();
        $accountMap = $accountList->pluck('id')->flip();

        // =========================
        // 5. QUERY TRANSACTION
        // =========================
        $rows = Transaction::whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) use ($allIds) {
                $q->whereIn('sender_id', $allIds)
                    ->orWhereIn('receiver_id', $allIds);
            })
            ->selectRaw('
                sender_id,
                receiver_id,
                type,
                SUM(total) as total
            ')
            ->groupBy('sender_id', 'receiver_id', 'type')
            ->get();

        // =========================
        // 6. INIT REPORT
        // =========================
        $supplierReport = [
            'buy' => [],
            'returnSupplier' => [],
            'cashInSupplier' => [],
            'cashInAccount' => [],
            'cashOutSupplier' => [],
            'cashOutAccount' => [],
            'nettBuy' => 0,
        ];

        // =========================
        // 7. LOOP
        // =========================
        foreach ($rows as $row) {
            $total = (float) $row->total;

            if ($row->type == Transaction::TYPE_BUY && isset($supplierMap[$row->sender_id])) {
                $supplierReport['buy'][$row->sender_id] = ($supplierReport['buy'][$row->sender_id] ?? 0) + $total;
            }

            if ($row->type == Transaction::TYPE_RETURN_SUPPLIER && isset($supplierMap[$row->sender_id])) {
                $supplierReport['returnSupplier'][$row->sender_id] = ($supplierReport['returnSupplier'][$row->sender_id] ?? 0) + $total;
            }

            if ($row->type == Transaction::TYPE_CASH_IN) {
                if (isset($supplierMap[$row->sender_id])) {
                    $supplierReport['cashInSupplier'][$row->sender_id] = ($supplierReport['cashInSupplier'][$row->sender_id] ?? 0) + $total;
                }
                if (isset($accountMap[$row->sender_id])) {
                    $supplierReport['cashInAccount'][$row->sender_id] = ($supplierReport['cashInAccount'][$row->sender_id] ?? 0) + $total;
                }
            }

            if ($row->type == Transaction::TYPE_CASH_OUT) {
                if (isset($supplierMap[$row->receiver_id])) {
                    $supplierReport['cashOutSupplier'][$row->receiver_id] = ($supplierReport['cashOutSupplier'][$row->receiver_id] ?? 0) + $total;
                }
                if (isset($accountMap[$row->sender_id])) {
                    $supplierReport['cashOutAccount'][$row->sender_id] = ($supplierReport['cashOutAccount'][$row->sender_id] ?? 0) + $total;
                }
            }
        }

        // =========================
        // 8. NETT
        // =========================
        $supplierReport['nettBuy'] = array_sum($supplierReport['buy'])
            - array_sum($supplierReport['returnSupplier'])
            - array_sum($supplierReport['cashInSupplier']);

        // =========================
        // 9. YEAR LIST
        // =========================
        $yearList = range(date('Y'), 2019);

        return Inertia::render('Reports/PurchaseReport', [
            'supplierList' => $supplierList,
            'supplierReport' => $supplierReport,
            'accountList' => $accountList,
            'filters' => [
                'month' => $month,
                'year' => (int) $year,
            ],
            'yearList' => $yearList,
            'datesNow' => [
                'month' => now()->month,
                'year' => now()->year,
            ],
        ]);
    }
}
