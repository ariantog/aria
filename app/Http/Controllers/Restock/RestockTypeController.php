<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockMissingService;
use App\Services\Restock\RestockSheetExportService;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockTypeController extends Controller
{
  public function __construct(
    protected RestockSheetService $sheetService,
    protected RestockMissingService $missingService,
    protected RestockSheetExportService $exportService,
  ) {}

  public function index(): View
  {
    Gate::authorize(RestockSheet::getPermissions()['view']);

    return view('restock.index', [
      'typeTags' => $this->sheetService->typeTags(),
      'sheets' => $this->sheetService->sheetSummaries(),
      'missingCount' => $this->missingService->missingCountForType(),
      'canExport' => request()->user()?->can(RestockSheet::getPermissions()['export']) ?? false,
      'exportStages' => RestockSheetExportService::ALL_STAGES,
    ]);
  }

  public function exportBulk(Request $request): StreamedResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['export']);

    $validated = $request->validate([
      'sheet_ids' => ['required', 'array', 'min:1'],
      'sheet_ids.*' => ['integer', 'exists:restock_sheets,id'],
      'stages' => ['required', 'array', 'min:1'],
      'stages.*' => ['string', 'in:restock,production,shipped,stock'],
    ]);

    $sheets = RestockSheet::query()
      ->whereIn('id', $validated['sheet_ids'])
      ->orderBy('name')
      ->get();

    return $this->exportService->downloadMany($sheets, $validated['stages']);
  }

  public function show(Tag $typeTag): View
  {
    Gate::authorize(RestockSheet::getPermissions()['view']);

    abort_unless((int) $typeTag->type === Tag::TYPE_TYPE, 404);
    abort_unless(RestockSheetService::isAssetLancarTypeTag($typeTag), 404);

    return view('restock.type-index', [
      'typeTags' => $this->sheetService->typeTags(),
      'activeTypeTag' => $typeTag,
      'parents' => $this->sheetService->parentsForType($typeTag),
      'sheet' => $this->sheetService->sheetForType($typeTag),
      'missingCount' => $this->missingService->missingCountForType($typeTag),
      'canCreateSheet' => $this->sheetService->canCreateSheetForType($typeTag)
        && (request()->user()?->can(RestockSheet::getPermissions()['create']) ?? false),
      'canEdit' => request()->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
    ]);
  }
}
