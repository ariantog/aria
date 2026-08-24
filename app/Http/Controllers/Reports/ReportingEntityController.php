<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportingEntityController extends Controller
{
    public function index()
    {
        $this->authorizeSuperadmin();

        $entities = ReportingEntity::query()
            ->with(['banks' => fn ($query) => $query->orderBy('name')->select('customers.id', 'customers.name')])
            ->orderBy('name')
            ->get();

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
        $this->assertBanksAvailableForEntity($entity, $bankIds);

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

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $bankIds
     */
    private function assertBanksAvailableForEntity(ReportingEntity $entity, $bankIds): void
    {
        if ($bankIds->isEmpty()) {
            return;
        }

        $conflicts = DB::table('reporting_entity_banks as reb')
            ->join('customers as bank', 'bank.id', '=', 'reb.bank_id')
            ->join('reporting_entities as other_entity', 'other_entity.id', '=', 'reb.reporting_entity_id')
            ->whereIn('reb.bank_id', $bankIds->all())
            ->where('reb.reporting_entity_id', '!=', $entity->id)
            ->orderBy('bank.name')
            ->get([
                'reb.bank_id',
                'bank.name as bank_name',
                'other_entity.name as entity_name',
            ]);

        if ($conflicts->isEmpty()) {
            return;
        }

        $messages = $conflicts->map(
            fn ($row) => sprintf(
                '%s is already assigned to %s. Remove it from that entity first.',
                $row->bank_name,
                $row->entity_name,
            )
        )->values()->all();

        throw ValidationException::withMessages([
            'bank_ids' => $messages,
        ]);
    }
}
