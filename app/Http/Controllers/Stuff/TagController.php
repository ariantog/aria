<?php

namespace App\Http\Controllers\Stuff;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize(Tag::getPermissions()['view']);

        $search = $request->query('search', '');
        $typeFilter = $request->query('type', '');

        $query = Tag::query()->withCount('items');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($typeFilter !== '' && $typeFilter !== null) {
            $query->where('type', (int) $typeFilter);
        }

        $sort = $request->query('sort', 'name');
        $direction = strtolower($request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortable = ['name', 'code', 'type', 'item_type', 'items_count'];

        if (! in_array($sort, $sortable, true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction);

        if ($sort !== 'name') {
            $query->orderBy('name');
        }

        $query->orderBy('id');

        return view('stuff.tags.index', [
            'tags' => $query->paginate(50)->withQueryString(),
            'search' => $search,
            'typeFilter' => $typeFilter,
            'sort' => $sort,
            'direction' => $direction,
            'types' => Tag::$types,
            'itemTypes' => [
                'All' => 0,
                ...array_combine(
                    array_map(fn ($t) => $t->label(), ItemType::cases()),
                    array_map(fn ($t) => $t->value, ItemType::cases())
                ),
            ],
            'can' => [
                'create' => request()->user()?->can(Tag::getPermissions()['create']) ?? false,
                'edit' => request()->user()?->can(Tag::getPermissions()['edit']) ?? false,
                'delete' => request()->user()?->can(Tag::getPermissions()['delete']) ?? false,
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
        $validated = Tag::normalizeWarnaAttributes($validated);

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
        $validated = Tag::normalizeWarnaAttributes($validated);

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
