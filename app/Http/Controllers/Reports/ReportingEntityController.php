<?php

namespace App\Http\Controllers\Reports;

use App\Enums\ReportingLedgerRole as ReportingLedgerRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole;
use App\Models\ReportingWarehouseFulfillment;
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
            'activeEntities' => $entities->where('is_active', true)->values(),
            'retiredEntities' => $entities->where('is_active', false)->values(),
            'unassignedBanks' => $this->unassignedOperatingBanks(),
            'ledgerRoles' => ReportingLedgerRole::query()
                ->with(['customer' => fn ($query) => $query->withTrashed()->select('id', 'name')])
                ->orderBy('role')
                ->get(),
            'ledgerRoleOptions' => ReportingLedgerRoleEnum::cases(),
            'accounts' => Addrbook::query()
                ->where('type', Addrbook::TYPE_ACCOUNT)
                ->orderBy('name')
                ->get(['id', 'name']),
            'fulfillments' => ReportingWarehouseFulfillment::query()
                ->with([
                    'warehouse' => fn ($query) => $query->withTrashed()->select('id', 'name'),
                    'customer' => fn ($query) => $query->withTrashed()->select('id', 'name'),
                ])
                ->orderBy('id')
                ->get(),
            'warehouses' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE])
                ->orderBy('name')
                ->get(['id', 'name']),
            'fulfillmentCustomers' => Addrbook::query()
                ->whereIn('type', [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER])
                ->orderBy('name')
                ->get(['id', 'name']),
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

    public function storeLedgerRole(Request $request)
    {
        $this->authorizeSuperadmin();

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'role' => ['required', Rule::enum(ReportingLedgerRoleEnum::class)],
        ]);

        $account = Addrbook::query()->findOrFail((int) $data['customer_id']);
        abort_unless((int) $account->type === Addrbook::TYPE_ACCOUNT, 422, 'Ledger role can only be assigned to an account.');

        ReportingLedgerRole::updateOrCreate(
            ['customer_id' => (int) $data['customer_id']],
            ['role' => $data['role']],
        );

        return redirect()->route('reports.entities.index')->with('success', 'Ledger role saved.');
    }

    public function destroyLedgerRole(ReportingLedgerRole $role)
    {
        $this->authorizeSuperadmin();

        $role->delete();

        return redirect()->route('reports.entities.index')->with('success', 'Ledger role removed.');
    }

    public function storeFulfillment(Request $request)
    {
        $this->authorizeSuperadmin();

        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $warehouse = Addrbook::query()->findOrFail((int) $data['warehouse_id']);
        $customer = Addrbook::query()->findOrFail((int) $data['customer_id']);

        abort_unless(
            in_array((int) $warehouse->type, [Addrbook::TYPE_WAREHOUSE, Addrbook::TYPE_V_WAREHOUSE], true),
            422,
            'Fulfillment warehouse must be a warehouse.',
        );
        abort_unless(
            in_array((int) $customer->type, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER], true),
            422,
            'Fulfillment channel must be a customer or reseller.',
        );

        ReportingWarehouseFulfillment::updateOrCreate(
            [
                'warehouse_id' => (int) $data['warehouse_id'],
                'customer_id' => (int) $data['customer_id'],
            ],
            ['notes' => $data['notes'] ?? null],
        );

        return redirect()->route('reports.entities.index')->with('success', 'Warehouse fulfillment saved.');
    }

    public function destroyFulfillment(ReportingWarehouseFulfillment $fulfillment)
    {
        $this->authorizeSuperadmin();

        $fulfillment->delete();

        return redirect()->route('reports.entities.index')->with('success', 'Warehouse fulfillment removed.');
    }

    private function authorizeSuperadmin(): void
    {
        abort_unless(request()->user()?->is_superadmin, 403);
    }

    /**
     * Active operating banks that are not on any active reporting_entity_banks row.
     *
     * @return \Illuminate\Support\Collection<int, Addrbook>
     */
    private function unassignedOperatingBanks()
    {
        $assignedBankIds = DB::table('reporting_entity_banks')
            ->where('is_active', true)
            ->pluck('bank_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Addrbook::query()
            ->where('type', Addrbook::TYPE_BANK)
            ->where('is_active_in_reports', true)
            ->when($assignedBankIds !== [], fn ($query) => $query->whereNotIn('id', $assignedBankIds))
            ->orderBy('name')
            ->get(['id', 'name']);
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
