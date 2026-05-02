<?php

namespace App\Http\Controllers;

use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
