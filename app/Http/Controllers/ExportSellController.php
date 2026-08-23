<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Report;
use App\Services\ExportSellExportService;
use App\Services\ExportSellQueryService;
use App\Support\LikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ExportSellController extends Controller
{
    public function index(Request $request, ExportSellQueryService $queryService)
    {
        Gate::authorize(Report::getPermissions()['view-export-sell']);

        $perPage = $queryService->resolvePerPage($request);
        $filters = $queryService->filtersFromRequest($request);
        $rows = $queryService
            ->buildQuery($request, Auth::user())
            ->paginate($perPage)
            ->withQueryString();

        return view('transactions.export-sell', [
            'rows' => $rows,
            'filters' => $filters,
            'perPage' => $perPage,
            'typeOptions' => $queryService->typeOptions(),
            'partyLookupUrl' => route('transactions.export-sell.lookup'),
            'selectedSender' => $this->resolveSelectedParty($filters['sender'] ?? null),
            'selectedReceiver' => $this->resolveSelectedParty($filters['receiver'] ?? null),
        ]);
    }

    public function export(Request $request, ExportSellQueryService $queryService, ExportSellExportService $exportService)
    {
        Gate::authorize(Report::getPermissions()['view-export-sell']);

        $rows = $queryService
            ->buildQuery($request, Auth::user())
            ->get();

        return $exportService->download($rows);
    }

    public function lookup(Request $request)
    {
        Gate::authorize(Report::getPermissions()['view-export-sell']);

        $search = trim((string) $request->input('search', ''));
        if (strlen($search) <= 2) {
            return response()->json([]);
        }

        $pattern = LikeSearch::contains($search);

        $results = Addrbook::query()
            ->visibleToUser($request->user())
            ->where(function ($query) use ($pattern) {
                $query->where('name', 'like', $pattern)
                    ->orWhere('id', 'like', $pattern);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json($results);
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function resolveSelectedParty(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $term = trim((string) $value);
        if ($term === '' || ! ctype_digit($term)) {
            return null;
        }

        $addrbook = Addrbook::query()
            ->visibleToUser(Auth::user())
            ->find((int) $term);

        if (! $addrbook) {
            return null;
        }

        return [
            'id' => $addrbook->id,
            'name' => $addrbook->name,
        ];
    }

    public static function itemShowUrl(?ItemType $type, int $itemId): string
    {
        if ($type === ItemType::ASSET_LANCAR) {
            return route('assetlancar.show', $itemId);
        }

        return route('items.show', $itemId);
    }

    public static function addrbookShowUrl(?\App\Models\Addrbook $addrbook): ?string
    {
        if (! $addrbook) {
            return null;
        }

        return route('addrbook.type.show', [$addrbook->type_slug, $addrbook->id]);
    }
}
