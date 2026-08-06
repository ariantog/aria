<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockMissingService;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class RestockMissingController extends Controller
{
    public function __construct(
        protected RestockMissingService $missingService,
        protected RestockSheetService $sheetService,
    ) {}

    public function index(): View
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        return $this->missingView(null);
    }

    public function forType(Tag $typeTag): View
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        abort_unless((int) $typeTag->type === Tag::TYPE_TYPE, 404);
        abort_unless(RestockSheetService::isAssetLancarTypeTag($typeTag), 404);

        return $this->missingView($typeTag);
    }

    protected function missingView(?Tag $typeTag): View
    {
        return view('restock.missing', [
            'typeTags' => $this->sheetService->typeTags(),
            'activeTypeTag' => $typeTag,
            'rows' => $this->missingService->listForType($typeTag),
            'canEdit' => request()->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
        ]);
    }

    public function markFound(RestockCell $cell): RedirectResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['edit']);

        try {
            $this->missingService->markFound($cell, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked as found.');
    }
}
