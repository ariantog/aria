<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ExportSellController;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Services\ExportSellQueryService;
use App\Services\InventoryHealth\InventoryHealthClassifier;
use App\Services\InventoryHealth\InventoryHealthQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InventoryHealthController extends Controller
{
    public function index(Request $request, InventoryHealthQueryService $queryService): View
    {
        Gate::authorize(Report::getPermissions()['view-inventory-health']);

        $filters = $queryService->filtersFromRequest($request);
        $windows = $queryService->resolveWindows($request);
        $sort = $queryService->resolveSort($request);
        $rows = $queryService->paginate($request, Auth::user());
        $meta = $queryService->pageMeta($request);
        $partyLookups = self::partyLookups();

        return view('reports.inventory-health', [
            'rows' => $rows,
            'filters' => $filters,
            'windows' => $windows,
            'sort' => $sort['column'],
            'direction' => $sort['direction'],
            'source' => $meta['source'],
            'syncedAt' => $meta['synced_at'],
            'stale' => $meta['stale'],
            'hasSnapshots' => $meta['has_snapshots'],
            'perPage' => $queryService->resolvePerPage($request),
            'typeOptions' => $queryService->typeOptions(),
            'statusOptions' => InventoryHealthClassifier::statusOptions(),
            'senderLookupUrl' => $partyLookups['sender_route'],
            'receiverLookupUrl' => $partyLookups['receiver_route'],
            'senderLabel' => 'Sender',
            'receiverLabel' => 'Receiver',
            'selectedSender' => $this->resolveSelectedParty($filters['sender'] ?? null),
            'selectedReceiver' => $this->resolveSelectedParty($filters['receiver'] ?? null),
            'itemLookupUrl' => route('items.index'),
            'selectedItem' => $this->resolveSelectedItem($filters['item_id'] ?? null),
        ]);
    }

    /**
     * @return array{sender_route: string, receiver_route: string}
     */
    public static function partyLookups(): array
    {
        $queryService = app(ExportSellQueryService::class);
        $partyTypeIds = $queryService->partyTypeIds();

        $lookupParams = [
            'type' => 'sell',
            'addrbook_type' => $partyTypeIds,
        ];

        return [
            'sender_route' => route('transactions.lookup', [...$lookupParams, 'role' => 'sender']),
            'receiver_route' => route('transactions.lookup', [...$lookupParams, 'role' => 'receiver']),
        ];
    }

    public static function itemShowUrl(?ItemType $type, int $itemId): string
    {
        return ExportSellController::itemShowUrl($type, $itemId);
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

    /**
     * @return array{id: int, name: string, code: string|null}|null
     */
    private function resolveSelectedItem(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $term = trim((string) $value);
        if ($term === '' || ! ctype_digit($term)) {
            return null;
        }

        $item = Item::query()->find((int) $term);
        if (! $item) {
            return null;
        }

        $code = $item->code ?: (string) $item->id;
        $name = trim($code.' — '.$item->name, ' —');

        return [
            'id' => $item->id,
            'name' => $name,
            'code' => $item->code,
        ];
    }
}
