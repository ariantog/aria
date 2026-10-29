<?php

namespace App\Http\Controllers\Readonly;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ReadonlyExport;
use App\Services\Readonly\ReadonlyItemExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReadonlyItemExportController extends Controller
{
    public function indexJson(Request $request, ReadonlyItemExportService $exportService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        $perPage = min(1000, max(1, (int) $request->query('per_page', 100)));
        $paginator = $exportService->baseQuery($request)->paginate($perPage);

        return response()->json([
            'data' => $exportService->rowsForItems($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function indexCsv(Request $request, ReadonlyItemExportService $exportService): StreamedResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="aria-items-'.now()->format('Y-m-d-His').'.csv"',
        ];

        return response()->stream(function () use ($request, $exportService): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, $this->csvColumns());

            $exportService->baseQuery($request)->chunkById(500, function ($items) use ($exportService, $out): void {
                foreach ($exportService->rowsForItems($items) as $row) {
                    fputcsv($out, $this->csvRow($row));
                }
            }, 'items.id', 'id');
        }, 200, $headers);
    }

    public function costHistory(Item $item): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        return response()->json([
            'data' => [],
            'meta' => [
                'item_id' => $item->id,
                'history_available' => false,
                'note' => 'No item cost history table yet; use base_cost and cost_updated_at from the items export.',
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function csvColumns(): array
    {
        return [
            'id', 'type', 'code', 'barcode', 'group_code', 'name', 'display_name', 'colorway', 'parent_group',
            'price', 'base_cost', 'cost_updated_at', 'cost_updated_at_source', 'cost_source',
            'last_buy_price', 'last_buy_date', 'qty_total', 'jubelio_item_id', 'jubelio_linked',
            'shopee_item_id', 'shopee_model_id', 'is_active', 'cost_flag',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private function csvRow(array $row): array
    {
        return [
            $row['id'],
            $row['type'],
            $row['code'],
            $row['barcode'],
            $row['group_code'],
            $row['name'],
            $row['display_name'],
            $row['colorway'],
            $row['parent_group'],
            $row['price'],
            $row['base_cost'],
            $row['cost_updated_at'],
            $row['cost_updated_at_source'],
            $row['cost_source'],
            $row['last_buy_price'],
            $row['last_buy_date'],
            $row['qty_total'],
            $row['jubelio_item_id'],
            $row['jubelio_linked'] ? '1' : '0',
            $row['shopee_item_id'],
            $row['shopee_model_id'],
            $row['is_active'] ? '1' : '0',
            $row['cost_flag'],
        ];
    }
}
