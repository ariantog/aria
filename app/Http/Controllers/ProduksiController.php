<?php

namespace App\Http\Controllers;

use App\Actions\Produksi\SendToWarehouse;
use App\Http\Requests\StoreProduksiRequest;
use App\Models\Produksi;
use App\Models\Tag;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProduksiController extends Controller
{
    public function workerIndex(string $type): Response
    {
        Gate::authorize(Worker::getPermissions()['view']);
        return Inertia::render('Produksi/Workers/Index', ['workers' => Worker::where('type', $this->getWorkerTypeInt($type))->latest()->paginate(10), 'type' => $type, 'title' => ucfirst($type).' Workers', 'can' => $this->workerPermissions()]);
    }
    public function workerStore(Request $request, string $type) { Gate::authorize(Worker::getPermissions()['create']); $request->validate(['name' => 'required|string|max:255']); Worker::create(['name' => trim($request->name), 'type' => $this->getWorkerTypeInt($type)]); return back()->with('success', ucfirst($type).' worker created.'); }
    public function workerUpdate(Request $request, Worker $worker) { Gate::authorize(Worker::getPermissions()['edit']); $request->validate(['name' => 'required|string|max:255']); $worker->update(['name' => trim($request->name)]); return back()->with('success', 'Worker updated.'); }
    public function workerDestroy(Worker $worker) { Gate::authorize(Worker::getPermissions()['delete']); $worker->delete(); return back()->with('success', 'Worker deleted.'); }

    public function index(Request $request): Response
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
        return Inertia::render('Produksi/Index', ['produksis' => $query->latest('id')->paginate(20)->withQueryString(), 'filters' => $request->only(['from', 'to', 'kode', 'customer', 'potong_id', 'jahit_id', 'serial', 'surat_jalan_potong', 'warna']), 'jahitList' => Worker::jahit()->get(), 'can' => $this->produksiPermissions()]);
    }

    public function create(): Response { Gate::authorize(Produksi::getPermissions()['create']); return Inertia::render('Produksi/Create', ['workers' => Worker::potong()->get(), 'sizes' => Tag::where('type', Tag::TYPE_SIZE)->get()]); }
    public function store(StoreProduksiRequest $request) { Gate::authorize(Produksi::getPermissions()['create']); DB::transaction(function () use ($request) { foreach ($request->items as $item) { Produksi::create(['temp_name' => $item['name'], 'size_id' => $item['size_id'], 'quantity' => $item['qty'], 'customer' => $item['customer'] ? strtoupper($item['customer']) : null, 'warna' => $item['warna'] ? strtoupper($item['warna']) : null, 'potong_id' => $request->potong_id, 'potong_date' => $request->date, 'surat_jalan_potong' => $request->surat_jalan_potong, 'status' => Produksi::STATUS_PRODUKSI]); } }); return redirect()->route('produksi.index')->with('success', 'Production records created.'); }
    public function postSaveRow(Request $request, Produksi $produksi) { Gate::authorize(Produksi::getPermissions()['edit']); $request->validate(['jahit_id' => 'required|exists:workers,id']); $w = Worker::where('id', $request->jahit_id)->where('type', Worker::TYPE_JAHIT)->firstOrFail(); $produksi->update(['jahit_id' => $w->id, 'jahit_date' => now()]); return back()->with('success', 'Assigned to Jahit.'); }
    public function postSaveQc(Request $request, Produksi $produksi) { Gate::authorize(Produksi::getPermissions()['edit']); $request->validate(['qc_id' => 'required|exists:workers,id']); $w = Worker::where('id', $request->qc_id)->where('type', Worker::TYPE_QC)->firstOrFail(); $produksi->update(['qc_id' => $w->id, 'qc_date' => now()]); return back()->with('success', 'Assigned to QC.'); }
    public function postSetor(Produksi $produksi) { Gate::authorize(Produksi::getPermissions()['setor']); $produksi->update(['status' => Produksi::STATUS_SETOR, 'setor_date' => now()]); return back()->with('success', 'Moved to Setoran.'); }
    public function workerLookup(Request $request) { $q = Worker::query()->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')); if ($request->filled('type')) { $m = ['jahit' => Worker::TYPE_JAHIT, 'potong' => Worker::TYPE_POTONG, 'qc' => Worker::TYPE_QC]; $q->where('type', $m[$request->type] ?? $request->type); } return response()->json($q->limit(20)->get()); }
    public function setoranGudang(Request $request, Produksi $produksi, SendToWarehouse $action) { Gate::authorize(Produksi::getPermissions()['gudang']); $request->validate(['invoice' => 'required|string|max:255']); try { $action->execute($produksi, $request->invoice, auth()->id() ?? 1); return back()->with('success', "Serial: {$produksi->serial} masuk transaksi {$request->invoice}."); } catch (\RuntimeException $e) { return back()->withErrors(['error' => $e->getMessage()]); } }
    public function setoranStatusToProduksi(Produksi $produksi) { Gate::authorize(Produksi::getPermissions()['edit']); if ($produksi->status != Produksi::STATUS_SETOR) return back()->withErrors(['error' => 'Status harus Setor.']); $produksi->update(['status' => Produksi::STATUS_PRODUKSI]); return back()->with('success', 'Kembali ke Produksi.'); }

    private function getWorkerTypeInt(string $type): int { return match ($type) { 'potong' => Worker::TYPE_POTONG, 'jahit' => Worker::TYPE_JAHIT, 'qc' => Worker::TYPE_QC, default => abort(404) }; }
    private function workerPermissions(): array { $u = auth()->user(); $p = Worker::getPermissions(); return ['create_worker' => $u->can($p['create']), 'edit_worker' => $u->can($p['edit']), 'delete_worker' => $u->can($p['delete'])]; }
    private function produksiPermissions(): array { $u = auth()->user(); $p = Produksi::getPermissions(); return ['create_produksi' => $u->can($p['create']), 'edit_produksi' => $u->can($p['edit']), 'delete_produksi' => $u->can($p['delete']), 'setor_produksi' => $u->can($p['setor'])]; }
}
