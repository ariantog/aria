<?php

namespace App\Http\Controllers\Readonly;

use App\Http\Controllers\Controller;
use App\Models\ReadonlyExport;
use App\Services\Readonly\ReadonlyStockExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReadonlyStockExportController extends Controller
{
    public function index(Request $request, ReadonlyStockExportService $exportService): JsonResponse
    {
        Gate::authorize(ReadonlyExport::getPermissions()['export']);

        return response()->json([
            'data' => $exportService->rows($request)->values()->all(),
        ]);
    }
}
