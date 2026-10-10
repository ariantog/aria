<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Services\Restock\RestockFlatExportService;
use App\Services\Restock\RestockFlatImportParser;
use App\Services\Restock\RestockFlatImportService;
use App\Services\Restock\RestockFlatPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockFlatController extends Controller
{
    public function __construct(
        protected RestockFlatExportService $exportService,
        protected RestockFlatImportParser $importParser,
        protected RestockFlatImportService $importService,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['export']);

        $validated = $request->validate([
            'source' => ['required', 'string', 'in:'.RestockFlatPipeline::SOURCE_RESTOCK.','.RestockFlatPipeline::SOURCE_PRODUCTION],
        ]);

        return $this->exportService->downloadAll($validated['source']);
    }

    public function preview(Request $request): JsonResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['edit']);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:'.RestockFlatPipeline::DIRECTION_TO_PRODUCTION.','.RestockFlatPipeline::DIRECTION_TO_SHIPPED],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
        ]);

        try {
            $parsed = $this->importParser->parse($validated['file']->getRealPath());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(
            $this->importService->preview($validated['direction'], $parsed),
        );
    }

    public function apply(Request $request): JsonResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['edit']);

        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:'.RestockFlatPipeline::DIRECTION_TO_PRODUCTION.','.RestockFlatPipeline::DIRECTION_TO_SHIPPED],
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
        ]);

        try {
            $parsed = $this->importParser->parse($validated['file']->getRealPath());
            $result = $this->importService->apply($validated['direction'], $parsed, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
