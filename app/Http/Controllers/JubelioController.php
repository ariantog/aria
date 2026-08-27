<?php

namespace App\Http\Controllers;

use App\Actions\Jubelio\AdjustStock;
use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioOrderShowPresenter;
use App\Services\Jubelio\JubelioOrderWarehouseResolver;
use App\Services\Jubelio\JubelioTransactionSyncPresenter;
use App\Services\JubelioGetOrdersService;
use App\Services\JubelioService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class JubelioController extends Controller
{
    public function cekOrder(Request $request, JubelioGetOrdersService $getOrdersService): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $orderId = trim((string) $request->query('order_id', old('order_id', '')));
        $lookup = null;
        $lookupError = null;

        if ($orderId !== '') {
            $apiData = $getOrdersService->lookupOrder($orderId);
            if ($apiData) {
                $lookup = [
                    'api' => $apiData,
                    'inspection' => $getOrdersService->inspectApiOrder($apiData),
                ];
            } else {
                $lookupError = 'Order tidak ditemukan di Jubelio API.';
            }
        }

        return view('jubelio.cek.index', [
            'orderId' => $orderId,
            'lookup' => $lookup,
            'lookupError' => $lookupError,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function queueCekOrder(Request $request, JubelioGetOrdersService $getOrdersService): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:255'],
        ]);

        $apiData = $getOrdersService->lookupOrder($validated['order_id']);
        if (! $apiData) {
            return redirect()
                ->route('jubelio.order.cek', ['order_id' => $validated['order_id']])
                ->with('error', 'Order tidak ditemukan di Jubelio API.');
        }

        $result = $getOrdersService->queueApiOrder($apiData);

        return redirect()
            ->route('jubelio.order.cek', ['order_id' => $validated['order_id']])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function index(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);
        $q = Jubelioorder::query()->with('user')->orderBy('updated_at', 'desc');
        if ($request->status == 'warning') {
            $q->where('status', 2)->where('error_type', 2);
        } elseif ($request->status == 'success') {
            $q->where('status', 2)->where('error_type', 10);
        } elseif ($request->status == 'error') {
            $q->where('status', 1)->where('error_type', 1);
        } elseif ($request->status == 'pending') {
            $q->where('status', 0);
        } elseif (! $request->invoice) {
            $q->where('status', 0);
        }
        $q->when($request->invoice, fn ($q) => $q->where('invoice', 'like', '%'.$request->invoice.'%'));
        $stats = Jubelioorder::selectRaw('COUNT(CASE WHEN status=0 THEN 1 END) as pending, COUNT(CASE WHEN status=2 AND error_type=10 THEN 1 END) as success, COUNT(CASE WHEN status=2 AND error_type=2 THEN 1 END) as warning, COUNT(CASE WHEN status=1 AND error_type=1 THEN 1 END) as error')->first();
        $resolver = app(JubelioOrderWarehouseResolver::class);
        $syncIndex = $resolver->syncIndex();
        $orders = $q->paginate(15)->withQueryString();
        $orders->getCollection()->transform(function (Jubelioorder $order) use ($resolver, $syncIndex) {
            $warehouses = $resolver->resolve($order, $syncIndex);
            $order->jubelio_warehouse = $warehouses['jubelio_warehouse'];
            $order->aria_warehouse = $warehouses['aria_warehouse'];

            return $order;
        });

        return view('jubelio.index', ['orders' => $orders, 'stats' => ['pending' => (int) $stats->pending, 'success' => (int) $stats->success, 'warning' => (int) $stats->warning, 'error' => (int) $stats->error], 'filters' => $request->only(['status', 'invoice']), 'flash' => ['success' => session('success'), 'error' => session('error') ?? session('errorMessage')]]);
    }

    public function show(Jubelioorder $jubelio, JubelioOrderShowPresenter $presenter): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $order = $jubelio->load(['user', 'trx']);
        $presented = $presenter->present($order);

        return view('jubelio.show', [
            'order' => $order,
            'summary' => $order->payloadSummary(),
            'items' => $presented['items'],
            'parties' => $presented['parties'],
            'transactionsUrl' => $order->transactionsSearchUrl(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function payload(Jubelioorder $jubelio): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        return response()->json([
            'payload' => $jubelio->payloadArray(),
        ]);
    }

    public function processOrder(Jubelioorder $jubelio, ProcessJubelioOrder $processor): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        if (! $jubelio->canProcessManually()) {
            return back()->with('error', 'Order ini tidak dapat diproses manual.');
        }

        $result = $processor->execute($jubelio, auth()->id());

        return $result['success']
            ? back()->with('success', $result['message'])
            : back()->with('errorMessage', $result['message']);
    }

    public function markSolved(Jubelioorder $jubelio): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        if (! $jubelio->canMarkSolved()) {
            return back()->with('error', 'Order ini tidak dapat ditandai selesai.');
        }

        $jubelio->update([
            'error_type' => 10,
            'error' => null,
            'execute_by' => auth()->id(),
            'status' => 2,
        ]);

        return redirect()->route('jubelio.index')->with('success', 'Order ditandai selesai.');
    }

    public function webhookReturn(Request $request, JubelioService $jubelioService): JsonResponse
    {
        $secret = config('services.jubelio.webhook_secret');
        if ($request->header('Sign') !== hash_hmac('sha256', trim($request->getContent()).$secret, $secret, false)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $returnId = $payload['return_id'] ?? null;

        if (! $returnId) {
            return response()->json(['status' => 'ok', 'message' => 'return_id missing']);
        }

        $dataApi = $jubelioService->fetchSalesReturn($returnId);

        if (! $dataApi) {
            return response()->json(['status' => 'ok', 'message' => 'Gagal mengambil data retur dari API.']);
        }

        if (Jubelioorder::where('invoice', $dataApi['return_no'])
            ->where('type', 'RETURN')
            ->where('order_status', 'RETURN')
            ->exists()) {
            return response()->json(['status' => 'ok', 'message' => 'Data already exists']);
        }

        $sellExists = Transaction::where('type', Transaction::TYPE_SELL)
            ->where('invoice', $dataApi['salesorder_no'])
            ->exists();

        if (! $sellExists) {
            return response()->json(['status' => 'ok', 'message' => 'Transaksi sell tidak ada.']);
        }

        Jubelioorder::create([
            'jubelio_order_id' => $dataApi['return_id'],
            'source' => 1,
            'invoice' => $dataApi['return_no'],
            'type' => 'RETURN',
            'order_status' => 'RETURN',
            'run_count' => 0,
            'status' => 0,
        ]);

        return response()->json(['status' => 'ok', 'message' => 'Data saved successfully']);
    }

    public function webhookOrder(Request $request): JsonResponse
    {
        $s = config('services.jubelio.webhook_secret');
        if ($request->header('Sign') !== hash_hmac('sha256', trim($request->getContent()).$s, $s, false)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        if ($request->header('X-Jubelio-Forwarded-From')) {
            Log::info('Jubelio order webhook received via forward', [
                'from' => $request->header('X-Jubelio-Forwarded-From'),
                'invoice' => $request->input('salesorder_no'),
                'status' => $request->input('status'),
            ]);
        }

        $d = $request->all();
        if (($d['status'] ?? '') === 'SHIPPED') {
            if (Carbon::parse($d['transaction_date'])->lt(Carbon::parse('2025-03-06'))) {
                return response()->json(['status' => 'ok', 'message' => 'Before threshold.']);
            }
            if (Jubelioorder::where('invoice', $d['salesorder_no'])->where('type', 'SELL')->where('order_status', $d['status'])->exists()) {
                return response()->json(['status' => 'ok', 'message' => 'Already exists']);
            }

            $invoice = $d['salesorder_no'];
            $destyInvoice = str_replace('SP-', '', $invoice);
            $sellExists = Transaction::where('type', Transaction::TYPE_SELL)
                ->where(function ($query) use ($invoice, $destyInvoice) {
                    $query->where('invoice', $invoice);
                    if ($destyInvoice !== $invoice) {
                        $query->orWhere('invoice', $destyInvoice);
                    }
                })
                ->exists();

            if ($sellExists) {
                return response()->json(['status' => 'ok', 'message' => 'Invoice sudah ada']);
            }

            $order = Jubelioorder::create([
                'jubelio_order_id' => $d['salesorder_id'],
                'source' => 1,
                'invoice' => $d['salesorder_no'],
                'type' => 'SELL',
                'order_status' => $d['status'],
                'run_count' => 0,
                'status' => 0,
            ]);

            return response()->json(['status' => 'ok', 'message' => 'Saved']);
        }
        if (($d['status'] ?? '') === 'CANCELED') {
            $t = Transaction::where('type', Transaction::TYPE_SELL)->where('invoice', $d['salesorder_no'])->first();
            if (! $t) {
                return response()->json(['status' => 'ok', 'message' => 'Not found']);
            }
            if ((int) $t->jubelio_return > 0) {
                return response()->json(['status' => 'ok', 'message' => 'Transaksi sudah return']);
            }
            if (Jubelioreturn::where('order_id', $d['salesorder_id'])->exists()) {
                return response()->json(['status' => 'ok', 'message' => 'Return exists']);
            }
            Jubelioreturn::create([
                'order_id' => $d['salesorder_id'],
                'transaction_id' => $t->id,
                'method_pay' => $d['payment_method'] ?? null,
                'invoice' => $d['salesorder_no'],
                'pesan' => $d['cancel_reason_detail'] ?? null,
                'location_name' => $d['location_name'] ?? null,
                'store_name' => $d['source_name'] ?? null,
                'status' => 0,
                'confirmed_by' => 0,
            ]);

            return response()->json(['status' => 'ok', 'message' => 'Cancel saved']);
        }

        return response()->json(['status' => 'ok', 'message' => 'Status '.($d['status'] ?? 'unknown')]);
    }

    public function confirmSyncWarning(Request $request, Transaction $transaction): RedirectResponse
    {
        Transaction::authorizeJubelioTransactionSync();

        $validated = $request->validate([
            'side' => ['required', 'in:1,2'],
            'reference_id' => ['nullable', 'string', 'max:255'],
        ]);

        $side = (int) $validated['side'];

        if ($side === 1) {
            if (! $transaction->hasSyncWarningA()) {
                return back()->with('errorMessage', 'Tidak ada peringatan sinkronisasi untuk Side A.');
            }
            $transaction->update([
                'a_submit_by' => auth()->id(),
                'a_reference_id' => $validated['reference_id'] ?? null,
            ]);
        } else {
            if (! $transaction->hasSyncWarningB()) {
                return back()->with('errorMessage', 'Tidak ada peringatan sinkronisasi untuk Side B.');
            }
            $transaction->update([
                'b_submit_by' => auth()->id(),
                'b_reference_id' => $validated['reference_id'] ?? null,
            ]);
        }

        return back()->with('success', 'Sinkronisasi dikonfirmasi berhasil.');
    }

    public function clearSyncWarning(Request $request, Transaction $transaction): RedirectResponse
    {
        Transaction::authorizeJubelioTransactionSync();

        $validated = $request->validate([
            'side' => ['required', 'in:1,2'],
        ]);

        $side = (int) $validated['side'];

        if ($side === 1) {
            if (! $transaction->hasSyncWarningA()) {
                return back()->with('errorMessage', 'Tidak ada peringatan sinkronisasi untuk Side A.');
            }
            $transaction->update(['submit_a_count' => 0]);
        } else {
            if (! $transaction->hasSyncWarningB()) {
                return back()->with('errorMessage', 'Tidak ada peringatan sinkronisasi untuk Side B.');
            }
            $transaction->update(['submit_b_count' => 0]);
        }

        return back()->with('success', 'Peringatan sinkronisasi dihapus. Anda dapat mencoba push ulang.');
    }

    public function transactionSync(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);
        $types = [Transaction::TYPE_SELL => 'SELL', Transaction::TYPE_RETURN_SUPPLIER => 'RETURN SUPPLIER', Transaction::TYPE_BUY => 'BUY', Transaction::TYPE_RETURN => 'RETURN', Transaction::TYPE_MOVE => 'MOVE'];
        $q = Transaction::with(['sender', 'receiver'])
            ->where('submit_type', '!=', Transaction::SUBMIT_TYPE_JUBELIO)
            ->when($request->display, fn ($q) => $q->where('sync_hide', $request->display), fn ($q) => $q->where('sync_hide', 'N'))->when($request->date, fn ($q) => $q->whereDate('date', '=', $request->date))->when($request->invoice, fn ($q) => $q->where('invoice', 'like', "%$request->invoice%"))->when($request->type, fn ($q) => $q->where('type', $request->type));
        if (! $request->invoice) {
            $q->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('type', [Transaction::TYPE_SELL, Transaction::TYPE_RETURN_SUPPLIER])->whereNull('a_submit_by')->whereIn('sender_id', fn ($s) => $s->select('warehouse_id')->from('jubeliosyncs')))->orWhere(fn ($q) => $q->whereIn('type', [Transaction::TYPE_BUY, Transaction::TYPE_RETURN])->whereNull('b_submit_by')->whereIn('receiver_id', fn ($s) => $s->select('warehouse_id')->from('jubeliosyncs')))->orWhere(fn ($q) => $q->where('type', Transaction::TYPE_MOVE)->where(fn ($q) => $q->where(fn ($w) => $w->whereIn('sender_id', fn ($s) => $s->select('warehouse_id')->from('jubeliosyncs'))->whereNull('a_submit_by'))->orWhere(fn ($w) => $w->whereIn('receiver_id', fn ($s) => $s->select('warehouse_id')->from('jubeliosyncs'))->whereNull('b_submit_by')))));
        }
        $t = $q->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(200)->withQueryString();
        $syncedWarehouseIds = app(JubelioTransactionSyncPresenter::class)->syncedWarehouseIds();
        $t->getCollection()->transform(function ($i) use ($syncedWarehouseIds) {
            $i->sync_cek = app(JubelioTransactionSyncPresenter::class)
                ->present($i, $syncedWarehouseIds)['sync_cek'];
            $i->type_name = $i->getTypeLabel();
            $i->description = $i->description ?? $i->notes ?? '';

            return $i;
        });

        return view('jubelio.transaction-sync', ['transactions' => $t, 'types' => $types, 'filters' => $request->only(['date', 'invoice', 'type', 'display']), 'flash' => ['success' => session('success'), 'error' => session('error')]]);
    }

    public function detailJubelioSync(Transaction $transaction, JubelioTransactionSyncPresenter $presenter): View
    {
        Transaction::authorizeJubelioTransactionSync();
        $transaction->load(['receiver', 'sender', 'user', 'submitByA', 'submitByB', 'details.item.group']);
        $sync = $presenter->present($transaction);
        $transaction->setAttribute('item_with_jubelio_count', $sync['mapping_missing']);

        return view('jubelio.detail-sync', [
            'data' => $transaction,
            'can_sync' => $sync['can_sync'],
            'JubelioA' => $sync['jubelio_a'],
            'JubelioB' => $sync['jubelio_b'],
            'adJustTypeA' => $sync['adjust_type_a'],
            'adJustTypeB' => $sync['adjust_type_b'],
            'whA' => 2,
            'whB' => 1,
            'whAName' => $sync['wh_a_name'],
            'whBName' => $sync['wh_b_name'],
            'warningA' => $sync['warning_a'],
            'warningB' => $sync['warning_b'],
            'flash' => ['success' => session('success'), 'error' => session('errorMessage') ?? session('error')],
        ]);
    }

    public function transactionSyncDisplay(Transaction $transaction): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);
        $transaction->update(['sync_hide' => $transaction->sync_hide == 'N' ? 'Y' : 'N']);

        return back()->with('success', 'Updated.');
    }

    public function adjustStok(Request $r, $id, AdjustStock $a): RedirectResponse
    {
        Transaction::authorizeJubelioTransactionSync();
        $t = Transaction::with(['details.item'])->findOrFail($id);
        try {
            $res = $a->execute($t, (int) $r->side, (int) $r->adjustType, (int) $r->whType);

            return $res['success'] ? back()->with('success', $res['message']) : back()->with('errorMessage', $res['message']);
        } catch (\RuntimeException $e) {
            return back()->with('errorMessage', $e->getMessage());
        }
    }

}
