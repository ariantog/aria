<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAddrbookRequest;
use App\Http\Requests\UpdateAddrbookRequest;
use App\Models\Addrbook;
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
        if (request()->wantsJson() || request()->has('json')) {
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

    public function destroy(Addrbook $addrbook)
    {
        Gate::authorize(Addrbook::getPermissions($addrbook->type_slug)['delete']);

        $addrbook->delete();

        return redirect()->route('addrbook.index')->with('success', 'Address Book entry deleted successfully.');
    }
}
