<?php

namespace App\Http\Controllers;

use App\Enums\ChecklistFrequency;
use App\Models\ChecklistTemplate;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChecklistTemplateController extends Controller
{
    public function index()
    {
        Gate::authorize(User::getPermissions()['staff-roles-view']);

        return view('staff-checklists.templates.index', [
            'roles' => StaffRole::query()
                ->where('is_active', true)
                ->with(['checklistTemplates' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->get(),
            'can' => $this->abilities(),
        ]);
    }

    public function create()
    {
        Gate::authorize(ChecklistTemplate::getPermissions()['edit']);

        return view('staff-checklists.templates.create', [
            'staffRoles' => $this->staffRoles(),
            'frequencies' => ChecklistFrequency::cases(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize(ChecklistTemplate::getPermissions()['edit']);

        ChecklistTemplate::query()->create($this->validated($request));

        return redirect()->route('staff-checklists.templates.index')->with('success', 'Template checklist dibuat.');
    }

    public function edit(ChecklistTemplate $template)
    {
        Gate::authorize(ChecklistTemplate::getPermissions()['edit']);

        return view('staff-checklists.templates.edit', [
            'template' => $template,
            'staffRoles' => $this->staffRoles(),
            'frequencies' => ChecklistFrequency::cases(),
            'canDelete' => request()->user()?->can(ChecklistTemplate::getPermissions()['delete']) ?? false,
        ]);
    }

    public function update(Request $request, ChecklistTemplate $template)
    {
        Gate::authorize(ChecklistTemplate::getPermissions()['edit']);

        $template->update($this->validated($request));

        return redirect()->route('staff-checklists.templates.index')->with('success', 'Template checklist diperbarui.');
    }

    public function destroy(ChecklistTemplate $template)
    {
        Gate::authorize(ChecklistTemplate::getPermissions()['delete']);

        $template->completions()->delete();
        $template->delete();

        return redirect()->route('staff-checklists.templates.index')->with('success', 'Template checklist dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'staff_role_id' => 'required|integer|exists:staff_roles,id',
            'frequency' => ['required', Rule::enum(ChecklistFrequency::class)],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'route_name' => 'nullable|string|max:128',
            'route_query' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'boolean',
        ]);

        $data['description'] = $data['description'] ?? null;
        $data['route_name'] = $data['route_name'] ?: null;
        $data['route_query'] = $data['route_query'] ?: null;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, StaffRole>
     */
    private function staffRoles()
    {
        return StaffRole::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    /**
     * @return array{edit: bool, delete: bool}
     */
    private function abilities(): array
    {
        $user = request()->user();

        return [
            'edit' => $user?->can(ChecklistTemplate::getPermissions()['edit']) ?? false,
            'delete' => $user?->can(ChecklistTemplate::getPermissions()['delete']) ?? false,
        ];
    }
}
