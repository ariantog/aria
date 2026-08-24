<?php

namespace App\Http\Controllers;

use App\Actions\Produksi\SendToWarehouse;
use App\Http\Requests\StoreProduksiRequest;
use App\Models\Produksi;
use App\Models\Tag;
use App\Models\Worker;
use App\Services\Produksi\ProduksiStatisticsService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProduksiController extends Controller
{
    public function workerIndex(string $type)
    {
        Gate::authorize(Worker::getPermissions()['view']);

        return view('produksi.workers.index', [
            'workers' => Worker::where('type', $this->getWorkerTypeInt($type))->latest()->paginate(10)->withQueryString(),
            'type' => $type,
            'title' => ucfirst($type).' Workers',
            'can' => $this->workerPermissions(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function workerShow(Request $request, Worker $worker, ProduksiStatisticsService $stats)
    {
        Gate::authorize(Worker::getPermissions()['view']);
        $type = (string) $request->route()->parameter('type');
        if ($worker->type !== $this->getWorkerTypeInt($type)) {
            abort(404);
        }

        [$startDate, $endDate, $month, $year] = $stats->resolveDateRange(
            $request->filled('month') ? (int) $request->query('month') : null,
            $request->filled('year') ? (int) $request->query('year') : null,
        );

        return view('produksi.workers.show', [
            'worker' => $worker,
            'type' => $type,
            'title' => $worker->name,
            'stats' => $stats->workerStats($worker, $startDate, $endDate),
            'history' => $stats->workerHistory($worker, $startDate, $endDate),
            'month' => $month,
            'year' => $year,
            'years' => $stats->yearList(),
            'filters' => $request->only(['month', 'year']),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function workerStore(Request $request, string $type)
    {
        Gate::authorize(Worker::getPermissions()['create']);
        $request->validate(['name' => 'required|string|max:255']);
        Worker::create(['name' => trim($request->name), 'type' => $this->getWorkerTypeInt($type)]);

        return back()->with('success', ucfirst($type).' worker created.');
    }

    public function workerUpdate(Request $request, Worker $worker)
    {
        Gate::authorize(Worker::getPermissions()['edit']);
        $type = (string) $request->route()->parameter('type');
        if ($worker->type !== $this->getWorkerTypeInt($type)) {
            abort(404);
        } $request->validate(['name' => 'required|string|max:255']);
        $worker->update(['name' => trim($request->name)]);

        return back()->with('success', 'Worker updated.');
    }

    public function workerDestroy(Request $request, Worker $worker)
    {
        Gate::authorize(Worker::getPermissions()['delete']);
        $type = (string) $request->route()->parameter('type');
        if ($worker->type !== $this->getWorkerTypeInt($type)) {
            abort(404);
        } $worker->delete();

        return back()->with('success', 'Worker deleted.');
    }

    public function index(Request $request)
    {
        Gate::authorize(Produksi::getPermissions()['view']);
        $query = Produksi::with(['potong', 'size', 'jahit', 'qc'])->where('status', Produksi::STATUS_PRODUKSI)
            ->when($request->filled('from') && $request->filled('to'), fn ($q) => $q->whereDate('potong_date', '>=', $request->from)->whereDate('potong_date', '<=', $request->to))
            ->when($request->filled('kode'), fn ($q) => $q->where('temp_name', 'like', '%'.$request->kode.'%'))
            ->when($request->filled('customer'), fn ($q) => $q->where('customer', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('potong_id'), fn ($q) => $q->where('potong_id', $request->potong_id))
            ->when($request->filled('jahit_id'), fn ($q) => $q->where('jahit_id', $request->jahit_id))
            ->when($request->filled('surat_jalan_potong'), fn ($q) => $q->where('surat_jalan_potong', 'like', '%'.$request->surat_jalan_potong.'%'))
            ->when($request->filled('warna'), fn ($q) => $q->where('warna', 'like', '%'.$request->warna.'%'))
            ->when($request->filled('serial'), fn ($q) => $q->where(fn ($s) => $s->where('id', base_convert($request->serial, 36, 10))->orWhere('original_id', base_convert($request->serial, 36, 10))));
        $prod_produksi = $query->latest('id')->paginate(20)->withQueryString();

        return view('produksi.index', ['prod_produksi' => $prod_produksi, 'filters' => $request->only(['from', 'to', 'kode', 'customer', 'potong_id', 'jahit_id', 'serial', 'surat_jalan_potong', 'warna']), 'jahitList' => Worker::jahit()->get(), 'can' => $this->produksiPermissions(), 'flash' => ['success' => session('success'), 'error' => session('error')]]);
    }

    public function create()
    {
        Gate::authorize(Produksi::getPermissions()['create']);

        return view('produksi.create', ['workers' => Worker::potong()->get(), 'sizes' => Tag::where('type', Tag::TYPE_SIZE)->get()]);
    }

    public function store(StoreProduksiRequest $request)
    {
        Gate::authorize(Produksi::getPermissions()['create']);
        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                Produksi::create(['temp_name' => $item['name'], 'size_id' => $item['size_id'], 'quantity' => $item['qty'], 'customer' => $item['customer'] ? strtoupper($item['customer']) : null, 'warna' => $item['warna'] ? strtoupper($item['warna']) : null, 'potong_id' => $request->potong_id, 'potong_date' => $request->date, 'surat_jalan_potong' => $request->surat_jalan_potong, 'user_id' => $request->user()->id, 'status' => Produksi::STATUS_PRODUKSI]);
            }
        });

        return redirect()->route('produksi.index')->with('success', 'Production records created.');
    }

    public function postSaveRow(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate(['jahit_id' => 'required|exists:prod_worker,id']);
        $w = Worker::where('id', $request->jahit_id)->where('type', Worker::TYPE_JAHIT)->firstOrFail();
        $produksi->update(['jahit_id' => $w->id, 'jahit_date' => now()]);

        return back()->with('success', 'Assigned to Jahit.');
    }

    public function postSaveQc(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate(['qc_id' => 'required|exists:prod_worker,id']);
        $w = Worker::where('id', $request->qc_id)->where('type', Worker::TYPE_QC)->firstOrFail();
        $produksi->update(['qc_id' => $w->id, 'qc_date' => now()]);

        return back()->with('success', 'Assigned to QC.');
    }

    public function postSavePritil(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate(['pritil_id' => 'required|exists:prod_worker,id']);
        $w = Worker::where('id', $request->pritil_id)->where('type', Worker::TYPE_PRITIL)->firstOrFail();
        $produksi->update(['pritil_id' => $w->id, 'pritil_date' => now()]);

        return back()->with('success', 'Assigned to Pritil.');
    }

    public function postSetor(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['setor']);
        $produksi->update(['status' => Produksi::STATUS_SETOR, 'setor_date' => now()]);

        return back()->with('success', 'Moved to Setoran.');
    }

    public function workerLookup(Request $request)
    {
        $q = Worker::query()->when($request->filled('search'), fn ($q) => $q->where('name', 'like', LikeSearch::contains($request->search)));
        if ($request->filled('type')) {
            $m = ['jahit' => Worker::TYPE_JAHIT, 'potong' => Worker::TYPE_POTONG, 'qc' => Worker::TYPE_QC, 'pritil' => Worker::TYPE_PRITIL];
            $q->where('type', $m[$request->type] ?? $request->type);
        }

        return response()->json($q->limit(20)->get());
    }

    public function setoranGudang(Request $request, Produksi $produksi, SendToWarehouse $action)
    {
        Gate::authorize(Produksi::getPermissions()['gudang']);
        $request->validate(['invoice' => 'required|string|max:255']);
        try {
            $action->execute($produksi, $request->invoice, auth()->id() ?? 1);

            return back()->with('success', "Serial: {$produksi->serial} masuk transaksi {$request->invoice}.");
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function setoranStatusToProduksi(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['revert']);
        if ($produksi->status != Produksi::STATUS_SETOR) {
            return back()->withErrors(['error' => 'Status harus Setor.']);
        } $produksi->update(['status' => Produksi::STATUS_PRODUKSI]);

        return back()->with('success', 'Kembali ke Produksi.');
    }

    public function edit(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $produksi->load(['potong', 'size', 'jahit', 'qc']);

        return view('produksi.edit', [
            'produksi' => $produksi,
            'jahitList' => Worker::jahit()->get(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function update(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $validated = $request->validate([
            'warna' => 'nullable|string|max:255',
            'customer' => 'nullable|string|max:255',
            'surat_jalan_potong' => 'nullable|string|max:255',
        ]);
        $produksi->update([
            'warna' => $validated['warna'] ? strtoupper($validated['warna']) : null,
            'customer' => $validated['customer'] ? strtoupper($validated['customer']) : null,
            'surat_jalan_potong' => $validated['surat_jalan_potong'] ?? null,
        ]);

        return back()->with('success', 'Basic info updated.');
    }

    public function split(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate([
            'split_q' => 'required|integer|min:1|max:'.($produksi->quantity - 1),
        ]);

        DB::transaction(function () use ($request, $produksi) {
            $splitQty = (int) $request->split_q;
            $produksi->update(['quantity' => $produksi->quantity - $splitQty]);

            $data = $produksi->replicate()->toArray();
            unset($data['serial']);
            $data['quantity'] = $splitQty;
            $data['original_id'] = $produksi->original_id ?: $produksi->id;
            $data['jahit_id'] = null;
            $data['jahit_date'] = null;
            $data['qc_id'] = null;
            $data['qc_date'] = null;
            $data['pritil_id'] = null;
            $data['pritil_date'] = null;
            Produksi::create($data);
        });

        return back()->with('success', 'Production entry split successfully.');
    }

    public function gantiJahit(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate(['jahit_id' => 'required|exists:prod_worker,id']);
        $w = Worker::where('id', $request->jahit_id)->where('type', Worker::TYPE_JAHIT)->firstOrFail();
        $produksi->update(['jahit_id' => $w->id, 'jahit_date' => $produksi->jahit_date ?: now()]);

        return back()->with('success', 'Jahit worker updated.');
    }

    public function setoranIndex(Request $request)
    {
        Gate::authorize(Produksi::getPermissions()['setoran-view']);
        $query = Produksi::with(['potong', 'size', 'jahit', 'qc', 'pritil', 'item'])
            ->whereIn('status', Produksi::setoranStatuses())
            ->when($request->filled('from') && $request->filled('to'), fn ($q) => $q->whereDate('potong_date', '>=', $request->from)->whereDate('potong_date', '<=', $request->to))
            ->when($request->filled('kode'), fn ($q) => $q->where('temp_name', 'like', '%'.$request->kode.'%'))
            ->when($request->filled('customer'), fn ($q) => $q->where('customer', 'like', '%'.$request->customer.'%'))
            ->when($request->filled('warna'), fn ($q) => $q->where('warna', 'like', '%'.$request->warna.'%'))
            ->when($request->filled('potong_id'), fn ($q) => $q->where('potong_id', $request->potong_id))
            ->when($request->filled('jahit_id'), fn ($q) => $q->where('jahit_id', $request->jahit_id))
            ->when($request->filled('surat_jalan_potong'), fn ($q) => $q->where('surat_jalan_potong', 'like', '%'.$request->surat_jalan_potong.'%'))
            ->when($request->filled('invoice'), fn ($q) => $q->where('invoice', 'like', '%'.$request->invoice.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('serial'), fn ($q) => $q->where(fn ($s) => $s->where('id', base_convert($request->serial, 36, 10))->orWhere('original_id', base_convert($request->serial, 36, 10))));

        $u = auth()->user();
        $p = Produksi::getPermissions();

        return view('produksi.setoran.index', [
            'prod_produksi' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $request->only(['from', 'to', 'kode', 'customer', 'warna', 'potong_id', 'jahit_id', 'surat_jalan_potong', 'serial', 'invoice', 'status']),
            'jahitList' => Worker::jahit()->get(),
            'potongList' => Worker::potong()->get(),
            'statusList' => Produksi::statusFilterOptions(),
            'can' => [
                'edit_setoran' => $u->can($p['edit']),
                'gudang_setoran' => $u->can($p['gudang']),
                'assign_qc' => $u->can($p['edit']),
                'assign_pritil' => $u->can($p['edit']),
                'revert_setoran' => $u->can($p['revert']),
            ],
            'qcList' => Worker::qc()->orderBy('name')->get(),
            'pritilList' => Worker::pritil()->orderBy('name')->get(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function setoranEdit(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['setoran-view']);
        $produksi->load(['potong', 'size', 'jahit', 'qc']);
        $u = auth()->user();
        $p = Produksi::getPermissions();

        return view('produksi.setoran.edit', [
            'produksi' => $produksi,
            'jahitList' => Worker::jahit()->get(),
            'qcList' => Worker::qc()->get(),
            'can' => [
                'edit_setoran' => $u->can($p['edit']),
                'split_setoran' => $u->can($p['edit']),
                'revert_setoran' => $u->can($p['revert']),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function setoranEditItem(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);
        $request->validate(['item_id' => 'required|exists:items,id']);

        if ($produksi->status !== Produksi::STATUS_SETOR || ! empty($produksi->invoice)) {
            return back()->withErrors(['error' => 'Kode item hanya bisa diubah saat status Setor (belum masuk gudang).']);
        }

        $originalId = $produksi->original_id ?: $produksi->id;
        Produksi::query()
            ->where(function ($q) use ($produksi, $originalId) {
                $q->where('id', $produksi->id)
                    ->orWhere('original_id', $originalId)
                    ->orWhere('id', $originalId);
            })
            ->where('status', Produksi::STATUS_SETOR)
            ->where(fn ($q) => $q->whereNull('invoice')->orWhere('invoice', ''))
            ->update(['item_id' => $request->item_id]);

        return back()->with('success', 'Kode item diupdate.');
    }

    private function getWorkerTypeInt(string $type): int
    {
        return match ($type) {
            'potong' => Worker::TYPE_POTONG, 'jahit' => Worker::TYPE_JAHIT, 'qc' => Worker::TYPE_QC, 'pritil' => Worker::TYPE_PRITIL, default => abort(404)
        };
    }

    private function workerPermissions(): array
    {
        $u = auth()->user();
        $p = Worker::getPermissions();

        return ['create_worker' => $u->can($p['create']), 'edit_worker' => $u->can($p['edit']), 'delete_worker' => $u->can($p['delete'])];
    }

    private function produksiPermissions(): array
    {
        $u = auth()->user();
        $p = Produksi::getPermissions();

        return ['create_produksi' => $u->can($p['create']), 'edit_produksi' => $u->can($p['edit']), 'delete_produksi' => $u->can($p['delete']), 'setor_produksi' => $u->can($p['setor'])];
    }
}
