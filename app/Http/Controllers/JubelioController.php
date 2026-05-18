<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class JubelioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = Jubelioorder::query()
            ->with('user')
            ->orderBy('updated_at', 'desc');

        // Apply legacy filters
        if ($request->status == 'warning') {
            $query->where('status', 2)->where('error_type', 2);
        } elseif ($request->status == 'success') {
            $query->where('status', 2)->where('error_type', 10);
        } elseif ($request->status == 'error') {
            $query->where('status', 1)->where('error_type', 1);
        } elseif ($request->status == 'pending') {
            $query->where('status', 0);
        }

        if ($request->invoice) {
            $query->where('invoice', 'like', '%'.$request->invoice.'%');
        }

        $orders = $query->paginate(15)->withQueryString();

        $stats = Jubelioorder::selectRaw('
            COUNT(CASE WHEN status = 0 THEN 1 END) as pending,
            COUNT(CASE WHEN status = 2 AND error_type = 10 THEN 1 END) as success,
            COUNT(CASE WHEN status = 2 AND error_type = 2 THEN 1 END) as warning,
            COUNT(CASE WHEN status = 1 AND error_type = 1 THEN 1 END) as error
        ')->first();

        return Inertia::render('jubelio/Index', [
            'orders' => $orders,
            'stats' => [
                'pending' => (int) $stats->pending,
                'success' => (int) $stats->success,
                'warning' => (int) $stats->warning,
                'error' => (int) $stats->error,
            ],
            'filters' => $request->only(['status', 'invoice']),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Jubelioorder $jubelio): Response
    {
        return Inertia::render('jubelio/Show', [
            'order' => $jubelio->load(['user', 'trx']),
        ]);
    }

    /**
     * Handle incoming order webhook from Jubelio.
     */
    public function webhookOrder(Request $request): JsonResponse
    {
        $secret = config('services.jubelio.webhook_secret', 'corenation2025');
        $content = trim($request->getContent());
        $sign = hash_hmac('sha256', $content.$secret, $secret, false);
        $signature = $request->header('Sign');

        if ($signature !== $sign) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $dataApi = $request->all();

        if (($dataApi['status'] ?? '') === 'SHIPPED') {
            $tanggal = Carbon::parse($dataApi['transaction_date']);
            $threshold = Carbon::parse('2025-03-06');

            if ($tanggal->lessThan($threshold)) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Transaction before threshold date ignored.',
                ], 200);
            }

            $exists = Jubelioorder::where('invoice', $dataApi['salesorder_no'])
                ->where('type', 'SELL')
                ->where('order_status', $dataApi['status'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Data already exists',
                ], 200);
            }

            Jubelioorder::create([
                'jubelio_order_id' => $dataApi['salesorder_id'],
                'source' => 1,
                'invoice' => $dataApi['salesorder_no'],
                'type' => 'SELL',
                'order_status' => $dataApi['status'],
                'run_count' => 0,
                'payload' => json_encode($dataApi),
                'status' => 0,
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Data saved successfully',
            ], 200);
        } elseif (($dataApi['status'] ?? '') === 'CANCELED') {
            $transaction = Transaction::where('type', Transaction::TYPE_SELL)
                ->where('invoice_number', $dataApi['salesorder_no'])
                ->first();

            if ($transaction) {
                $exists = Jubelioreturn::where('order_id', $dataApi['salesorder_id'])->exists();
                if ($exists) {
                    return response()->json(['status' => 'ok', 'message' => 'Return already exists'], 200);
                }

                Jubelioreturn::create([
                    'order_id' => $dataApi['salesorder_id'],
                    'transaction_id' => $transaction->id,
                    'method_pay' => $dataApi['payment_method'] ?? null,
                    'invoice' => $dataApi['salesorder_no'],
                    'pesan' => $dataApi['cancel_reason_detail'] ?? null,
                    'location_name' => $dataApi['location_name'] ?? null,
                    'store_name' => $dataApi['source_name'] ?? null,
                    'status' => 0,
                    'confirmed_by' => 0, // System/Pending
                ]);

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Cancel data saved successfully',
                ], 200);
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'Transaction not found for cancellation',
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Status '.($dataApi['status'] ?? 'unknown').' received',
        ], 200);
    }

    /**
     * Display a listing of transactions to be synced.
     */
    public function transactionSync(Request $request): Response
    {
        $types = [
            Transaction::TYPE_SELL => 'SELL',
            Transaction::TYPE_RETURN_SUPPLIER => 'RETURN SUPPLIER',
            Transaction::TYPE_BUY => 'BUY',
            Transaction::TYPE_RETURN => 'RETURN',
            Transaction::TYPE_MOVE => 'MOVE',
        ];

        $query = Transaction::with(['sender', 'receiver'])
            ->where('submit_type', Transaction::SUBMIT_TYPE_MANUAL);

        if ($request->display) {
            $query->where('sync_hide', $request->display);
        } else {
            $query->where('sync_hide', 'N');
        }

        if ($request->date) {
            $query->whereDate('date', '=', $request->date);
        }

        if ($request->invoice) {
            $query->where('invoice_number', 'like', "%$request->invoice%");
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if (! $request->invoice) {
            $query->where(function ($query) {
                $query
                    // Side A (Sender) needs sync
                    ->where(function ($q) {
                        $q->whereIn('type', [
                            Transaction::TYPE_SELL,
                            Transaction::TYPE_RETURN_SUPPLIER,
                        ])
                            ->whereNull('a_submit_by')
                            ->whereIn('sender_id', function ($sub) {
                                $sub->select('warehouse_id')->from('jubeliosyncs');
                            });
                    })
                    // Side B (Receiver) needs sync
                    ->orWhere(function ($q) {
                        $q->whereIn('type', [
                            Transaction::TYPE_BUY,
                            Transaction::TYPE_RETURN,
                        ])
                            ->whereNull('b_submit_by')
                            ->whereIn('receiver_id', function ($sub) {
                                $sub->select('warehouse_id')->from('jubeliosyncs');
                            });
                    })
                    // Move needs sync on either or both sides
                    ->orWhere(function ($q) {
                        $q->where('type', Transaction::TYPE_MOVE)
                            ->where(function ($qq) {
                                $qq->where(function ($w) {
                                    $w->whereIn('sender_id', function ($sub) {
                                        $sub->select('warehouse_id')->from('jubeliosyncs');
                                    })
                                        ->whereNull('a_submit_by');
                                })
                                    ->orWhere(function ($w) {
                                        $w->whereIn('receiver_id', function ($sub) {
                                            $sub->select('warehouse_id')->from('jubeliosyncs');
                                        })
                                            ->whereNull('b_submit_by');
                                    });
                            });
                    });
            });
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(200)->withQueryString();

        $syncedWarehouseIds = Jubeliosync::pluck('warehouse_id')->toArray();

        // Map sync_cek logic for frontend
        $transactions->getCollection()->transform(function ($item) use ($syncedWarehouseIds) {
            if (in_array($item->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER])) {
                $item->sync_cek = 'S';
            } elseif (in_array($item->type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN])) {
                $item->sync_cek = 'R';
            } elseif ($item->type == Transaction::TYPE_MOVE) {
                $senderSynced = in_array($item->sender_id, $syncedWarehouseIds);
                $receiverSynced = in_array($item->receiver_id, $syncedWarehouseIds);

                if ($senderSynced && $receiverSynced) {
                    $item->sync_cek = 'B';
                } elseif ($senderSynced) {
                    $item->sync_cek = 'S';
                } elseif ($receiverSynced) {
                    $item->sync_cek = 'R';
                } else {
                    $item->sync_cek = null;
                }
            } else {
                $item->sync_cek = null;
            }

            $item->type_name = $item->getTypeLabel();
            $item->description = $item->description ?? $item->notes ?? '';

            return $item;
        });

        return Inertia::render('jubelio/TransactionSync', [
            'transactions' => $transactions,
            'types' => $types,
            'filters' => $request->only(['date', 'invoice', 'type', 'display']),
        ]);
    }

    /**
     * Display detail for Jubelio sync.
     */
    public function detailJubelioSync(Transaction $transaction): Response
    {
        $data = $transaction->load(['receiver', 'sender', 'user', 'submitByA', 'submitByB', 'details.item.group'])
            ->loadCount([
                'details as item_with_jubelio_count' => function ($query) {
                    $query->whereHas('item', function ($q) {
                        $q->where(function ($q) {
                            $q->whereNull('jubelio_item_id')
                                ->orWhere('jubelio_item_id', '<', 1);
                        });
                    });
                },
            ]);

        $adJustTypeA = 0; // Sender adjustment type (0: none, 1: add, 2: deduct)
        $adJustTypeB = 0; // Receiver adjustment type
        $JubelioA = null;
        $JubelioB = null;

        // Determine if Sender needs sync
        if (in_array($data->type, [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER, Transaction::TYPE_MOVE])) {
            $jubSyncA = Jubeliosync::where('warehouse_id', $data->sender_id)->first();
            if ($jubSyncA) {
                $adJustTypeA = 2; // Deduct from sender
                $JubelioA = $jubSyncA->jubelio_location_name;
            }
        }

        // Determine if Receiver needs sync
        if (in_array($data->type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN, Transaction::TYPE_MOVE])) {
            $jubSyncB = Jubeliosync::where('warehouse_id', $data->receiver_id)->first();
            if ($jubSyncB) {
                $adJustTypeB = 1; // Add to receiver
                $JubelioB = $jubSyncB->jubelio_location_name;
            }
        }

        // Only manual transactions can be synced
        $canSync = $data->submit_type === \App\Models\Transaction::SUBMIT_TYPE_MANUAL;

        return Inertia::render('jubelio/DetailSync', [
            'data' => $data,
            'can_sync' => $canSync,
            'JubelioA' => $JubelioA,
            'JubelioB' => $JubelioB,
            'adJustTypeA' => $adJustTypeA,
            'adJustTypeB' => $adJustTypeB,
            // whA/whB are legacy params for adjustStok indicating receiver(1) or sender(2)
            'whA' => 2, // Always sender for side A
            'whB' => 1, // Always receiver for side B
            'whAName' => $data->sender->name ?? '',
            'whBName' => $data->receiver->name ?? '',
        ]);
    }

    /**
     * Toggle sync_hide status.
     */
    public function transactionSyncDisplay(Transaction $transaction): RedirectResponse
    {
        $transaction->sync_hide = ($transaction->sync_hide == 'N') ? 'Y' : 'N';
        $transaction->save();

        return back()->with('success', 'Sync visibility updated.');
    }

    /**
     * Adjust stock in Jubelio.
     */
    public function adjustStok(Request $request, $id): RedirectResponse
    {
        $transaction = Transaction::with(['details.item'])->findOrFail($id);

        // Warning/Limit checks
        if ($request->side == 1) {
            if ($transaction->submit_a_count > 0) {
                return back()->with('errorMessage', 'Side A has already been attempted.');
            }
            $transaction->increment('submit_a_count');
        } elseif ($request->side == 2) {
            if ($transaction->submit_b_count > 0) {
                return back()->with('errorMessage', 'Side B has already been attempted.');
            }
            $transaction->increment('submit_b_count');
        }

        try {
            DB::beginTransaction();

            $jubSync = null;
            if ($request->whType == 1) {
                $jubSync = Jubeliosync::where('warehouse_id', $transaction->receiver_id)->first();
            } elseif ($request->whType == 2) {
                $jubSync = Jubeliosync::where('warehouse_id', $transaction->sender_id)->first();
            }

            if (! $jubSync) {
                throw new \Exception('Jubelio mapping not found for this warehouse.');
            }

            // Force active and disable SSL verification for local/test environment
            config(['services.jubelio.active' => true]);
            config(['services.jubelio.verify_ssl' => false]);

            $service = app(\App\Services\JubelioService::class);
            $token = $service->getToken();

            if (! $token) {
                throw new \Exception('Jubelio token not found or authentication failed.');
            }

            $detailItems = [];
            foreach ($transaction->details as $row) {
                if (! $row->item->jubelio_item_id) {
                    continue;
                }

                $detailItems[] = [
                    'item_adj_detail_id' => 0,
                    'item_id' => $row->item->jubelio_item_id,
                    'serial_no' => null,
                    'qty_in_base' => ($request->adjustType == 1) ? (float) $row->quantity : -(float) $row->quantity,
                    'original_item_adj_detail_id' => 0,
                    'unit' => 'Buah',
                    'amount' => (float) $row->total,
                    'location_id' => $jubSync->jubelio_location_id,
                    'account_id' => 75, // Adjust to your actual Jubelio account ID
                    'description' => 'Item '.$row->item->code,
                    'bin_id' => $jubSync->bin_id,
                    'cost' => 0,
                ];
            }

            $payload = [
                'item_adj_id' => 0,
                'item_adj_no' => '[auto]',
                'transaction_date' => now()->toIso8601ZuluString(),
                'note' => 'Adjust from Aria with order no. '.$transaction->invoice_number,
                'location_id' => $jubSync->jubelio_location_id,
                'is_opening_balance' => false,
                'items' => $detailItems,
            ];

            $requestHttp = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token,
            ]);

            if (! config('services.jubelio.verify_ssl', true)) {
                $requestHttp->withoutVerifying();
            }

            $response = $requestHttp->post('https://api2.jubelio.com/inventory/adjustments/warehouse', $payload);

            if ($response->successful()) {
                $result = $response->json();
                if ($request->side == 1) {
                    $transaction->a_submit_by = Auth::id();
                    $transaction->a_reference_id = $result['id'] ?? null;
                } elseif ($request->side == 2) {
                    $transaction->b_submit_by = Auth::id();
                    $transaction->b_reference_id = $result['id'] ?? null;
                }
                $transaction->save();
                DB::commit();

                return back()->with('success', 'Jubelio adjustment successful.');
            } else {
                DB::rollBack();
                $error = $response->json();
                $message = $error['message'] ?? 'Jubelio API Error: '.$response->status();

                return back()->with('errorMessage', $message);
            }
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('errorMessage', $e->getMessage());
        }
    }
}
