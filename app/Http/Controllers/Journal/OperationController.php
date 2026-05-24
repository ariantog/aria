<?php

namespace App\Http\Controllers\Journal;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class OperationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(Operation::getPermissions()['operation-list']);

        $query = Operation::query();

        if ($search = $request->search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('Journals/Operations/Index', [
            'operations' => $query->latest()->paginate(50)->withQueryString(),
        ]);
    }

    public function show(Request $request, Operation $operation)
    {
        Gate::authorize(Operation::getPermissions()['operation-list']);

        $query = $operation->accounts()->where('type', \App\Models\Addrbook::TYPE_ACCOUNT);

        if ($search = $request->search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('Journals/Operations/Show', [
            'operation' => $operation,
            'accounts' => $query->latest()->paginate(50)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(Operation::getPermissions()['operation-create']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Operation::create($validated);

        return redirect()->back()->with('success', 'Operation created successfully.');
    }

    public function update(Request $request, Operation $operation)
    {
        Gate::authorize(Operation::getPermissions()['operation-edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $operation->update($validated);

        return redirect()->back()->with('success', 'Operation updated successfully.');
    }

    public function destroy(Operation $operation)
    {
        Gate::authorize(Operation::getPermissions()['operation-delete']);

        $operation->delete();

        return redirect()->back()->with('success', 'Operation deleted successfully.');
    }
}
