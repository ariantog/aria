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

class NettCashController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view_nett_cash']);

        $datesNow = Carbon::now();
        $month = $request->month;
        $year = $request->year ?? $datesNow->year;

        // ================= DATE RANGE =================
        if ($month && $month !== 'all') {
            $date = Carbon::createFromDate($year, $month, 1);
            $startDate = $date->startOfMonth()->toDateString();
            $endDate = $date->endOfMonth()->toDateString();
        } else {
            $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString();
            $endDate = Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString();
            $month = null;
        }

        // ================= GET CUSTOMER ID FROM TRANSACTION (FIX 🔥) =================
        $trxCustomerIds = Transaction::whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) {
                // CASH IN → sender
                $q->where(function ($sub) {
                    $sub->where('type', Transaction::TYPE_CASH_IN)
                        ->whereIn('sender_type', [
                            Addrbook::TYPE_CUSTOMER,
                            Addrbook::TYPE_RESELLER,
                        ]);
                })
                // CASH OUT & SELL → receiver
                    ->orWhere(function ($sub) {
                        $sub->whereIn('type', [
                            Transaction::TYPE_CASH_OUT,
                            Transaction::TYPE_SELL,
                        ])
                            ->whereIn('receiver_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    })
                // RETURN → sender
                    ->orWhere(function ($sub) {
                        $sub->where('type', Transaction::TYPE_RETURN)
                            ->whereIn('sender_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    })
                // BANK → receiver
                    ->orWhere(function ($sub) {
                        $sub->where('receiver_type', Addrbook::TYPE_BANK)
                            ->whereIn('sender_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    });
            })
            ->get()
            ->flatMap(function ($row) {
                return [$row->sender_id, $row->receiver_id];
            })
            ->filter()
            ->unique()
            ->values();

        // ================= CUSTOMER LIST (STRICT 🔥) =================
        $customers = Addrbook::withTrashed()
            ->whereIn('type', [
                Addrbook::TYPE_CUSTOMER,
                Addrbook::TYPE_RESELLER,
                Addrbook::TYPE_BANK,
            ])
            ->where(function ($q) use ($trxCustomerIds) {
                // ✅ semua aktif
                $q->whereNull('deleted_at')
                // ✅ hanya deleted yang ada di transaksi
                    ->orWhere(function ($sub) use ($trxCustomerIds) {
                        $sub->whereNotNull('deleted_at')
                            ->whereIn('id', $trxCustomerIds);
                    });
            })
            ->get();

        $customerList = $customers->where('type', Addrbook::TYPE_CUSTOMER)->values();
        $resellerList = $customers->where('type', Addrbook::TYPE_RESELLER)->values();
        $bankList = $customers->where('type', Addrbook::TYPE_BANK)->values();

        // ================= VALID IDS =================
        $customerIds = array_flip($customerList->pluck('id')->toArray());
        $resellerIds = array_flip($resellerList->pluck('id')->toArray());
        $bankIds = array_flip($bankList->pluck('id')->toArray());

        // ================= QUERY =================
        $rows = Transaction::whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('type', Transaction::TYPE_CASH_IN)
                        ->whereIn('sender_type', [
                            Addrbook::TYPE_CUSTOMER,
                            Addrbook::TYPE_RESELLER,
                        ]);
                })
                    ->orWhere(function ($sub) {
                        $sub->whereIn('type', [
                            Transaction::TYPE_CASH_OUT,
                            Transaction::TYPE_SELL,
                        ])
                            ->whereIn('receiver_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('type', Transaction::TYPE_RETURN)
                            ->whereIn('sender_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('receiver_type', Addrbook::TYPE_BANK)
                            ->whereIn('sender_type', [
                                Addrbook::TYPE_CUSTOMER,
                                Addrbook::TYPE_RESELLER,
                            ]);
                    });
            })
            ->selectRaw('
                sender_id,
                sender_type,
                receiver_id,
                receiver_type,
                type,
                SUM(total) as total
            ')
            ->groupBy(
                'sender_id',
                'sender_type',
                'receiver_id',
                'receiver_type',
                'type'
            )
            ->get();

        // ================= INIT =================
        $init = fn () => [
            'cashIn' => [],
            'cashOut' => [],
            'sell' => [],
            'return' => [],
            'nettCash' => 0,
            'nettSell' => 0,
        ];

        $customerReport = $init();
        $resellerReport = $init();
        $bankReport = $init();

        $add = function (&$report, $key, $id, $value) {
            $report[$key][$id] = ($report[$key][$id] ?? 0) + $value;
        };

        // ================= LOOP =================
        foreach ($rows as $row) {
            if ($row->type == Transaction::TYPE_CASH_IN) {
                if ($row->sender_type == Addrbook::TYPE_CUSTOMER && isset($customerIds[$row->sender_id])) {
                    $add($customerReport, 'cashIn', $row->sender_id, $row->total);
                }
                if ($row->sender_type == Addrbook::TYPE_RESELLER && isset($resellerIds[$row->sender_id])) {
                    $add($resellerReport, 'cashIn', $row->sender_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_BANK && isset($bankIds[$row->receiver_id])) {
                    $add($bankReport, 'cashIn', $row->receiver_id, $row->total);
                }
            }

            if ($row->type == Transaction::TYPE_CASH_OUT) {
                if ($row->receiver_type == Addrbook::TYPE_CUSTOMER && isset($customerIds[$row->receiver_id])) {
                    $add($customerReport, 'cashOut', $row->receiver_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_RESELLER && isset($resellerIds[$row->receiver_id])) {
                    $add($resellerReport, 'cashOut', $row->receiver_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_BANK && isset($bankIds[$row->receiver_id])) {
                    $add($bankReport, 'cashOut', $row->receiver_id, $row->total);
                }
            }

            if ($row->type == Transaction::TYPE_SELL) {
                if ($row->receiver_type == Addrbook::TYPE_CUSTOMER && isset($customerIds[$row->receiver_id])) {
                    $add($customerReport, 'sell', $row->receiver_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_RESELLER && isset($resellerIds[$row->receiver_id])) {
                    $add($resellerReport, 'sell', $row->receiver_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_BANK && isset($bankIds[$row->receiver_id])) {
                    $add($bankReport, 'sell', $row->receiver_id, $row->total);
                }
            }

            if ($row->type == Transaction::TYPE_RETURN) {
                if ($row->sender_type == Addrbook::TYPE_CUSTOMER && isset($customerIds[$row->sender_id])) {
                    $add($customerReport, 'return', $row->sender_id, $row->total);
                }
                if ($row->sender_type == Addrbook::TYPE_RESELLER && isset($resellerIds[$row->sender_id])) {
                    $add($resellerReport, 'return', $row->sender_id, $row->total);
                }
                if ($row->receiver_type == Addrbook::TYPE_BANK && isset($bankIds[$row->receiver_id])) {
                    $add($bankReport, 'return', $row->receiver_id, $row->total);
                }
            }
        }

        // ================= NORMALIZE =================
        $normalize = function ($list, &$report) {
            foreach ($list as $item) {
                $id = $item->id;
                $report['cashIn'][$id] = $report['cashIn'][$id] ?? 0;
                $report['cashOut'][$id] = $report['cashOut'][$id] ?? 0;
                $report['sell'][$id] = $report['sell'][$id] ?? 0;
                $report['return'][$id] = $report['return'][$id] ?? 0;
            }
        };

        $normalize($customerList, $customerReport);
        $normalize($resellerList, $resellerReport);
        $normalize($bankList, $bankReport);

        // ================= CALC =================
        $calc = function (&$report) {
            $report['nettCash'] = array_sum($report['cashIn']) + array_sum($report['cashOut']);
            $report['nettSell'] = array_sum($report['sell']) - array_sum($report['return']);
        };

        $calc($customerReport);
        $calc($resellerReport);
        $calc($bankReport);

        // ================= YEAR LIST =================
        $yearList = range(date('Y'), 2019);

        return Inertia::render('Reports/NettCashSby', [
            'customerList' => $customerList,
            'resellerList' => $resellerList,
            'bankList' => $bankList,
            'customerReport' => (object) $customerReport,
            'resellerReport' => (object) $resellerReport,
            'bankReport' => (object) $bankReport,
            'filters' => ['month' => $month, 'year' => (int) $year],
            'yearList' => $yearList,
            'datesNow' => ['month' => $datesNow->month, 'year' => $datesNow->year],
        ]);
    }
}
