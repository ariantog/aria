<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportingEntityController extends Controller
{
    public function index()
    {
        $this->authorizeSuperadmin();

        $entities = ReportingEntity::withCount('banks')->orderBy('name')->get();

        return view('reports.entities.index', [
            'entities' => $entities,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function edit(ReportingEntity $entity)
    {
        $this->authorizeSuperadmin();

        $entity->load('banks');
        $banks = Addrbook::query()
            ->where('type', Addrbook::TYPE_BANK)
            ->orderBy('name')
            ->get(['id', 'name']);
        $assignedBankIds = $entity->banks->pluck('id')->all();

        return view('reports.entities.edit', [
            'entity' => $entity,
            'banks' => $banks,
            'assignedBankIds' => old('bank_ids', $assignedBankIds),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function update(Request $request, ReportingEntity $entity)
    {
        $this->authorizeSuperadmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:80', Rule::unique('reporting_entities', 'slug')->ignore($entity->id)],
            'is_pkp' => ['boolean'],
            'npwp' => ['nullable', 'string', 'max:20'],
            'modal' => ['nullable', 'numeric'],
            'laba_ditahan_awal' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'bank_ids' => ['nullable', 'array'],
            'bank_ids.*' => ['integer', 'exists:customers,id'],
        ]);

        $entity->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_pkp' => $request->boolean('is_pkp'),
            'npwp' => $data['npwp'] ?? null,
            'modal' => $data['modal'] ?? null,
            'laba_ditahan_awal' => $data['laba_ditahan_awal'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'notes' => $data['notes'] ?? null,
        ]);

        $bankIds = collect($data['bank_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $sync = [];
        foreach ($bankIds as $bankId) {
            $sync[$bankId] = ['is_active' => true];
        }
        $entity->banks()->sync($sync);

        return redirect()->route('reports.entities.index')->with('success', 'Entity updated.');
    }

    public function store(Request $request)
    {
        $this->authorizeSuperadmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'unique:reporting_entities,slug'],
            'is_pkp' => ['boolean'],
        ]);

        ReportingEntity::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'is_pkp' => $request->boolean('is_pkp'),
            'is_active' => true,
        ]);

        return redirect()->route('reports.entities.index')->with('success', 'Entity created.');
    }

    private function authorizeSuperadmin(): void
    {
        abort_unless(request()->user()?->is_superadmin, 403);
    }
}
