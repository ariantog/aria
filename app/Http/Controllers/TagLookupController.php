<?php

namespace App\Http\Controllers;

use App\Models\Tag;
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
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
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
