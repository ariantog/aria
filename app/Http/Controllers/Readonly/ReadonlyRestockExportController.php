<?php

namespace App\Http\Controllers\Readonly;

use App\Http\Controllers\Controller;
use App\Models\ReadonlyExport;
use App\Services\Readonly\ReadonlyRestockContextService;
use App\Services\Restock\RestockFlatImportService;
use App\Services\Restock\RestockFlatPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use RuntimeException;

class ReadonlyRestockExportController extends Controller
{
    public function context(Request $request, ReadonlyRestockContextService $contextService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        return response()->json($contextService->build($request, $request->user()));
    }

    public function insightPeriod(ReadonlyRestockContextService $contextService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        return response()->json($contextService->insightPeriod());
    }

    public function previewApply(Request $request, RestockFlatImportService $importService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['restock_apply']);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:'.RestockFlatPipeline::DIRECTION_SET_RESTOCK],
            'rows' => ['required', 'array', 'min:1', 'max:5000'],
            'rows.*.sku' => ['required', 'string', 'max:255'],
            'rows.*.qty' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $parsed = $this->parsedRowsFromPayload($validated['rows']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
            $importService->preview($validated['direction'], $parsed),
        );
    }

    public function apply(Request $request, RestockFlatImportService $importService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['restock_apply']);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:'.RestockFlatPipeline::DIRECTION_SET_RESTOCK],
            'rows' => ['required', 'array', 'min:1', 'max:5000'],
            'rows.*.sku' => ['required', 'string', 'max:255'],
            'rows.*.qty' => ['required', 'integer', 'min:0'],
            'lines' => ['sometimes', 'array'],
            'lines.*' => ['integer', 'min:1'],
        ]);

        try {
            $parsed = $this->parsedRowsFromPayload($validated['rows']);
            $lineNumbers = isset($validated['lines']) ? array_values(array_map('intval', $validated['lines'])) : null;
            $result = $importService->apply(
                $validated['direction'],
                $parsed,
                $request->user(),
                $lineNumbers,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    /**
     * @param  list<array{sku: string, qty: int}>  $rows
     * @return list<array{line: int, sku: string, qty: int}>
     */
    private function parsedRowsFromPayload(array $rows): array
    {
        $parsed = [];
        $line = 1;

        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                throw new RuntimeException("Row {$line}: SKU is empty.");
            }

            $parsed[] = [
                'line' => $line,
                'sku' => $sku,
                'qty' => (int) ($row['qty'] ?? 0),
            ];
            $line++;
        }

        return $parsed;
    }
}