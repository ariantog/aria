<?php

namespace App\Http\Controllers;

use App\Services\StaffChecklistService;
use Illuminate\Http\Request;

class MyChecklistController extends Controller
{
    public function index(Request $request, StaffChecklistService $checklistService)
    {
        $checklist = $checklistService->forUser($request->user());

        if (! $checklist['has_checklists']) {
            return redirect()->route('dashboard');
        }

        return view('my-checklist.index', [
            'checklist' => $checklist,
        ]);
    }
}
