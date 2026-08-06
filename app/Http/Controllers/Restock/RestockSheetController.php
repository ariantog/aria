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
            'parentGroups' => $this->sheetService->cellsGroupedByParent($sheet),
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
