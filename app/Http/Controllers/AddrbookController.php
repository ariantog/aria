<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Enums\ItemType;
use App\Enums\TransactionType;
use App\Http\Requests\StoreAddrbookRequest;
use App\Http\Requests\UpdateAddrbookRequest;
use App\Models\Addrbook;
use App\Models\StatSell;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AddrbookController extends Controller
{
    public function index(?string $type = null)
    {
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
            ->when(request('trashed') === 'with', fn ($q) => $q->withTrashed())
            ->when(request('trashed') === 'only', fn ($q) => $q->onlyTrashed())
            ->when($typeId, fn ($q) => $q->where('type', $typeId))
            ->when(request('type') && ! $typeId, fn ($q) => $q->where('type', request('type')))
            ->when($s = request('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('contact_person', 'like', "%{$s}%")
                ->orWhere('id', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('member_id', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")
                ->orWhere('address', 'like', "%{$s}%")
            ))
            ->latest();

        if ((request()->wantsJson() || request()->has('json')) && ! request()->header('X-Inertia')) {
            return $q->limit(20)->get(['id', 'code', 'name', 'alias', 'ppn']);
        }

        return Inertia::render('Addrbook/Index', [
            'addrbooks' => $q->paginate(10)->withQueryString(),
            'filters' => request()->all(['search', 'type', 'trashed']),
            'can' => [
                'create' => request()->user()?->can(Addrbook::getPermissions($type)['create']) ?? false,
                'edit' => request()->user()?->can(Addrbook::getPermissions($type)['edit']) ?? false,
                'delete' => request()->user()?->can(Addrbook::getPermissions($type)['delete']) ?? false,
            ],
            'current_type' => $type,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    public function create(?string $type = null)
    {
        Gate::authorize(Addrbook::getPermissions($type)['create']);

        $pt = null;
        if ($type) {
            $d = collect(Addrbook::getTypes())->firstWhere('slug', $type);
            $pt = $d ? $d['id'] : null;
        }

        return Inertia::render('Addrbook/Create', [
            'types' => Addrbook::getTypes(),
            'preselected_type_id' => $pt,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    public function store(StoreAddrbookRequest $r)
    {
        $td = collect(Addrbook::getTypes())->firstWhere('id', $r->type);
        Gate::authorize(Addrbook::getPermissions($td['slug'] ?? null)['create']);

        $a = Addrbook::create($r->validated());
        $a->stat()->create(['balance' => $r->input('initial_balance', 0)]);

        return redirect()->route('addrbook.index')->with('success', 'Created.');
    }

    public function show(Addrbook $a)
    {
        $slug = $a->type_slug;
        Gate::authorize(Addrbook::getPermissions($slug)['view']);

        $load = ['stat', 'dailies' => fn ($q) => $q->latest('date')->limit(50)];

        if ($a->type === AddrbookType::Warehouse) {
            $load[] = 'items';
        }

        $a->load($load);

        if ($a->type === AddrbookType::Warehouse) {
            $a->items->each(function ($i) {
                $c = $i->type === ItemType::ASSET_LANCAR
                    ? (float) $i->cost
                    : (float) $i->price * 0.3;
                $i->calculated_cost = $c;
                $i->total_calculated_cost = $c * (float) $i->pivot->quantity;
            });
        }

        return Inertia::render('Addrbook/Show', [
            'addrbook' => $a,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    public function showType(string $t, Addrbook $a) { return $this->show($a); }
    public function transactionsType(string $t, Addrbook $a) { return $this->transactions($a->id); }
    public function itemsType(string $t, Addrbook $a) { return $this->items($a->id); }
    public function statType(string $t, Addrbook $a) { return $this->stat($a->id); }
    public function itemSalesType(string $t, Addrbook $a) { return $this->itemSales($a->id); }

    public function edit(Addrbook $a)
    {
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['edit']);

        return Inertia::render('Addrbook/Edit', [
            'addrbook' => $a->load('stat'),
            'types' => Addrbook::getTypes(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    public function editType(string $t, Addrbook $a) { return $this->edit($a); }

    public function update(UpdateAddrbookRequest $r, Addrbook $a)
    {
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['edit']);
        $a->update($r->validated());

        return redirect()->route('addrbook.index')->with('success', 'Updated.');
    }

    public function transactions($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['view']);

        $q = \App\Models\Transaction::where(fn ($q) => $q
            ->where('sender_id', $a->id)
            ->orWhere('receiver_id', $a->id)
        )
            ->with(['sender', 'receiver', 'user'])
            ->when(request('from'), fn ($q) => $q->whereDate('date', '>=', request('from')))
            ->when(request('to'), fn ($q) => $q->whereDate('date', '<=', request('to')))
            ->when(request('type'), fn ($q) => $q->where('type', request('type')));

        if (request('order_date', 'date') === 'created_at') {
            $q->orderBy('created_at', 'desc');
        } else {
            $q->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        return Inertia::render('Addrbook/Transactions', [
            'addrbook' => $a,
            'transactions' => $q->paginate(50)->withQueryString(),
            'transactionTypes' => \App\Models\Transaction::getTypes(),
            'filters' => request()->all(['from', 'to', 'type', 'order_date']),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function items($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['view']);

        $q = $a->items()->with('group')
            ->when(request('name'), fn ($q) => $q->where(fn ($sq) => $sq
                ->where('items.name', 'like', '%'.request('name').'%')
                ->orWhere('items.code', 'like', '%'.request('name').'%')
            ))
            ->when(request('show0') !== 'show', fn ($q) => $q->wherePivot('quantity', '>', 0));

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

        return Inertia::render('Addrbook/Items', [
            'addrbook' => $a,
            'items' => $q->paginate(50)->withQueryString(),
            'filters' => request()->all(['name', 'sort', 'show0']),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function itemSales($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['view']);

        $q = StatSell::where('sender_id', $a->id)->with('group')
            ->when(request('bulan'), fn ($q) => $q->where('bulan', request('bulan')))
            ->when(request('tahun'), fn ($q) => $q->where('tahun', request('tahun')))
            ->when(request('search'), fn ($q) => $q->whereHas('group', fn ($gq) => $gq
                ->where('name', 'like', '%'.request('search').'%')
                ->orWhere('description', 'like', '%'.request('search').'%')
            ))
            ->when(request('type'), fn ($q) => $q->where('type', request('type')))
            ->orderBy('tahun', 'desc')->orderBy('bulan', 'desc');

        return Inertia::render('Addrbook/ItemSales', [
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
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function stat($id)
    {
        $a = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['view']);

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
            $cat = match ($op->type instanceof AddrbookType ? $op->type : AddrbookType::tryFrom($op->type ?? 99) ?? AddrbookType::Other) {
                AddrbookType::Customer => 'customer',
                AddrbookType::Reseller => 'reseller',
                AddrbookType::Account => 'journal',
                AddrbookType::Bank => 'bank',
                AddrbookType::Warehouse => 'warehouse',
                default => 'other',
            };

            $amt = (float) $t->grand_total;

            if ($t->type == TransactionType::CashIn->value) {
                $ds['cash_in'][$cat] += $amt;
                $ds['cash_in']['total'] += $amt;
            } elseif ($t->type == TransactionType::CashOut->value) {
                $ds['cash_out'][$cat] += $amt;
                $ds['cash_out']['total'] += $amt;
            } elseif ($t->type == TransactionType::Sell->value) {
                $ds['sell'][$cat] += $amt;
                $ds['sell']['total'] += $amt;
            } elseif ($t->type == TransactionType::Return->value) {
                $ds['return'][$cat] += $amt;
                $ds['return']['total'] += $amt;
            }
        }

        return Inertia::render('Addrbook/Stats', [
            'addrbook' => $a,
            'dataStat' => $ds,
            'filters' => ['month' => (int) $mo, 'year' => (int) $yr],
            'years' => range(date('Y'), date('Y') - 5),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function destroy(Addrbook $a)
    {
        Gate::authorize(Addrbook::getPermissions($a->type_slug)['delete']);
        $a->delete();

        return redirect()->route('addrbook.index')->with('success', 'Deleted.');
    }
}
