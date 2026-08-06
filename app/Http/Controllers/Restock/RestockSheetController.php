<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class RestockSheetController extends Controller
{
    public function __construct(
        protected RestockSheetService $sheetService,
    ) {}

    public function store(Request $request, Tag $typeTag): RedirectResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['create']);

        abort_unless((int) $typeTag->type === Tag::TYPE_TYPE, 404);

        $validated = $request->validate([
            'pcode' => ['required', 'string', 'max:255'],
        ]);

        try {
            $sheet = $this->sheetService->createSheet(
                $typeTag,
                $validated['pcode'],
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('restock.sheets.show', $sheet)
            ->with('success', "Restock sheet for {$sheet->pcode} created.");
    }

    public function show(RestockSheet $sheet): View
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        $sheet->load([
            'typeTag',
            'representativeGroup',
            'cells.color',
            'cells.size',
            'cells.item.tags',
        ]);

        $cells = $sheet->cells->sortBy([
            fn ($cell) => $cell->color?->name ?? '',
            fn ($cell) => $cell->size?->name ?? '',
        ]);

        return view('restock.sheet', [
            'sheet' => $sheet,
            'cells' => $cells,
            'typeTags' => $this->sheetService->typeTags(),
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
}
