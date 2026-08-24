<?php

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Report;
use App\Services\ExportSellExportService;
use App\Services\ExportSellQueryService;
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

        $partyLookups = self::exportSellPartyLookups($queryService);

        return view('transactions.export-sell', [
            'rows' => $rows,
            'filters' => $filters,
            'perPage' => $perPage,
            'typeOptions' => $queryService->typeOptions(),
            'senderLookupUrl' => $partyLookups['sender_route'],
            'receiverLookupUrl' => $partyLookups['receiver_route'],
            'senderLabel' => $partyLookups['sender_label'],
            'receiverLabel' => $partyLookups['receiver_label'],
            'selectedSender' => $this->resolveSelectedParty($filters['sender'] ?? null),
            'selectedReceiver' => $this->resolveSelectedParty($filters['receiver'] ?? null),
            'itemLookupUrl' => route('items.index'),
            'selectedItem' => $this->resolveSelectedItem($filters['item_id'] ?? null),
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

    /**
     * @return array{sender_route: string, receiver_route: string, sender_label: string, receiver_label: string}
     */
    public static function exportSellPartyLookups(?ExportSellQueryService $queryService = null): array
    {
        $queryService ??= app(ExportSellQueryService::class);
        $partyTypeIds = $queryService->partyTypeIds();

        $lookupParams = [
            'type' => 'sell',
            'addrbook_type' => $partyTypeIds,
        ];

        return [
            'sender_route' => route('transactions.lookup', [...$lookupParams, 'role' => 'sender']),
            'receiver_route' => route('transactions.lookup', [...$lookupParams, 'role' => 'receiver']),
            'sender_label' => 'Sender',
            'receiver_label' => 'Receiver',
        ];
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

    public static function itemShowUrl(?ItemType $type, int $itemId): string
    {
        if ($type === ItemType::ASSET_LANCAR) {
            return route('assetlancar.show', $itemId);
        }

        return route('items.show', $itemId);
    }

    public static function addrbookShowUrl(?Addrbook $addrbook): ?string
    {
        if (! $addrbook) {
            return null;
        }

        return route('addrbook.type.show', [$addrbook->type_slug, $addrbook->id]);
    }
}
