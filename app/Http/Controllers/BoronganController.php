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
use Inertia\Inertia;

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

        return Inertia::render('Borongan/Index', [
            'borongans' => $borongans,
            'filters' => $request->only(['from', 'to', 'jahit_id']),
            'can' => [
                'create_borongan' => auth()->user()->can(Borongan::getPermissions()['create']),
                'view_borongan' => auth()->user()->can(Borongan::getPermissions()['view_details']),
                'delete_borongan' => auth()->user()->can(Borongan::getPermissions()['delete']),
            ],
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
        $jahitList = Worker::jahit()->get();

        return Inertia::render('Borongan/Create', [
            'jahitList' => $jahitList,
            'defaultFrom' => $from,
            'defaultTo' => $to,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize(Borongan::getPermissions()['create']);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'jahit_id' => 'required|exists:workers,id',
            'permak' => 'nullable|numeric|min:0',
            'tres' => 'nullable|numeric|min:0',
            'lain2' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $boronganItems = $this->findBorongan($request->from, $request->to, $request->jahit_id);

            if (empty($boronganItems)) {
                return back()->with('error', 'Tidak ada data produksi yang bisa diborongan pada rentang tanggal tersebut.');
            }

            $permak = $request->input('permak', 0);
            $tres = $request->input('tres', 0);
            $lain2 = $request->input('lain2', 0);

            $borongan = new Borongan;
            $borongan->date = Carbon::now()->toDateString();
            $borongan->user_id = $request->user()->id;
            $borongan->jahit_id = $request->jahit_id;
            $borongan->permak = $permak;
            $borongan->tres = $tres;
            $borongan->lain2 = $lain2;
            $borongan->from = $request->from;
            $borongan->to = $request->to;
            $borongan->total_items = 0;
            $borongan->total = bcadd($permak, bcadd($tres, $lain2, 2), 2);
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

                // Update produksi status
                $produksi = Produksi::find($value['produksi_id']);
                if ($produksi) {
                    $produksi->status = Produksi::STATUS_BOTH;
                    $produksi->save();
                }

                $borongan->total = bcadd((string) $borongan->total, (string) $value['total'], 2);
                $borongan->total_items += $value['quantity'];
            }

            $borongan->save();

            DB::commit();

            return redirect()->route('borongan.index')->with('success', 'Data Borongan berhasil disimpan.');

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
        Gate::authorize(Borongan::getPermissions()['view_details']);

        $borongan->load(['jahit', 'user']);
        $details = $borongan->details()->with(['item', 'produksi'])->get();

        return Inertia::render('Borongan/Show', [
            'borongan' => $borongan,
            'details' => $details,
        ]);
    }

    /**
     * Provide Ajax Data for Create Form
     */
    public function getAjaxBorongan(Request $request)
    {
        Gate::authorize(Borongan::getPermissions()['create']);

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date',
            'jahit_id' => 'required|exists:workers,id',
        ]);

        try {
            $data = $this->findBorongan($request->from, $request->to, $request->jahit_id);

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Helper to find produksis
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
            ->where('gudang_date', '>=', $from)
            ->where('gudang_date', '<=', $to)
            ->where('status', '=', Produksi::STATUS_GUDANG)
            ->where('jahit_id', '=', $jahit_id)
            ->where('item_id', '>', 0) // fail-safe (pastikan bukan nama temp)
            ->get();

        $boronganArray = [];

        foreach ($data as $key => $val) {
            $ongkos = $this->ongkos($val->item);
            $total = (float) bcmul((string) $val->quantity, (string) $ongkos, 2);

            $code = $val->item ? $val->item->getItemCode() : $val->temp_name;

            $boronganArray[] = [
                'produksi_id' => $val->id,
                'item_id' => $val->item_id,
                'item' => $val->item ? $val->item->toArray() : null,
                'quantity' => $val->quantity,
                'serial' => $val->serial, // Menggunakan attribute accessor
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
