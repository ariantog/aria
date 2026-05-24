<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ExpenseReportController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-expense']);

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
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('sender_type', Addrbook::TYPE_ACCOUNT)
                        ->where('receiver_type', Addrbook::TYPE_BANK);
                })->orWhere(function ($q2) {
                    $q2->where('sender_type', Addrbook::TYPE_BANK)
                        ->where('receiver_type', Addrbook::TYPE_ACCOUNT);
                });
            })
            ->selectRaw('sender_id as id')
            ->union(
                Transaction::whereBetween('date', [$startDate, $endDate])
                    ->where(function ($q) {
                        $q->where(function ($q2) {
                            $q2->where('sender_type', Addrbook::TYPE_ACCOUNT)
                                ->where('receiver_type', Addrbook::TYPE_BANK);
                        })->orWhere(function ($q2) {
                            $q2->where('sender_type', Addrbook::TYPE_BANK)
                                ->where('receiver_type', Addrbook::TYPE_ACCOUNT);
                        });
                    })
                    ->selectRaw('receiver_id as id')
            )
            ->pluck('id')
            ->unique()
            ->toArray();

        // =========================
        // 3. AMBIL ADDRBOOK (ACCOUNT & BANK)
        // =========================
        $customers = Addrbook::withTrashed()
            ->whereIn('type', [
                Addrbook::TYPE_ACCOUNT,
                Addrbook::TYPE_BANK,
            ])
            ->where(function ($q) use ($transactionCustomerIds) {
                $q->whereNull('deleted_at')
                    ->orWhereIn('id', $transactionCustomerIds);
            })
            ->get();

        // =========================
        // 4. SPLIT LIST
        // =========================
        $accountList = $customers->where('type', Addrbook::TYPE_ACCOUNT)->values();
        $bankList = $customers->where('type', Addrbook::TYPE_BANK)->values();

        // =========================
        // 5. QUERY TRANSACTION
        // =========================
        $rows = Transaction::whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('sender_type', Addrbook::TYPE_ACCOUNT)
                        ->where('receiver_type', Addrbook::TYPE_BANK);
                })
                    ->orWhere(function ($q2) {
                        $q2->where('sender_type', Addrbook::TYPE_BANK)
                            ->where('receiver_type', Addrbook::TYPE_ACCOUNT);
                    });
            })
            ->selectRaw('
                sender_id,
                receiver_id,
                sender_type,
                receiver_type,
                SUM(total) as total
            ')
            ->groupBy('sender_id', 'receiver_id', 'sender_type', 'receiver_type')
            ->get();

        // =========================
        // 6. INIT REPORT
        // =========================
        $accountReport = [
            'cashIn' => [],
            'cashOut' => [],
        ];

        $bankReport = [
            'cashIn' => [],
            'cashOut' => [],
        ];

        // =========================
        // 7. LOOP
        // =========================
        foreach ($rows as $row) {
            $total = (float) $row->total;

            // ACCOUNT -> BANK (uang masuk ke BANK)
            if ($row->sender_type == Addrbook::TYPE_ACCOUNT && $row->receiver_type == Addrbook::TYPE_BANK) {
                $accountReport['cashIn'][$row->sender_id] = ($accountReport['cashIn'][$row->sender_id] ?? 0) + $total;
                $bankReport['cashIn'][$row->receiver_id] = ($bankReport['cashIn'][$row->receiver_id] ?? 0) + $total;
            }

            // BANK -> ACCOUNT (uang keluar dari BANK)
            if ($row->sender_type == Addrbook::TYPE_BANK && $row->receiver_type == Addrbook::TYPE_ACCOUNT) {
                $accountReport['cashOut'][$row->receiver_id] = ($accountReport['cashOut'][$row->receiver_id] ?? 0) + $total;
                $bankReport['cashOut'][$row->sender_id] = ($bankReport['cashOut'][$row->sender_id] ?? 0) + $total;
            }
        }

        // =========================
        // 8. YEAR LIST
        // =========================
        $yearList = range(date('Y'), 2019);

        return Inertia::render('Reports/ExpenseReport', [
            'accountList' => $accountList,
            'accountReport' => $accountReport,
            'bankList' => $bankList,
            'bankReport' => $bankReport,
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
