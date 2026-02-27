<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdditionalFeeRequest;
use App\Http\Requests\UpdateAdditionalFeeRequest;
use App\Models\AdditionalFee;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdditionalFeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize(AdditionalFee::getPermissions()['view']);

        $query = AdditionalFee::query();

        if ($search = request('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('AdditionalFees/Index', [
            'additional_fees' => $query->latest()->paginate(10)->withQueryString(),
            'filters' => request()->all(['search']),
            'can' => [
                'create' => request()->user()?->can(AdditionalFee::getPermissions()['create']) ?? false,
                'edit' => request()->user()?->can(AdditionalFee::getPermissions()['edit']) ?? false,
                'delete' => request()->user()?->can(AdditionalFee::getPermissions()['delete']) ?? false,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize(AdditionalFee::getPermissions()['create']);

        return Inertia::render('AdditionalFees/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdditionalFeeRequest $request)
    {
        Gate::authorize(AdditionalFee::getPermissions()['create']);

        AdditionalFee::create($request->validated());

        return redirect()->route('additional-fees.index')
            ->with('success', 'Additional fee created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AdditionalFee $additionalFee)
    {
        Gate::authorize(AdditionalFee::getPermissions()['edit']);

        return Inertia::render('AdditionalFees/Edit', [
            'additional_fee' => $additionalFee,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdditionalFeeRequest $request, AdditionalFee $additionalFee)
    {
        Gate::authorize(AdditionalFee::getPermissions()['edit']);

        $additionalFee->update($request->validated());

        return redirect()->route('additional-fees.index')
            ->with('success', 'Additional fee updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdditionalFee $additionalFee)
    {
        Gate::authorize(AdditionalFee::getPermissions()['delete']);

        $additionalFee->delete();

        return redirect()->route('additional-fees.index')
            ->with('success', 'Additional fee deleted successfully.');
    }
}
