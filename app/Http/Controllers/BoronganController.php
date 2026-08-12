<?php

namespace App\Http\Controllers;

use App\Models\Borongan;
use App\Models\BoronganDetail;
use App\Models\Produksi;
use App\Models\Tag;
use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoronganController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize(Borongan::getPermissions()['view']);

        $from = $request->input('from');
        $to = $request->input('to');
        $jahitId = $request->input('jahit_id');

        $query = Borongan::with(['jahit', 'user']);

        if ($from) {
            $query->where('from', '>=', $from);
        }

        if ($to) {
            $query->where('to', '<=', $to);
        }

        if ($jahitId) {
            $query->where('jahit_id', $jahitId);
        }

        $prod_borongan = $query->latest('id')->paginate(30)->withQueryString();

        return view('borongan.index', [
            'prod_borongan' => $prod_borongan,
            'filters' => $request->only(['from', 'to', 'jahit_id']),
            'jahitList' => Worker::jahit()->get(),
            'can' => [
                'create_borongan' => auth()->user()->can(Borongan::getPermissions()['create']),
                'view_borongan' => auth()->user()->can(Borongan::getPermissions()['view-details']),
                'edit_borongan' => auth()->user()->can(Borongan::getPermissions()['edit']),
                'delete_borongan' => auth()->user()->can(Borongan::getPermissions()['delete']),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize(Borongan::getPermissions()['create']);

        $from = Carbon::now()->subDays(7)->toDateString();
        $to = Carbon::now()->toDateString();

        return view('borongan.create', [
            'defaultFrom' => $from,
            'defaultTo' => $to,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /**
     * Store newly created borongan records — one per jahit worker in the date range.
     */
    public function store(Request $request)
    {
        Gate::authorize(Borongan::getPermissions()['create']);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'batches' => 'required|array|min:1',
            'batches.*.jahit_id' => 'required|exists:prod_worker,id',
            'batches.*.permak' => 'nullable|numeric|min:0',
            'batches.*.tres' => 'nullable|numeric|min:0',
            'batches.*.lain2' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $created = 0;
            $updated = 0;

            foreach ($request->input('batches', []) as $batch) {
                $jahitId = (int) $batch['jahit_id'];
                $boronganItems = $this->findBorongan($request->from, $request->to, $jahitId);

                $permak = (float) ($batch['permak'] ?? 0);
                $tres = (float) ($batch['tres'] ?? 0);
                $lain2 = (float) ($batch['lain2'] ?? 0);

                $existing = $this->findExistingBorongan($request->from, $request->to, $jahitId);

                if ($existing) {
                    if (empty($boronganItems)) {
                        $existing->update([
                            'permak' => $permak,
                            'tres' => $tres,
                            'lain2' => $lain2,
                        ]);
                        $this->recalculateBoronganTotal($existing);
                        $updated++;

                        continue;
                    }

                    $borongan = $existing;
                    $borongan->permak = $permak;
                    $borongan->tres = $tres;
                    $borongan->lain2 = $lain2;
                    $borongan->save();
                    $this->appendBoronganItems($borongan, $boronganItems);
                    $updated++;

                    continue;
                }

                if (empty($boronganItems)) {
                    continue;
                }

                $borongan = new Borongan;
                $borongan->date = Carbon::now()->toDateString();
                $borongan->user_id = $request->user()->id;
                $borongan->jahit_id = $jahitId;
                $borongan->permak = $permak;
                $borongan->tres = $tres;
                $borongan->lain2 = $lain2;
                $borongan->from = $request->from;
                $borongan->to = $request->to;
                $borongan->total_items = 0;
                $borongan->total = bcadd((string) $permak, bcadd((string) $tres, (string) $lain2, 2), 2);
                $borongan->save();

                $this->appendBoronganItems($borongan, $boronganItems);
                $created++;
            }

            DB::commit();

            if ($created === 0 && $updated === 0) {
                return back()->withInput()->with('error', 'Tidak ada data produksi yang bisa diborongan pada rentang tanggal tersebut.');
            }

            $message = collect([
                $created > 0 ? "{$created} borongan baru" : null,
                $updated > 0 ? "{$updated} borongan diperbarui" : null,
            ])->filter()->implode(', ');

            return redirect()->route('borongan.index')->with('success', "Berhasil menyimpan: {$message}.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Borongan $borongan)
    {
        Gate::authorize(Borongan::getPermissions()['view-details']);

        $borongan->load(['jahit', 'user']);
        $details = $borongan->details()->with(['item', 'produksi'])->get();

        return view('borongan.show', [
            'borongan' => $borongan,
            'details' => $details,
            'can' => [
                'edit_borongan' => auth()->user()->can(Borongan::getPermissions()['edit']),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function edit(Borongan $borongan)
    {
        Gate::authorize(Borongan::getPermissions()['edit']);

        $borongan->load(['jahit', 'user']);
        $details = $borongan->details()->with(['item', 'produksi'])->get();

        return view('borongan.edit', [
            'borongan' => $borongan,
            'details' => $details,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function update(Request $request, Borongan $borongan)
    {
        Gate::authorize(Borongan::getPermissions()['edit']);

        $validated = $request->validate([
            'permak' => 'nullable|numeric|min:0',
            'tres' => 'nullable|numeric|min:0',
            'lain2' => 'nullable|numeric|min:0',
        ]);

        $borongan->update([
            'permak' => (float) ($validated['permak'] ?? 0),
            'tres' => (float) ($validated['tres'] ?? 0),
            'lain2' => (float) ($validated['lain2'] ?? 0),
        ]);

        $this->recalculateBoronganTotal($borongan);

        return redirect()->route('borongan.show', $borongan)->with('success', 'Borongan berhasil diperbarui.');
    }

    /**
     * Provide Ajax Data for Create Form — grouped by jahit worker.
     */
    public function getAjaxBorongan(Request $request)
    {
        Gate::authorize(Borongan::getPermissions()['create']);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        try {
            return response()->json($this->findBoronganGrouped($request->from, $request->to));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return list<array{jahit_id: int, jahit_name: string, items: list<array<string, mixed>>, subtotal: float, total_qty: int}>
     */
    protected function findBoronganGrouped(string $from, string $to): array
    {
        if (! $from || ! $to) {
            throw new \Exception('Tanggal error');
        }

        $rows = Produksi::with(['item', 'jahit'])
            ->whereDate('gudang_date', '>=', $from)
            ->whereDate('gudang_date', '<=', $to)
            ->where('status', Produksi::STATUS_GUDANG)
            ->where('item_id', '>', 0)
            ->orderBy('jahit_id')
            ->get();

        $grouped = [];

        foreach ($rows as $val) {
            $jahitId = (int) $val->jahit_id;
            if (! $jahitId) {
                continue;
            }

            if (! isset($grouped[$jahitId])) {
                $existing = $this->findExistingBorongan($from, $to, $jahitId);
                $grouped[$jahitId] = [
                    'jahit_id' => $jahitId,
                    'jahit_name' => $val->jahit->name ?? 'Unknown',
                    'jahit_link' => route('produksi.jahit.show', $jahitId),
                    'borongan_id' => $existing?->id,
                    'existing_permak' => (float) ($existing?->permak ?? 0),
                    'existing_tres' => (float) ($existing?->tres ?? 0),
                    'existing_lain2' => (float) ($existing?->lain2 ?? 0),
                    'is_append' => $existing !== null,
                    'items' => [],
                    'subtotal' => 0.0,
                    'total_qty' => 0,
                ];
            }

            $ongkos = $this->ongkos($val->item);
            $total = (float) bcmul((string) $val->quantity, (string) $ongkos, 2);
            $code = $val->item ? $val->item->getItemCode() : $val->temp_name;

            $grouped[$jahitId]['items'][] = [
                'produksi_id' => $val->id,
                'item_id' => $val->item_id,
                'item' => $val->item ? $val->item->toArray() : null,
                'quantity' => $val->quantity,
                'serial' => $val->serial,
                'ongkos' => $ongkos,
                'total' => $total,
                'code' => $code,
                'edit_link' => route('produksi.setoran.edit', ['produksi' => $val->id]),
            ];
            $grouped[$jahitId]['subtotal'] += $total;
            $grouped[$jahitId]['total_qty'] += (int) $val->quantity;
        }

        return array_values($grouped);
    }

    /**
     * Helper to find prod_produksi for one jahit worker.
     *
     * @return list<array<string, mixed>>
     */
    protected function findBorongan($from, $to, $jahit_id)
    {
        if (! $from || ! $to) {
            throw new \Exception('Tanggal error');
        }

        if (! $jahit_id) {
            throw new \Exception('Penjahit tidak boleh kosong');
        }

        $data = Produksi::with(['item'])
            ->whereDate('gudang_date', '>=', $from)
            ->whereDate('gudang_date', '<=', $to)
            ->where('status', '=', Produksi::STATUS_GUDANG)
            ->where('jahit_id', '=', $jahit_id)
            ->where('item_id', '>', 0)
            ->get();

        $boronganArray = [];

        foreach ($data as $val) {
            $ongkos = $this->ongkos($val->item);
            $total = (float) bcmul((string) $val->quantity, (string) $ongkos, 2);
            $code = $val->item ? $val->item->getItemCode() : $val->temp_name;

            $boronganArray[] = [
                'produksi_id' => $val->id,
                'item_id' => $val->item_id,
                'item' => $val->item ? $val->item->toArray() : null,
                'quantity' => $val->quantity,
                'serial' => $val->serial,
                'ongkos' => $ongkos,
                'total' => $total,
                'code' => $code,
                'edit_link' => route('produksi.setoran.edit', ['produksi' => $val->id]),
            ];
        }

        return $boronganArray;
    }

    /**
     * Helper to get ongkos jahit from item's tags
     */
    protected function ongkos($item)
    {
        if (! $item) {
            return 0;
        }

        $ongkosTag = $item->tags()->where('tags.type', Tag::TYPE_JAHIT)->first();
        if (! $ongkosTag) {
            return 0;
        }

        return $ongkosTag->price ?? 0;
    }

    protected function findExistingBorongan(string $from, string $to, int $jahitId): ?Borongan
    {
        return Borongan::query()
            ->where('jahit_id', $jahitId)
            ->whereDate('from', $from)
            ->whereDate('to', $to)
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $boronganItems
     */
    protected function appendBoronganItems(Borongan $borongan, array $boronganItems): void
    {
        $existingProduksiIds = $borongan->details()->pluck('produksi_id')->all();

        foreach ($boronganItems as $value) {
            if (in_array($value['produksi_id'], $existingProduksiIds, true)) {
                continue;
            }

            BoronganDetail::create([
                'borongan_id' => $borongan->id,
                'item_id' => $value['item_id'],
                'produksi_id' => $value['produksi_id'],
                'ongkos' => $value['ongkos'],
                'quantity' => $value['quantity'],
                'total' => $value['total'],
            ]);

            $produksi = Produksi::find($value['produksi_id']);
            if ($produksi) {
                $produksi->status = Produksi::STATUS_BOTH;
                $produksi->save();
            }
        }

        $this->recalculateBoronganTotal($borongan);
    }

    protected function recalculateBoronganTotal(Borongan $borongan): void
    {
        $itemTotal = (string) $borongan->details()->sum('total');
        $fees = bcadd((string) $borongan->permak, bcadd((string) $borongan->tres, (string) $borongan->lain2, 2), 2);

        $borongan->update([
            'total' => bcadd($itemTotal, $fees, 2),
            'total_items' => (int) $borongan->details()->sum('quantity'),
        ]);
    }
}
