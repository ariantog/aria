<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Support\LikeSearch;
use Illuminate\Http\Request;

class TagLookupController extends Controller
{
    /**
     * Search for tags by type and name.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $query = Tag::query();

        // Filter by Tag Type (Jahit, Size, Warna, etc.)
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Apply search term
        if ($search = $request->input('search')) {
            $pattern = LikeSearch::contains($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'like', $pattern)
                    ->orWhere('code', 'like', $pattern)
                    ->orWhere('id', 'like', $pattern);
            });
        }

        $results = $query
            ->select('id', 'name', 'code', 'type')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($results);
    }
}
