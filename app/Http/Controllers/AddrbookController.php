<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddrbookRequest;
use App\Http\Requests\UpdateAddrbookRequest;
use App\Models\Addrbook;
use App\Models\StatSell;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AddrbookController extends Controller
{
    public function __construct() {}

    public function index(?string $type = null)
    {
        Gate::authorize(Addrbook::getPermissions($type)['view']);

        // If type slug is passed, find the corresponding ID using service
        // If type slug is passed, find the corresponding ID from model constants
        $typeId = null;
        if ($type) {
            $types = collect(Addrbook::getTypes());
            $typeData = $types->firstWhere('slug', $type);

            if (! $typeData) {
                // If type is not found in constants, returning 404 is appropriate
                abort(404);
            }
            $typeId = $typeData['id'];
        }

        // Initialize query with eager loads
        $query = Addrbook::with(['stat']);

        // 1. Filter by Trashed (Show Deleted)
        if (request('trashed') === 'with') {
            $query->withTrashed();
        } elseif (request('trashed') === 'only') {
            $query->onlyTrashed();
        }

        // 2. Filter by Type
        if ($typeId) {
            $query->where('type', $typeId);
        } elseif ($requestType = request('type')) {
            $query->where('type', $requestType);
        }

        // 3. Search (inside closure to protect type filter)
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('member_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $query->latest();

        // 4. JSON Response logic...
        if ((request()->wantsJson() || request()->has('json')) && ! request()->header('X-Inertia')) {
            return $query->limit(20)->get(['id', 'code', 'name', 'alias', 'ppn']);
        }

        $results = $query->paginate(10)->withQueryString();

        return Inertia::render('Addrbook/Index', [
            'addrbooks' => $results,
            'filters' => request()->all(['search', 'type', 'trashed']),
            'can' => [
                'create' => request()->user()?->can(Addrbook::getPermissions($type)['create']) ?? false,
                'edit' => request()->user()?->can(Addrbook::getPermissions($type)['edit']) ?? false,
                'delete' => request()->user()?->can(Addrbook::getPermissions($type)['delete']) ?? false,
            ],
            'current_type' => $type, // Pass current type slug to view
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(?string $type = null)
    {
        Gate::authorize(Addrbook::getPermissions($type)['create']);

        // Convert slug to ID if present using model constants
        $preselectedTypeId = null;
        if ($type) {
            $types = collect(Addrbook::getTypes());
            $typeData = $types->firstWhere('slug', $type);
            $preselectedTypeId = $typeData ? $typeData['id'] : null;
        }

        return Inertia::render('Addrbook/Create', [
            'types' => Addrbook::getTypes(),
            'preselected_type_id' => $preselectedTypeId,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddrbookRequest $request)
    {
        $typeData = collect(Addrbook::getTypes())->firstWhere('id', $request->type);
        $typeSlug = $typeData ? $typeData['slug'] : null;
        Gate::authorize(Addrbook::getPermissions($typeSlug)['create']);

        $addrbook = Addrbook::create($request->validated());

        // Initialize default stats
        $addrbook->stat()->create([
            'balance' => $request->input('initial_balance', 0),
        ]);

        return redirect()->route('addrbook.index')
            ->with('success', 'Entry created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Addrbook $addrbook)
    {
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['view']);

        $load = ['stat', 'dailies' => function ($query) {
            $query->latest('date')->limit(50);
        }];

        if ($addrbook->type === Addrbook::TYPE_WAREHOUSE) {
            $load[] = 'items';
        }

        $addrbook->load($load);

        // Calculate costs for warehouse items
        if ($addrbook->type === Addrbook::TYPE_WAREHOUSE) {
            $addrbook->items->each(function ($item) {
                $cost = 0;
                if ($item->type->value === 2) { // ASSET_LANCAR
                    $cost = (float) $item->cost;
                } elseif ($item->type->value === 1) { // ITEM
                    $cost = (float) $item->price * 0.3;
                }
                $item->calculated_cost = $cost;
                $item->total_calculated_cost = $cost * (float) $item->pivot->quantity;
            });
        }

        return Inertia::render('Addrbook/Show', [
            'addrbook' => $addrbook,
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Addrbook $addrbook)
    {
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['edit']);

        $addrbook->load(['stat']);

        return Inertia::render('Addrbook/Edit', [
            'addrbook' => $addrbook,
            'types' => Addrbook::getTypes(),
            'ppn_rate' => (float) \App\Models\Setting::getValue('ppn_rate', 11),
        ]);
    }

    public function editType(string $type, Addrbook $addrbook)
    {
        return $this->edit($addrbook);
    }

    public function update(UpdateAddrbookRequest $request, Addrbook $addrbook)
    {
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['edit']);

        $addrbook->update($request->validated());

        return redirect()->route('addrbook.index')->with('success', 'Address Book entry updated successfully.');
    }

    public function transactions($id)
    {
        $addrbook = Addrbook::withTrashed()->findOrFail($id);

        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['view']);

        $from = request()->query('from');
        $to = request()->query('to');
        $type = request()->query('type');
        $orderDate = request()->query('order_date', 'date');

        $query = \App\Models\Transaction::where(function ($q) use ($addrbook) {
            $q->where('sender_id', $addrbook->id)
                ->orWhere('receiver_id', $addrbook->id);
        })
            ->with(['sender', 'receiver', 'user']);

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($orderDate === 'created_at') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        $transactions = $query->paginate(50)->withQueryString();

        return Inertia::render('Addrbook/Transactions', [
            'addrbook' => $addrbook,
            'transactions' => $transactions,
            'transactionTypes' => \App\Models\Transaction::getTypes(),
            'filters' => request()->all(['from', 'to', 'type', 'order_date']),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function items($id)
    {
        $addrbook = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['view']);

        $name = request()->query('name');
        $sort = request()->query('sort', 'qtydesc');
        $show0 = request()->query('show0') === 'show';

        $query = $addrbook->items()->with('group');

        if ($name) {
            $query->where(function ($q) use ($name) {
                $q->where('items.name', 'like', "%{$name}%")
                    ->orWhere('items.code', 'like', "%{$name}%");
            });
        }

        if (! $show0) {
            $query->wherePivot('quantity', '>', 0);
        }

        switch ($sort) {
            case 'qtyasc':
                $query->orderByPivot('quantity', 'asc');
                break;
            case 'codedesc':
                $query->orderBy('items.code', 'desc');
                break;
            case 'codeasc':
                $query->orderBy('items.code', 'asc');
                break;
            case 'namedesc':
                $query->orderBy('items.name', 'desc');
                break;
            case 'nameasc':
                $query->orderBy('items.name', 'asc');
                break;
            case 'iddesc':
                $query->orderBy('items.id', 'desc');
                break;
            case 'idasc':
                $query->orderBy('items.id', 'asc');
                break;
            default: // qtydesc
                $query->orderByPivot('quantity', 'desc');
                break;
        }

        $items = $query->paginate(50)->withQueryString();

        return Inertia::render('Addrbook/Items', [
            'addrbook' => $addrbook,
            'items' => $items,
            'filters' => request()->all(['name', 'sort', 'show0']),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function itemSales($id)
    {
        $addrbook = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['view']);

        $bulan = request()->query('bulan');
        $tahun = request()->query('tahun');
        $search = request()->query('search');
        $type = request()->query('type');

        $query = StatSell::where('sender_id', $addrbook->id)
            ->with('group');

        if ($bulan) {
            $query->where('bulan', $bulan);
        }

        if ($tahun) {
            $query->where('tahun', $tahun);
        }

        if ($search) {
            $query->whereHas('group', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        $sales = $query->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Addrbook/ItemSales', [
            'addrbook' => $addrbook,
            'sales' => $sales,
            'filters' => [
                'bulan' => $bulan ? (int) $bulan : null,
                'tahun' => $tahun ? (int) $tahun : null,
                'search' => $search,
                'type' => $type ? (int) $type : null,
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
        $addrbook = Addrbook::withTrashed()->findOrFail($id);
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['view']);

        $month = request('month');
        $year = request('year', date('Y'));

        // Fetch all transactions involving this addrbook for the selected period
        $query = \App\Models\Transaction::where(function ($q) use ($addrbook) {
            $q->where('sender_id', $addrbook->id)
                ->orWhere('receiver_id', $addrbook->id);
        })
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $transactions = $query->with(['sender', 'receiver'])->get();

        $categories = ['customer', 'reseller', 'journal', 'bank', 'warehouse', 'other'];
        $metrics = ['cash_in', 'cash_out', 'sell', 'return'];

        $dataStat = [];
        foreach ($metrics as $metric) {
            foreach ($categories as $cat) {
                $dataStat[$metric][$cat] = 0;
            }
            $dataStat[$metric]['total'] = 0;
        }

        foreach ($transactions as $t) {
            // Determine counterpart
            $otherParty = ($t->sender_id == $addrbook->id) ? $t->receiver : $t->sender;
            $type = $otherParty->type ?? null;

            $cat = match ($type) {
                Addrbook::TYPE_CUSTOMER => 'customer',
                Addrbook::TYPE_RESELLER => 'reseller',
                Addrbook::TYPE_ACCOUNT => 'journal',
                Addrbook::TYPE_BANK => 'bank',
                Addrbook::TYPE_WAREHOUSE => 'warehouse',
                default => 'other',
            };

            // Cash In (relative to current addrbook)
            // If we are receiver, cash came in. If we are sender, cash went out.
            // But usually this stat is used for Warehouse/Bank relative to others.
            if ($t->type == \App\Models\Transaction::TYPE_CASH_IN) {
                $amt = (float) $t->grand_total;
                $dataStat['cash_in'][$cat] += $amt;
                $dataStat['cash_in']['total'] += $amt;
            } elseif ($t->type == \App\Models\Transaction::TYPE_CASH_OUT) {
                $amt = (float) $t->grand_total;
                $dataStat['cash_out'][$cat] += $amt;
                $dataStat['cash_out']['total'] += $amt;
            } elseif ($t->type == \App\Models\Transaction::TYPE_SELL) {
                $amt = (float) $t->grand_total;
                $dataStat['sell'][$cat] += $amt;
                $dataStat['sell']['total'] += $amt;
            } elseif ($t->type == \App\Models\Transaction::TYPE_RETURN) {
                $amt = (float) $t->grand_total;
                $dataStat['return'][$cat] += $amt;
                $dataStat['return']['total'] += $amt;
            }
        }

        return Inertia::render('Addrbook/Stats', [
            'addrbook' => $addrbook,
            'dataStat' => $dataStat,
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
            ],
            'years' => range(date('Y'), date('Y') - 5),
            'can' => [
                'bank_hidden_balance' => request()->user()?->can('addrbook-bank-account-hidden-balance') ?? false,
            ],
        ]);
    }

    public function destroy(Addrbook $addrbook)
    {
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['delete']);

        $addrbook->delete();

        return redirect()->route('addrbook.index')->with('success', 'Address Book entry deleted successfully.');
    }
}
