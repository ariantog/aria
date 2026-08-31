<?php

namespace App\Http\Controllers;

use App\Models\ChecklistTemplate;
use App\Services\StaffChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistCompletionController extends Controller
{
    public function __invoke(Request $request, ChecklistTemplate $checklist, StaffChecklistService $service): JsonResponse
    {
        $completed = $service->toggle($request->user(), $checklist);

        return response()->json([
            'completed' => $completed,
            'template_id' => $checklist->id,
            'period_key' => $service->periodKeyFor($checklist->frequency),
        ]);
    }
}
