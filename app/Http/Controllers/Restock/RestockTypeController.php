<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestockTypeController extends Controller
{
  public function __construct(
    protected RestockSheetService $sheetService,
  ) {}

  public function index(): RedirectResponse|View
  {
    Gate::authorize(RestockSheet::getPermissions()['view']);

    $typeTags = $this->sheetService->typeTags();

    if ($typeTags->isEmpty()) {
      return view('restock.type-index', [
        'typeTags' => $typeTags,
        'activeTypeTag' => null,
        'parents' => collect(),
        'sheet' => null,
        'canCreateSheet' => false,
      ]);
    }

    return redirect()->route('restock.type.show', $typeTags->first());
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
      'canCreateSheet' => $this->sheetService->canCreateSheetForType($typeTag)
        && (request()->user()?->can(RestockSheet::getPermissions()['create']) ?? false),
    ]);
  }
}
