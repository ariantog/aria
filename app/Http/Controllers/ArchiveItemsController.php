<?php

namespace App\Http\Controllers;

use App\Models\Archive\ArchiveItem;
use App\Models\DataRetentionRun;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ArchiveItemsController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize(DataRetentionRun::getPermissions()['archive-view']);

        $search = trim((string) $request->query('search', ''));

        $query = ArchiveItem::query()
            ->with('group')
            ->orderByDesc('id');

        if ($search !== '') {
            $contains = LikeSearch::contains($search);
            $prefix = LikeSearch::prefix($search);

            $query->where(function ($q) use ($search, $contains, $prefix) {
                $q->where('name', 'like', $contains)
                    ->orWhere('code', 'like', $prefix)
                    ->orWhere('legacy_code', 'like', $prefix)
                    ->orWhere('pcode', 'like', $prefix);

                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $items = $query->paginate(25)->withQueryString();

        return view('archive.items.index', [
            'items' => $items,
            'search' => $search,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function show(int $id)
    {
        Gate::authorize(DataRetentionRun::getPermissions()['archive-view']);

        $item = ArchiveItem::with('group')->findOrFail($id);

        return view('archive.items.show', [
            'item' => $item,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }
}
