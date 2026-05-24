<?php

namespace App\Http\Controllers\Stuff;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize(Tag::getPermissions()['view']);

        $query = Tag::query();

        if ($search = $request->search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        return Inertia::render('Stuff/Tags/Index', [
            'tags' => $query->latest()->paginate(50)->withQueryString(),
            'types' => Tag::$types,
            'itemTypes' => [
                'All' => 0,
                ...array_combine(
                    array_map(fn ($t) => $t->label(), ItemType::cases()),
                    array_map(fn ($t) => $t->value, ItemType::cases())
                ),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize(Tag::getPermissions()['create']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'type' => 'required|integer',
            'item_type' => 'nullable|integer',
        ]);

        $validated['item_type'] = $validated['item_type'] ?? 0;

        Tag::create($validated);

        return redirect()->back()->with('success', 'Tag created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        Gate::authorize(Tag::getPermissions()['edit']);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'type' => 'required|integer',
            'item_type' => 'nullable|integer',
        ]);

        $validated['item_type'] = $validated['item_type'] ?? 0;

        $tag->update($validated);

        return redirect()->back()->with('success', 'Tag updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag)
    {
        Gate::authorize(Tag::getPermissions()['delete']);

        $tag->delete();

        return redirect()->back()->with('success', 'Tag deleted successfully.');
    }
}
