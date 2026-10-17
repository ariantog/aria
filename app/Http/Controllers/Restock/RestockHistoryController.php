<?php

namespace App\Http\Controllers\Restock;

use App\Http\Controllers\Controller;
use App\Models\RestockSheet;
use App\Support\RestockCellHistoryLabels;
use App\Services\Restock\RestockCellHistoryQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RestockHistoryController extends Controller
{
    public function __construct(
        protected RestockCellHistoryQuery $historyQuery,
    ) {}

    public function index(Request $request, ?RestockSheet $sheet = null): View
    {
        Gate::authorize(RestockSheet::getPermissions()['history']);

        $sheet?->loadMissing('typeTag');

        $filters = [
            'sheet_id' => $sheet?->id ?? $request->query('sheet_id'),
            'item_id' => $request->query('item_id'),
            'actor_type' => $request->query('actor_type'),
            'source' => $request->query('source'),
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        return view('restock.history', [
            'sheet' => $sheet,
            'filters' => $filters,
            'entries' => $this->historyQuery->paginate($filters),
            'sheetOptions' => $this->historyQuery->sheetOptions(),
            'actionLabels' => RestockCellHistoryLabels::actionLabels(),
            'sourceLabels' => RestockCellHistoryLabels::sourceLabels(),
            'actorLabels' => RestockCellHistoryLabels::actorLabels(),
        ]);
    }
}
