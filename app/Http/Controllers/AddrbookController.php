<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Http\Requests\StoreAddrbookRequest;
use App\Http\Requests\UpdateAddrbookRequest;
use App\Models\Addrbook;
use App\Models\Location;
use App\Models\StatSell;
use App\Support\LikeSearch;
use Illuminate\Support\Facades\Gate;

class AddrbookController extends Controller
{
    public function index(?string $type = null)
    {
        if ($type === null) {
            abort(404);
        }

        Gate::authorize(Addrbook::getPermissions($type)['view']);

        $typeId = null;
        if ($type) {
            $d = collect(Addrbook::getTypes())->firstWhere('slug', $type);
            if (! $d) {
                abort(404);
            }
            $typeId = $d['id'];
        }

        $q = Addrbook::with(['stat'])
            ->visibleToUser(request()->user())
            ->when(request('trashed') === 'with', fn ($q) => $q->withTrashed())
            ->when(request('trashed') === 'only', fn ($q) => $q->onlyTrashed())
            ->when($typeId, fn ($q) => $q->where('type', $typeId))
            ->when(request('type') && ! $typeId, fn ($q) => $q->where('type', request('type')))
            ->when($s = request('search'), function ($q) use ($s) {
                $pattern = LikeSearch::contains($s);

                return $q->where(fn ($q) => $q
                    ->where('name', 'like', $pattern)
                    ->orWhere('contact_person', 'like', $pattern)
                    ->orWhere('id', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)
                    ->orWhere('memberId', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern)
                    ->orWhere('address', 'like', $pattern)
                );
            })
            ->latest();

        // Combobox / autocomplete requests (?json=1)
        if ($this->isJsonRequest()) {
            return $q->limit(20)->get(['id', 'code', 'name', 'alias', 'ppn']);
        }

        $can = [
            'create' => request()->user()?->can(Addrbook::getPermissions($type)['create']) ?? false,
            'edit' => request()->user()?->can(Addrbook::getPermissions($type)['edit']) ?? false,
            'delete' => request()->user()?->can(Addrbook::getPermissions($type)['delete']) ?? false,
            'bank_hidden_balance' => ! (request()->user()?->is_superadmin ?? false) && (request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false),
        ];

        // Tabulator AJAX requests
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json($q->paginate((int) request('size', 50))->withQueryString());
        }

        return view('addrbook.index', [
            'customers' => $q->paginate(50)->withQueryString(),
            'filters' => request()->all(['search', 'type', 'trashed']),
            'can' => $can,
            'current_type' => $type,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function create(?string $type = null)
    {
        if ($type === null) {
            abort(404);
        }

        Gate::authorize(Addrbook::getPermissions($type)['create']);

        $pt = null;
        if ($type) {
            $d = collect(Addrbook::getTypes())->firstWhere('slug', $type);
            $pt = $d ? $d['id'] : null;
        }

        return view('addrbook.create', [
            'types' => Addrbook::getTypes(),
            'preselected_type_id' => $pt,
            'current_type' => $type,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            ...$this->locationFormProps(),
            ...$this->arrangementFormProps(),
        ]);
    }

    public function store(StoreAddrbookRequest $r)
    {
        $td = collect(Addrbook::getTypes())->firstWhere('id', $r->type);
        Gate::authorize(Addrbook::getPermissions($td['slug'] ?? null)['create']);

        $a = Addrbook::create($r->safe()->except(['location_ids', 'arrangement_source_ids', 'initial_balance']));
        $a->stat()->create(['balance' => $r->input('initial_balance', 0)]);
        $this->syncAddrbookLocations($a, $r->input('location_ids', []));
        $this->syncArrangementSources($a, $r->input('arrangement_source_ids', []));

        return redirect()->to(Addrbook::typeIndexRoute((int) $a->type))->with('success', 'Created.');
    }

    public function show(Addrbook $addrbook)
    {
        $a = $addrbook;
        $slug = $this->addrbookTypeSlug($a);
        Gate::authorize(Addrbook::getPermissions($slug)['view']);
        $this->authorizeAddrbookLocation($a);

        $load = ['stat', 'dailies' => fn ($q) => $q->latest('date')->limit(50)];

        if ($this->addrbookIsWarehouse($a)) {
            $load[] = 'items';
        }

        $a->load($load);

        if ($this->addrbookIsWarehouse($a) && $a->relationLoaded('items')) {
            $a->items->each(function ($i) {
                $c = ($i->type instanceof ItemType && $i->type === ItemType::ASSET_LANCAR)
                    ? (float) $i->cost
                    : (float) $i->price * 0.3;
                $i->calculated_cost = $c;
                $i->total_calculated_cost = $c * (float) ($i->pivot->quantity ?? 0);
            });
        }

        return view('addrbook.show', [
            'addrbook' => $a,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function showType(string $type, Addrbook $addrbook)
    {
        return $this->show($addrbook);
    }

    public function transactionsType(string $type, Addrbook $addrbook)
    {
        return $this->transactions($addrbook->id);
    }

    public function itemsType(string $type, Addrbook $addrbook)
    {
        return $this->items($addrbook->id);
    }

    public function statType(string $type, Addrbook $addrbook)
    {
        return $this->stat($addrbook->id);
    }

    public function itemSalesType(string $type, Addrbook $addrbook)
    {
        return $this->itemSales($addrbook->id);
    }

    public function edit(Addrbook $addrbook)
    {
        $a = $addrbook;
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['edit']);
        $this->authorizeAddrbookLocation($a);

        return view('addrbook.edit', [
            'addrbook' => $a->load(['stat', 'locations', 'arrangementSources']),
            'types' => Addrbook::getTypes(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
            ...$this->locationFormProps($a),
            ...$this->arrangementFormProps($a),
        ]);
    }

    public function editType(string $type, Addrbook $addrbook)
    {
        return $this->edit($addrbook);
    }

    public function update(UpdateAddrbookRequest $r, Addrbook $addrbook)
    {
        $a = $addrbook;
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['edit']);
        $a->update($r->safe()->except(['location_ids', 'arrangement_source_ids']));
        $this->syncAddrbookLocations($a, $r->input('location_ids', []));
        $this->syncArrangementSources($a, $r->input('arrangement_source_ids', []));

        return redirect()->to(Addrbook::typeIndexRoute((int) $a->type))->with('success', 'Updated.');
    }

    public function transactions($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['view']);
        $this->authorizeAddrbookLocation($a);

        $q = \App\Models\Transaction::where(fn ($q) => $q
            ->where('sender_id', $a->id)
            ->orWhere('receiver_id', $a->id)
        )
            ->visibleToUser(request()->user())
            ->with(['sender', 'receiver', 'user'])
            ->when(request('from'), fn ($q) => $q->whereDate('date', '>=', request('from')))
            ->when(request('to'), fn ($q) => $q->whereDate('date', '<=', request('to')))
            // Use query() so the {type} route segment (customer/supplier/…) does not leak
            // into request('type') and filter transactions by a non-numeric type.
            ->when(request()->query('type'), fn ($q) => $q->where('type', request()->query('type')));

        if (request('order_date', 'date') === 'created_at') {
            $q->orderBy('created_at', 'desc');
        } else {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json($q->paginate((int) request('size', 50))->withQueryString());
        }

        return view('addrbook.transactions', [
            'addrbook' => $a,
            'transactions' => $q->paginate(50)->withQueryString(),
            'transactionTypes' => \App\Models\Transaction::getTypes(),
            'filters' => request()->all(['from', 'to', 'type', 'order_date']),
            'can' => [
                'bank_hidden_balance' => ! (request()->user()?->is_superadmin ?? false) && (request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function items($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['view']);
        $this->authorizeAddrbookLocation($a);

        $q = $a->items()->with('group')
            ->when(request('name'), fn ($q) => $q->where(fn ($sq) => $sq
                ->where('items.name', 'like', '%'.request('name').'%')
                ->orWhere('items.code', 'like', '%'.request('name').'%')
            ))
            // Qualified pivot column: inside a when() closure the callback receives the base
            // query builder, where wherePivot() is unavailable and degrades to a broken where.
            ->when(request('show0') !== 'show', fn ($q) => $q->where('warehouse_item.quantity', '>', 0));

        $sort = request('sort', 'qtydesc');
        match ($sort) {
            'qtyasc' => $q->orderByPivot('quantity', 'asc'),
            'codedesc' => $q->orderBy('items.code', 'desc'),
            'codeasc' => $q->orderBy('items.code', 'asc'),
            'namedesc' => $q->orderBy('items.name', 'desc'),
            'nameasc' => $q->orderBy('items.name', 'asc'),
            'iddesc' => $q->orderBy('items.id', 'desc'),
            'idasc' => $q->orderBy('items.id', 'asc'),
            default => $q->orderByPivot('quantity', 'desc'),
        };

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json($q->paginate((int) request('size', 50))->withQueryString());
        }

        return view('addrbook.items', [
            'addrbook' => $a,
            'items' => $q->paginate(50)->withQueryString(),
            'filters' => request()->all(['name', 'sort', 'show0']),
            'can' => [
                'bank_hidden_balance' => ! (request()->user()?->is_superadmin ?? false) && (request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function itemSales($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['view']);
        $this->authorizeAddrbookLocation($a);

        $q = StatSell::where('sender_id', $a->id)->with('group')
            ->when(request('bulan'), fn ($q) => $q->where('bulan', request('bulan')))
            ->when(request('tahun'), fn ($q) => $q->where('tahun', request('tahun')))
            ->when(request('search'), fn ($q) => $q->whereHas('group', fn ($gq) => $gq
                ->where('name', 'like', '%'.request('search').'%')
                ->orWhere('description', 'like', '%'.request('search').'%')
            ))
            ->when(request('type'), fn ($q) => $q->where('type', request('type')))
            ->orderBy('tahun', 'desc')->orderBy('bulan', 'desc');

        return view('addrbook.item-sales', [
            'addrbook' => $a,
            'sales' => $q->paginate(50)->withQueryString(),
            'filters' => [
                'bulan' => request('bulan') ? (int) request('bulan') : null,
                'tahun' => request('tahun') ? (int) request('tahun') : null,
                'search' => request('search'),
                'type' => request('type') ? (int) request('type') : null,
            ],
            'years' => range(date('Y'), date('Y') - 5),
            'transactionTypes' => \App\Models\Transaction::getTypes(),
            'can' => [
                'bank_hidden_balance' => ! (request()->user()?->is_superadmin ?? false) && (request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function stat($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['view']);
        $this->authorizeAddrbookLocation($a);

        $mo = request('month');
        $yr = request('year', date('Y'));

        $tx = \App\Models\Transaction::where(fn ($q) => $q
            ->where('sender_id', $a->id)
            ->orWhere('receiver_id', $a->id)
        )
            ->whereYear('date', $yr)
            ->when($mo, fn ($q) => $q->whereMonth('date', $mo))
            ->with(['sender', 'receiver'])
            ->get();

        $ds = [];
        foreach (['cash_in', 'cash_out', 'sell', 'return'] as $m) {
            foreach (['customer', 'reseller', 'journal', 'bank', 'warehouse', 'other'] as $c) {
                $ds[$m][$c] = 0;
            }
            $ds[$m]['total'] = 0;
        }

        foreach ($tx as $t) {
            $op = ($t->sender_id == $a->id) ? $t->receiver : $t->sender;
            if (! $op) {
                continue;
            }

            $cat = $this->categorizeAddrbook($op);
            $amt = (float) $t->real_total;
            $txType = $t->type instanceof TransactionType ? $t->type->value : $t->type;

            if ($txType == TransactionType::CashIn->value) {
                $ds['cash_in'][$cat] += $amt;
                $ds['cash_in']['total'] += $amt;
            } elseif ($txType == TransactionType::CashOut->value) {
                $ds['cash_out'][$cat] += $amt;
                $ds['cash_out']['total'] += $amt;
            } elseif ($txType == TransactionType::Sell->value) {
                $ds['sell'][$cat] += $amt;
                $ds['sell']['total'] += $amt;
            } elseif ($txType == TransactionType::Return->value) {
                $ds['return'][$cat] += $amt;
                $ds['return']['total'] += $amt;
            }
        }

        return view('addrbook.stats', [
            'addrbook' => $a,
            'dataStat' => $ds,
            'filters' => ['month' => (int) $mo, 'year' => (int) $yr],
            'years' => range(date('Y'), date('Y') - 5),
            'can' => [
                'bank_hidden_balance' => ! (request()->user()?->is_superadmin ?? false) && (request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false),
            ],
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function destroy(Addrbook $addrbook)
    {
        $a = $addrbook;
        Gate::authorize(Addrbook::getPermissions($this->addrbookTypeSlug($a))['delete']);
        $a->delete();

        return redirect()->to(Addrbook::typeIndexRoute((int) $a->type))->with('success', 'Deleted.');
    }

    private function authorizeAddrbookLocation(Addrbook $addrbook): void
    {
        abort_unless(
            app(\App\Services\LocationAccessService::class)->canAccessAddrbook(request()->user(), $addrbook),
            403
        );
    }

    private function isJsonRequest(): bool
    {
        return (request()->wantsJson() || request()->has('json')) && ! request()->header('X-Inertia');
    }

    private function addrbookTypeSlug(Addrbook $a): ?string
    {
        $type = $a->type instanceof AddrbookType
            ? $a->type
            : AddrbookType::tryFrom((int) $a->type);

        return $type?->slug();
    }

    private function addrbookIsWarehouse(Addrbook $a): bool
    {
        return Addrbook::typeIsWarehouse((int) $a->type);
    }

    private function categorizeAddrbook($addrbook): string
    {
        $type = $addrbook->type;
        if ($type instanceof AddrbookType) {
            return match ($type) {
                AddrbookType::Customer => 'customer',
                AddrbookType::Reseller => 'reseller',
                AddrbookType::Account => 'journal',
                AddrbookType::Bank => 'bank',
                AddrbookType::Warehouse, AddrbookType::VirtualWarehouse => 'warehouse',
                default => 'other',
            };
        }

        // Fallback for uncast integer types
        return match ((int) $type) {
            AddrbookType::Customer->value => 'customer',
            AddrbookType::Reseller->value => 'reseller',
            AddrbookType::Account->value => 'journal',
            AddrbookType::Bank->value => 'bank',
            AddrbookType::Warehouse->value, AddrbookType::VirtualWarehouse->value => 'warehouse',
            default => 'other',
        };
    }

    /**
     * @return array{locations: \Illuminate\Support\Collection, selectedLocationIds: \Illuminate\Support\Collection<int, int>}
     */
    private function locationFormProps(?Addrbook $addrbook = null): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->get(),
            'selectedLocationIds' => $addrbook?->locations->pluck('id') ?? collect(),
        ];
    }

    private function syncAddrbookLocations(Addrbook $addrbook, array $locationIds): void
    {
        $type = $addrbook->type instanceof AddrbookType
            ? $addrbook->type
            : AddrbookType::tryFrom((int) $addrbook->type);

        if (! in_array($type, [AddrbookType::Customer, AddrbookType::Warehouse], true)) {
            return;
        }

        $addrbook->locations()->sync($locationIds);
    }

    private function syncArrangementSources(Addrbook $addrbook, array $sourceIds): void
    {
        if ((int) $addrbook->type !== Addrbook::TYPE_WAREHOUSE || ! $addrbook->arrangement_enabled) {
            $addrbook->arrangementSources()->sync([]);

            return;
        }

        $sourceIds = collect($sourceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $addrbook->id)
            ->unique()
            ->values()
            ->all();

        $sourceIds = Addrbook::query()
            ->where('type', AddrbookType::Warehouse)
            ->whereIn('id', $sourceIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $addrbook->arrangementSources()->sync($sourceIds);
    }

    /**
     * @return array{
     *     arrangementWarehouses: \Illuminate\Support\Collection,
     *     selectedArrangementSourceIds: \Illuminate\Support\Collection<int, int>
     * }
     */
    private function arrangementFormProps(?Addrbook $addrbook = null): array
    {
        return [
            'arrangementWarehouses' => Addrbook::query()
                ->where('type', AddrbookType::Warehouse)
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedArrangementSourceIds' => $addrbook?->arrangementSources->pluck('id') ?? collect(),
        ];
    }
}
