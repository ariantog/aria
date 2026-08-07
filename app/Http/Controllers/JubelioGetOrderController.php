<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Crongetorder;
use App\Models\Crongetorderdetail;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\ScheduledTask;
use App\Models\Transaction;
use App\Services\JubelioGetOrdersService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioGetOrderController extends Controller
{
    public function __construct(
        private JubelioGetOrdersService $getOrdersService,
    ) {}

    public function index(): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = Crongetorder::withCount('details')->orderByDesc('created_at')->first();
        $details = collect();
        $dateFrom = '';
        $dateTo = '';
        $progress = 0;
        $existingInvoices = [];

        if ($import) {
            $range = $import->dateRangeIso();
            $dateFrom = $range['from'];
            $dateTo = $range['to'];
            $progress = $import->progressPercent();
            $details = Crongetorderdetail::query()
                ->where('crongetorder_id', $import->id)
                ->orderBy('invoice')
                ->paginate(200);

            $existingInvoices = $this->getOrdersService->existingInvoiceLookup(
                $details->getCollection()->pluck('invoice')->filter()->all(),
            );
        }

        return view('jubelio.get-orders.index', [
            'import' => $import,
            'details' => $details,
            'progress' => $progress,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'existingInvoices' => $existingInvoices,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'integer', 'min:0', 'max:366'],
        ]);

        if (Crongetorder::where('status', 0)->exists()) {
            return back()->with('error', 'Masih ada import yang berjalan. Reset terlebih dahulu.');
        }

        Crongetorder::create([
            'from' => $validated['from'],
            'to' => $validated['to'],
        ]);

        ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => true]);

        return redirect()->route('jubelio.get-orders.index')
            ->with('success', 'Import order dimulai. Cron akan mengambil data dari Jubelio API.');
    }

    public function checkTransactions(): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = $this->activeImport();
        if (! $import) {
            return back()->with('error', 'Tidak ada import aktif.');
        }

        $endDate = Carbon::parse($import->from)->addDays($import->to)->toDateString();

        $invoices = Transaction::where('type', TransactionType::Sell->value)
            ->where('submit_type', Transaction::SUBMIT_TYPE_JUBELIO)
            ->whereDate('date', '>=', $import->from)
            ->whereDate('date', '<=', $endDate)
            ->pluck('invoice_number')
            ->filter()
            ->all();

        $removed = 0;
        foreach (array_chunk($invoices, 1000) as $chunk) {
            $removed += Crongetorderdetail::where('crongetorder_id', $import->id)
                ->whereIn('invoice', $chunk)
                ->delete();
        }

        $import->update(['cek_transaction' => true]);

        return back()->with('success', "{$removed} order dengan transaksi Jubelio dihapus dari daftar.");
    }

    public function checkExisting(): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = $this->activeImport();
        if (! $import) {
            return back()->with('error', 'Tidak ada import aktif.');
        }

        $removed = $this->getOrdersService->removeInvoicesAlreadyInAria($import->id);

        return back()->with('success', "{$removed} order yang sudah ada di Aria dihapus dari daftar.");
    }

    public function importToOrders(): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = Crongetorder::query()->orderByDesc('created_at')->first();
        if (! $import || ! Crongetorderdetail::where('crongetorder_id', $import->id)->exists()) {
            return back()->with('error', 'Tidak ada order untuk diimport.');
        }

        $inserted = 0;
        $now = now();

        Crongetorderdetail::query()
            ->where('crongetorder_id', $import->id)
            ->orderBy('id')
            ->chunkById(500, function ($details) use (&$inserted, $now) {
                $invoices = $details->pluck('invoice')->filter()->all();
                if ($invoices === []) {
                    return;
                }

                $existing = Jubelioorder::query()
                    ->where('type', 'SELL')
                    ->whereIn('invoice', $invoices)
                    ->pluck('invoice')
                    ->all();

                $rows = $details
                    ->reject(fn ($detail) => in_array($detail->invoice, $existing, true))
                    ->map(fn ($detail) => [
                        'jubelio_order_id' => $detail->jubelio_order_id,
                        'source' => 2,
                        'invoice' => $detail->invoice,
                        'type' => 'SELL',
                        'order_status' => $detail->order_status ?? 'SHIPPED',
                        'run_count' => 0,
                        'payload' => '{}',
                        'status' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('jubelioorders')->insert($rows);
                    $inserted += count($rows);
                }
            });

        Crongetorderdetail::where('crongetorder_id', $import->id)->delete();
        $import->delete();
        ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => false]);

        return redirect()->route('jubelio.index')
            ->with('success', "{$inserted} order dipindah ke Jubelio Orders (pending).");
    }

    public function reset(): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $import = Crongetorder::orderByDesc('created_at')->first();
        if ($import) {
            Crongetorderdetail::where('crongetorder_id', $import->id)->delete();
            $import->delete();
        }

        ScheduledTask::where('command', 'jubelio:get-orders')->update(['is_active' => false]);

        return back()->with('success', 'Import direset.');
    }

    protected function activeImport(): ?Crongetorder
    {
        return Crongetorder::orderByDesc('created_at')->first();
    }
}
