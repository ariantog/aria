<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockCellService;
use App\Services\Restock\RestockGridBuilder;
use App\Services\Restock\RestockMoveService;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class RestockSheetController extends Controller
{
  public function __construct(
    protected RestockSheetService $sheetService,
    protected RestockGridBuilder $gridBuilder,
    protected RestockCellService $cellService,
    protected RestockMoveService $moveService,
  ) {}

  public function store(Request $request, Tag $typeTag): RedirectResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['create']);

    abort_unless(RestockSheetService::isAssetLancarTypeTag($typeTag), 404);

    try {
      $sheet = $this->sheetService->createSheet($typeTag, $request->user());
    } catch (InvalidArgumentException $e) {
      return back()->with('error', $e->getMessage());
    }

    return redirect()
      ->route('restock.sheets.show', $sheet)
      ->with('success', "Restock sheet for {$sheet->name} created.");
  }

  public function show(RestockSheet $sheet): View
  {
    Gate::authorize(RestockSheet::getPermissions()['view']);

    $sheet->load(['typeTag', 'representativeGroup']);

    return view('restock.sheet', [
      'sheet' => $sheet,
      'grid' => $this->gridBuilder->build($sheet),
      'typeTags' => $this->sheetService->typeTags(),
      'canEdit' => request()->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
    ]);
  }

  public function update(Request $request, RestockSheet $sheet): JsonResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['edit']);

    $validated = $request->validate([
      'cells' => ['required', 'array'],
      'cells.*.id' => ['required', 'integer'],
      'cells.*.qty_restock' => ['nullable', 'integer', 'min:0'],
      'cells.*.qty_production' => ['nullable', 'integer', 'min:0'],
      'cells.*.qty_shipped' => ['nullable', 'integer', 'min:0'],
    ]);

    try {
      $changes = $this->cellService->saveQuantities($sheet, $validated['cells'], $request->user());
    } catch (InvalidArgumentException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }

    return response()->json([
      'message' => $changes > 0 ? "Saved {$changes} change(s)." : 'No changes to save.',
      'changes' => $changes,
    ]);
  }

  public function sync(Request $request, RestockSheet $sheet): RedirectResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['edit']);

    $added = $this->sheetService->syncSkus($sheet);

    return back()->with(
      'success',
      $added > 0
        ? "Added {$added} new SKU cell(s) from the item catalog."
        : 'Sheet is already up to date with the item catalog.',
    );
  }

  public function move(Request $request, RestockSheet $sheet): JsonResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['edit']);

    $validated = $request->validate([
      'direction' => ['required', 'string', 'in:to_production,to_shipped'],
      'cells' => ['required', 'array', 'min:1'],
      'cells.*.id' => ['required', 'integer'],
      'cells.*.qty' => ['nullable', 'integer', 'min:1'],
    ]);

    try {
      $moved = $this->moveService->move($sheet, $validated['direction'], $validated['cells'], $request->user());
    } catch (InvalidArgumentException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }

    return response()->json([
      'message' => $moved > 0 ? "Moved {$moved} unit(s)." : 'Nothing to move.',
      'moved' => $moved,
      'grid' => $this->gridBuilder->build($sheet->fresh()),
    ]);
  }
}
