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

        $borongans = $query->latest('id')->paginate(30)->withQueryString();

        return view('borongan.index', [
            'borongans' => $borongans,
            'filters' => $request->only(['from', 'to', 'jahit_id']),
            'jahitList' => Worker::jahit()->get(),
            'can' => [
                'create_borongan' => auth()->user()->can(Borongan::getPermissions()['create']),
                'view_borongan' => auth()->user()->can(Borongan::getPermissions()['view-details']),
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
            'batches.*.jahit_id' => 'required|exists:workers,id',
            'batches.*.permak' => 'nullable|numeric|min:0',
            'batches.*.tres' => 'nullable|numeric|min:0',
            'batches.*.lain2' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $created = 0;

            foreach ($request->input('batches', []) as $batch) {
                $jahitId = (int) $batch['jahit_id'];
                $boronganItems = $this->findBorongan($request->from, $request->to, $jahitId);

                if (empty($boronganItems)) {
                    continue;
                }

                $permak = (float) ($batch['permak'] ?? 0);
                $tres = (float) ($batch['tres'] ?? 0);
                $lain2 = (float) ($batch['lain2'] ?? 0);

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

                foreach ($boronganItems as $value) {
                    $detail = new BoronganDetail;
                    $detail->borongan_id = $borongan->id;
                    $detail->item_id = $value['item_id'];
                    $detail->produksi_id = $value['produksi_id'];
                    $detail->ongkos = $value['ongkos'];
                    $detail->quantity = $value['quantity'];
                    $detail->total = $value['total'];
                    $detail->save();

                    $produksi = Produksi::find($value['produksi_id']);
                    if ($produksi) {
                        $produksi->status = Produksi::STATUS_BOTH;
                        $produksi->save();
                    }

                    $borongan->total = bcadd((string) $borongan->total, (string) $value['total'], 2);
                    $borongan->total_items += $value['quantity'];
                }

                $borongan->save();
                $created++;
            }

            DB::commit();

            if ($created === 0) {
                return back()->withInput()->with('error', 'Tidak ada data produksi yang bisa diborongan pada rentang tanggal tersebut.');
            }

            return redirect()->route('borongan.index')->with('success', "Berhasil menyimpan {$created} borongan (satu per penjahit).");

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
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
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
                $grouped[$jahitId] = [
                    'jahit_id' => $jahitId,
                    'jahit_name' => $val->jahit->name ?? 'Unknown',
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
     * Helper to find produksis for one jahit worker.
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
}
