<?php

namespace App\Http\Controllers;

use App\Enums\AddrbookType;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Addrbook;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LocationController extends Controller
{
    public function index()
    {
        Gate::authorize(Location::getPermissions()['view']);

        $query = Location::query()->withCount('customers');

        if ($search = request('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if (request()->wantsJson() || request()->has('json') || request()->ajax()) {
            return $query->limit(20)->get(['id', 'name']);
        }

        return view('locations.index', [
            'locations' => $query->latest()->paginate(50)->withQueryString(),
            'can' => [
                'create_location' => request()->user()?->can(Location::getPermissions()['create']) ?? false,
                'edit_location' => request()->user()?->can(Location::getPermissions()['edit']) ?? false,
                'delete_location' => request()->user()?->can(Location::getPermissions()['delete']) ?? false,
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize(Location::getPermissions()['create']);

        return view('locations.create');
    }

    public function store(StoreLocationRequest $request)
    {
        Gate::authorize(Location::getPermissions()['create']);

        Location::create($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function edit(Location $location)
    {
        Gate::authorize(Location::getPermissions()['edit']);

        return view('locations.edit', [
            'location' => $location,
        ]);
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        Gate::authorize(Location::getPermissions()['edit']);

        $location->update($request->validated());

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        Gate::authorize(Location::getPermissions()['delete']);

        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }

    public function customers(Location $location)
    {
        Gate::authorize(Location::getPermissions()['edit']);

        $search = trim((string) request()->query('q', ''));

        $assigned = $location->customers()
            ->where('type', AddrbookType::Customer)
            ->orderBy('name')
            ->get();

        $candidates = collect();
        if ($search !== '') {
            $candidates = Addrbook::query()
                ->where('type', AddrbookType::Customer)
                ->whereNotIn('id', $assigned->pluck('id'))
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('memberId', 'like', "%{$search}%")
                )
                ->orderBy('name')
                ->limit(20)
                ->get();
        }

        return view('locations.customers', [
            'location' => $location,
            'assigned' => $assigned,
            'candidates' => $candidates,
            'filters' => ['q' => $search],
        ]);
    }

    public function attachAddrbook(Request $request, Location $location)
    {
        Gate::authorize(Location::getPermissions()['edit']);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $addrbook = Addrbook::query()->findOrFail($data['customer_id']);
        if ($addrbook->type !== AddrbookType::Customer) {
            return redirect()
                ->route('locations.customers', $location)
                ->with('error', 'Only customers can be linked to a location.');
        }

        $location->customers()->syncWithoutDetaching([$addrbook->id]);

        return redirect()
            ->route('locations.customers', $location)
            ->with('success', 'Customer linked to location.');
    }

    public function detachAddrbook(Location $location, Addrbook $addrbook)
    {
        Gate::authorize(Location::getPermissions()['edit']);

        $location->customers()->detach($addrbook->id);

        return redirect()
            ->route('locations.customers', $location)
            ->with('success', 'Customer removed from location.');
    }
}
