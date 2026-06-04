<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LocationController extends Controller
{
    public function index()
    {
        Gate::authorize(Location::getPermissions()['view']);

        $query = Location::query();

        if ($search = request('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ((request()->wantsJson() || request()->has('json')) && ! request()->header('X-Inertia')) {
            return $query->limit(20)->get(['id', 'name']);
        }

        return Inertia::render('Locations/Index', [
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

        return Inertia::render('Locations/Create');
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

        return Inertia::render('Locations/Edit', [
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
}
