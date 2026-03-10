<?php

namespace App\Http\Controllers;

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
    // Generic Worker Management
    public function workerIndex(string $type): Response
    {
        Gate::authorize(Worker::getPermissions()['view']);

        $workerType = $this->getWorkerTypeInt($type);
        $title = ucfirst($type).' Workers';

        return Inertia::render('Produksi/Workers/Index', [
            'workers' => Worker::where('type', $workerType)->latest()->paginate(10),
            'type' => $type,
            'title' => $title,
            'can' => [
                'create_worker' => auth()->user()->can(Worker::getPermissions()['create']),
                'edit_worker' => auth()->user()->can(Worker::getPermissions()['edit']),
                'delete_worker' => auth()->user()->can(Worker::getPermissions()['delete']),
            ],
        ]);
    }

    public function workerStore(Request $request, string $type)
    {
        Gate::authorize(Worker::getPermissions()['create']);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Worker::create([
            'name' => trim($request->name),
            'type' => $this->getWorkerTypeInt($type),
        ]);

        return redirect()->back()->with('success', ucfirst($type).' worker created.');
    }

    public function workerUpdate(Request $request, Worker $worker)
    {
        Gate::authorize(Worker::getPermissions()['edit']);

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $worker->update(['name' => trim($request->name)]);

        return redirect()->back()->with('success', 'Worker updated.');
    }

    public function workerDestroy(Worker $worker)
    {
        Gate::authorize(Worker::getPermissions()['delete']);

        $worker->delete();

        return redirect()->back()->with('success', 'Worker deleted.');
    }

    protected function getWorkerTypeInt(string $type): int
    {
        return match ($type) {
            'potong' => Worker::TYPE_POTONG,
            'jahit' => Worker::TYPE_JAHIT,
            'qc' => Worker::TYPE_QC,
            default => abort(404),
        };
    }

    // Production Entries
    public function index(Request $request): Response
    {
        Gate::authorize(Produksi::getPermissions()['view']);

        $query = Produksi::with(['potong', 'size', 'jahit', 'qc'])
            ->where('status', Produksi::STATUS_PRODUKSI);

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereDate('potong_date', '>=', $request->from)
                ->whereDate('potong_date', '<=', $request->to);
        }

        if ($request->filled('kode')) {
            $query->where('temp_name', 'like', '%'.$request->kode.'%');
        }

        if ($request->filled('customer')) {
            $query->where('customer', 'like', '%'.$request->customer.'%');
        }

        if ($request->filled('potong_id')) {
            $query->where('potong_id', $request->potong_id);
        }

        if ($request->filled('jahit_id')) {
            $query->where('jahit_id', $request->jahit_id);
        }

        if ($request->filled('serial')) {
            $serial = $request->serial;
            $id = base_convert($serial, 36, 10);
            $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('original_id', $id);
            });
        }

        if ($request->filled('surat_jalan_potong')) {
            $query->where('surat_jalan_potong', 'like', '%'.$request->surat_jalan_potong.'%');
        }

        if ($request->filled('warna')) {
            $query->where('warna', 'like', '%'.$request->warna.'%');
        }

        return Inertia::render('Produksi/Index', [
            'produksis' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $request->only(['from', 'to', 'kode', 'customer', 'potong_id', 'jahit_id', 'serial', 'surat_jalan_potong', 'warna']),
            'jahitList' => Worker::jahit()->get(),
            'can' => [
                'create_produksi' => auth()->user()->can(Produksi::getPermissions()['create']),
                'edit_produksi' => auth()->user()->can(Produksi::getPermissions()['edit']),
                'delete_produksi' => auth()->user()->can(Produksi::getPermissions()['delete']),
                'setor_produksi' => auth()->user()->can(Produksi::getPermissions()['setor']),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize(Produksi::getPermissions()['create']);

        return Inertia::render('Produksi/Create', [
            'workers' => Worker::potong()->get(),
            'sizes' => Tag::where('type', Tag::TYPE_SIZE)->get(),
        ]);
    }

    public function store(StoreProduksiRequest $request)
    {
        Gate::authorize(Produksi::getPermissions()['create']);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                Produksi::create([
                    'temp_name' => $item['name'],
                    'size_id' => $item['size_id'],
                    'quantity' => $item['qty'],
                    'customer' => $item['customer'] ? strtoupper($item['customer']) : null,
                    'warna' => $item['warna'] ? strtoupper($item['warna']) : null,
                    'potong_id' => $request->potong_id,
                    'potong_date' => $request->date,
                    'surat_jalan_potong' => $request->surat_jalan_potong,
                    'status' => Produksi::STATUS_PRODUKSI,
                ]);
            }
        });

        return redirect()->route('produksi.index')->with('success', 'Production records created.');
    }

    public function postSaveRow(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'jahit_id' => 'required|exists:workers,id',
        ]);

        $worker = Worker::where('id', $request->jahit_id)->where('type', Worker::TYPE_JAHIT)->firstOrFail();

        $produksi->update([
            'jahit_id' => $worker->id,
            'jahit_date' => now(),
        ]);

        return redirect()->back()->with('success', 'Production entry #'.$produksi->id.' assigned to Jahit.');
    }

    public function postSaveQc(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'qc_id' => 'required|exists:workers,id',
        ]);

        $worker = Worker::where('id', $request->qc_id)->where('type', Worker::TYPE_QC)->firstOrFail();

        $produksi->update([
            'qc_id' => $worker->id,
            'qc_date' => now(),
        ]);

        return redirect()->back()->with('success', 'Production entry #'.$produksi->id.' assigned to QC.');
    }

    public function postSetor(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['setor']);

        $produksi->update([
            'status' => Produksi::STATUS_SETOR,
            'setor_date' => now(),
        ]);

        return back()->with('success', 'Item has been moved to Setoran.');
    }

    public function workerLookup(Request $request)
    {
        $query = Worker::query();

        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'jahit') {
                $type = Worker::TYPE_JAHIT;
            } elseif ($type === 'potong') {
                $type = Worker::TYPE_POTONG;
            } elseif ($type === 'qc') {
                $type = Worker::TYPE_QC;
            }

            $query->where('type', $type);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return response()->json($query->limit(20)->get());
    }

    public function setoranIndex(Request $request): Response
    {
        Gate::authorize(Produksi::getPermissions()['setoran-view']);

        $query = Produksi::with(['item', 'potong', 'size', 'jahit', 'qc']);

        $from = $request->from;
        $to = $request->to;

        if ($from && $to) {
            $query->where(function ($query) use ($from, $to) {
                $query->where(function ($q) use ($from, $to) {
                    $q->whereDate('potong_date', '>=', $from)->whereDate('potong_date', '<=', $to);
                })->orWhere(function ($q) use ($from, $to) {
                    $q->whereDate('jahit_date', '>=', $from)->whereDate('jahit_date', '<=', $to);
                });
            });
        }

        if ($request->filled('potong_id')) {
            $query->where('potong_id', $request->potong_id);
        }
        if ($request->filled('jahit_id')) {
            $query->where('jahit_id', $request->jahit_id);
        }
        if ($request->filled('customer')) {
            $query->where('customer', 'like', "%{$request->customer}%");
        }
        if ($request->filled('warna')) {
            $query->where('warna', 'like', "%{$request->warna}%");
        }
        if ($request->filled('kode')) {
            $query->where('temp_name', 'like', "%{$request->kode}%");
        }
        if ($request->filled('surat_jalan_potong')) {
            $query->where('surat_jalan_potong', 'like', "{$request->surat_jalan_potong}%");
        }
        if ($request->filled('serial')) {
            $serial = $request->serial;
            $query->where(function ($query) use ($serial) {
                // Convert base36 back to base10 for ID
                $id = base_convert($serial, 36, 10);
                $query->where('id', '=', $id)->orWhere('original_id', '=', $id);
            });
        }
        if ($request->filled('invoice')) {
            $query->where('invoice', $request->invoice);
        }

        if ($request->filled('status')) {
            if ($request->status > 0) {
                $query->where('status', $request->status);
            } else {
                $query->where('status', '!=', Produksi::STATUS_PRODUKSI);
            }
        } else {
            $query->where('status', '!=', Produksi::STATUS_PRODUKSI);
        }

        $produksis = $query->latest('id')->paginate(30)->withQueryString();

        return Inertia::render('Produksi/Setoran/Index', [
            'produksis' => $produksis,
            'filters' => $request->only(['from', 'to', 'potong_id', 'jahit_id', 'customer', 'warna', 'kode', 'surat_jalan_potong', 'serial', 'status', 'invoice']),
            'jahitList' => Worker::jahit()->get(),
            'potongList' => Worker::potong()->get(),
            'statusList' => [
                ['id' => Produksi::STATUS_SETOR, 'name' => 'Setor'],
                ['id' => Produksi::STATUS_GUDANG, 'name' => 'Gudang'],
                ['id' => Produksi::STATUS_BOTH, 'name' => 'Both'],
                ['id' => Produksi::STATUS_BAYAR, 'name' => 'Bayar'],
            ],
            'statusGudang' => Produksi::STATUS_GUDANG,
            'statusBoth' => Produksi::STATUS_BOTH,
            'can' => [
                'edit_setoran' => auth()->user()->can(Produksi::getPermissions()['edit']),
                'gudang_setoran' => auth()->user()->can(Produksi::getPermissions()['gudang']),
                'kembali_produksi' => auth()->user()->can(Produksi::getPermissions()['edit']),
            ],
        ]);
    }

    public function setoranEditItem(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'item_id' => 'required',
        ]);

        try {
            DB::transaction(function () use ($request, $produksi) {
                $item = \App\Models\Item::find((int) $request->item_id);
                if (! $item) {
                    throw new \Exception('Item not found');
                }

                $produksi->item_id = $item->id;
                $produksi->temp_name = $item->name;
                $produksi->save();

                $siblingsIds = [];
                // Update siblings
                if ($produksi->original_id > 0) {
                    $siblings = Produksi::where('original_id', $produksi->original_id)->where('id', '!=', $produksi->id)->get();
                    foreach ($siblings as $s) {
                        if (! empty($s->invoice)) {
                            continue;
                        }
                        $s->item_id = $item->id;
                        $s->temp_name = $item->name;
                        $s->save();
                        $siblingsIds[] = $s->serial;
                    }
                }
            });

            return redirect()->back()->with('success', 'Serial '.$produksi->serial.' updated.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function setoranGudang(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['gudang']);

        $request->validate([
            'invoice' => 'required|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request, $produksi) {
                if (empty($produksi->item_id)) {
                    throw new \Exception('Belum ada item, update kode terlebih dahulu.');
                }

                if ($produksi->transaction_id > 0 || $produksi->detail_id > 0 || ! empty($produksi->invoice)) {
                    throw new \Exception('Sudah masuk invoice/gudang');
                }

                if ($produksi->status == Produksi::STATUS_GUDANG) {
                    throw new \Exception('Status sudah gudang');
                }

                $invoice = $request->invoice;

                // Hardcode logic legacy 2874 or fallback
                $warehouseId = 2874;
                if (! \App\Models\Addrbook::where('id', $warehouseId)->exists()) {
                    $warehouseId = \App\Models\Addrbook::where('type', \App\Models\Addrbook::TYPE_WAREHOUSE)->value('id');
                    if (! $warehouseId) {
                        throw new \Exception('Gudang tujuan tidak ditemukan di database.');
                    }
                }

                $transaction = \App\Models\Transaction::where('invoice_number', $invoice)->where('type', \App\Models\Transaction::TYPE_PRODUCTION)->first();

                if (! $transaction) {
                    $transaction = \App\Models\Transaction::create([
                        'date' => now()->toDateString(),
                        'type' => \App\Models\Transaction::TYPE_PRODUCTION,
                        'receiver_id' => $warehouseId,
                        'receiver_type' => \App\Models\Addrbook::class,
                        'invoice_number' => $invoice,
                        'user_id' => auth()->id() ?? 1,
                        'total_items' => 0,
                    ]);
                }

                $detail = \App\Models\TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'item_id' => $produksi->item_id,
                    'quantity' => $produksi->quantity,
                    'price' => 0,
                    'discount' => 0,
                    'total' => 0,
                ]);

                $produksi->update([
                    'status' => Produksi::STATUS_GUDANG,
                    'transaction_id' => $transaction->id,
                    'detail_id' => $detail->id,
                    'invoice' => $invoice,
                    'gudang_date' => now()->toDateString(),
                ]);

                // Update transaction total
                $transaction->increment('total_items', $produksi->quantity);

                // Add to warehouse inventory
                $inventoryService = new \App\Services\InventoryService;
                $inventoryService->add($warehouseId, $produksi->item, $produksi->quantity);
            });

            return back()->with('success', "Serial: {$produksi->serial} sudah masuk transaksi {$request->invoice}.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function setoranStatusToProduksi(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        if ($produksi->status != Produksi::STATUS_SETOR) {
            return back()->withErrors(['error' => 'Inputan tidak valid, status harus Setor.']);
        }

        $produksi->update([
            'status' => Produksi::STATUS_PRODUKSI,
        ]);

        return back()->with('success', 'Serial: '.$produksi->serial.' kembali ke Produksi.');
    }

    public function edit(Produksi $produksi): Response
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        return Inertia::render('Produksi/Edit', [
            'produksi' => $produksi->load(['potong', 'size', 'jahit', 'qc']),
            'jahitList' => Worker::jahit()->get(),
        ]);
    }

    public function setoranEdit(Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['setoran-view']);

        if ($produksi->status == Produksi::STATUS_PRODUKSI) {
            return redirect()->route('produksi.index')->withErrors(['error' => 'Not in Setoran status.']);
        }

        return Inertia::render('Produksi/Setoran/Edit', [
            'produksi' => $produksi->load(['potong', 'size', 'jahit', 'qc']),
            'jahitList' => Worker::jahit()->get(),
            'qcList' => Worker::qc()->get(),
            'can' => [
                'edit_setoran' => auth()->user()->can(Produksi::getPermissions()['edit']),
                'split_setoran' => auth()->user()->can(Produksi::getPermissions()['edit']),
            ],
        ]);
    }

    public function update(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'warna' => 'nullable|string|max:255',
            'customer' => 'nullable|string|max:255',
            'surat_jalan_potong' => 'nullable|string|max:255',
        ]);

        $produksi->update([
            'warna' => $request->warna ? strtoupper(trim($request->warna)) : null,
            'customer' => $request->customer ? strtoupper(trim($request->customer)) : null,
            'surat_jalan_potong' => $request->surat_jalan_potong ? trim($request->surat_jalan_potong) : null,
        ]);

        return redirect()->back()->with('success', 'Production entry updated.');
    }

    public function split(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'split_q' => 'required|numeric|min:1|max:'.($produksi->quantity - 1),
        ]);

        $splitQty = (int) $request->split_q;

        DB::transaction(function () use ($produksi, $splitQty) {
            // New record
            $new = $produksi->replicate();
            $new->quantity = $splitQty;
            $new->save();

            // Update original record
            $produksi->decrement('quantity', $splitQty);
        });

        return redirect()->back()->with('success', 'Production entry split successfully.');
    }

    public function gantiJahit(Request $request, Produksi $produksi)
    {
        Gate::authorize(Produksi::getPermissions()['edit']);

        $request->validate([
            'jahit_id' => 'required|exists:workers,id',
        ]);

        $worker = Worker::where('id', $request->jahit_id)->where('type', Worker::TYPE_JAHIT)->firstOrFail();

        $produksi->update([
            'jahit_id' => $worker->id,
            'jahit_date' => now(), // Update date too as it's a new assignment effectively
        ]);

        return redirect()->back()->with('success', 'Jahit worker updated.');
    }
}
