<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockCellService;
use App\Services\Restock\RestockGridBuilder;
use App\Services\Restock\RestockMoveService;
use App\Services\Restock\RestockReceiveService;
use App\Services\Restock\RestockSettingsService;
use App\Services\Restock\RestockSheetExportService;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockSheetController extends Controller
{
  public function __construct(
    protected RestockSheetService $sheetService,
    protected RestockGridBuilder $gridBuilder,
    protected RestockCellService $cellService,
    protected RestockMoveService $moveService,
    protected RestockReceiveService $receiveService,
    protected RestockSettingsService $settingsService,
    protected RestockSheetExportService $exportService,
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

    $receiveReady = rescue(fn () => $this->settingsService->resolveReceiveParties(), report: false) !== null;

    return view('restock.sheet', [
      'sheet' => $sheet,
      'grid' => $this->gridBuilder->build($sheet),
      'typeTags' => $this->sheetService->typeTags(),
      'canEdit' => request()->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
      'receiveReady' => $receiveReady,
      'stockWarehouseLabel' => $this->settingsService->stockDisplayLabel(),
    ]);
  }

  public function export(RestockSheet $sheet): StreamedResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['view']);

    return $this->exportService->download($sheet);
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
      $moved = $this->moveService->move(
        $sheet,
        $validated['direction'],
        $validated['cells'],
        $request->user(),
      );
    } catch (InvalidArgumentException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
      report($e);

      return response()->json(['message' => $e->getMessage() ?: 'Move failed.'], 500);
    }

    $sheet->refresh();

    return response()->json([
      'message' => $moved > 0 ? "Moved {$moved} unit(s)." : 'Nothing to move.',
      'moved' => $moved,
      'grid' => $this->gridBuilder->build($sheet),
    ]);
  }

  public function receive(Request $request, RestockSheet $sheet): JsonResponse
  {
    Gate::authorize(RestockSheet::getPermissions()['edit']);

    $validated = $request->validate([
      'date' => ['required', 'date'],
      'invoice' => ['nullable', 'string', 'max:255'],
      'cells' => ['required', 'array', 'min:1'],
      'cells.*.id' => ['required', 'integer'],
      'cells.*.qty' => ['nullable', 'integer', 'min:0'],
    ]);

    try {
      $transaction = $this->receiveService->receive(
        $sheet,
        $validated['cells'],
        $request->user(),
        $validated['date'],
        $validated['invoice'] ?? null,
      );
    } catch (ValidationException $e) {
      return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
    } catch (InvalidArgumentException $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    } catch (\Throwable $e) {
      report($e);

      return response()->json(['message' => $e->getMessage() ?: 'Receive failed.'], 500);
    }

    $sheet->refresh();

    return response()->json([
      'message' => 'Received into warehouse.',
      'transaction_id' => $transaction->id,
      'transaction_url' => route('transactions.show', $transaction),
      'grid' => $this->gridBuilder->build($sheet),
    ]);
  }
}
