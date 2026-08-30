<?php

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingWarehouseFulfillment;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $permissions = Addrbook::getPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
    $this->user->givePermissionTo(array_values($permissions));
});

it('lists unassigned operating banks and hides assigned or inactive banks', function () {
    $assigned = Addrbook::create(['name' => 'BCA Assigned', 'type' => Addrbook::TYPE_BANK, 'is_active_in_reports' => true]);
    $unassigned = Addrbook::create(['name' => 'BCA Unassigned', 'type' => Addrbook::TYPE_BANK, 'is_active_in_reports' => true]);
    Addrbook::create(['name' => 'Transfer Pending', 'type' => Addrbook::TYPE_BANK, 'is_active_in_reports' => false]);

    $entity = ReportingEntity::create(['name' => 'CV Mapped', 'slug' => 'cv-mapped', 'is_pkp' => true]);
    $entity->banks()->attach($assigned->id, ['is_active' => true]);

    $this->actingAs($this->user)
        ->get(route('reports.entities.index'))
        ->assertOk()
        ->assertSee('data-testid="entities-unassigned-banks"', false)
        ->assertSee('BCA Unassigned', false)
        ->assertDontSee('Transfer Pending', false);

    $html = $this->actingAs($this->user)->get(route('reports.entities.index'))->getContent();
    $banner = Str::between($html, 'data-testid="entities-unassigned-banks"', '</div>');

    expect($banner)->toContain('BCA Unassigned')
        ->and($banner)->not->toContain('BCA Assigned');
});

it('saves a ledger role from the entities mapping ui', function () {
    $account = Addrbook::create(['name' => 'Material Produksi', 'type' => Addrbook::TYPE_ACCOUNT]);

    $this->actingAs($this->user)
        ->post(route('reports.entities.ledger-roles.store'), [
            'customer_id' => $account->id,
            'role' => ReportingLedgerRole::Material->value,
        ])
        ->assertRedirect(route('reports.entities.index'));

    $row = ReportingLedgerRoleModel::query()->where('customer_id', $account->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->role)->toBe(ReportingLedgerRole::Material);
});

it('saves a ledger role from the addrbook reporting field', function () {
    $this->actingAs($this->user)
        ->post(route('addrbook.store'), [
            'name' => 'Gaji Mingguan Test',
            'type' => Addrbook::TYPE_ACCOUNT,
            'ledger_role' => ReportingLedgerRole::ProductionCost->value,
            'is_online' => false,
            'ppn' => false,
            'initial_balance' => 0,
        ])
        ->assertRedirect();

    $account = Addrbook::where('name', 'Gaji Mingguan Test')->first();
    expect($account)->not->toBeNull();

    $row = ReportingLedgerRoleModel::query()->where('customer_id', $account->id)->first();
    expect($row?->role)->toBe(ReportingLedgerRole::ProductionCost);
});

it('attaches warehouse fulfillment mappings', function () {
    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang WTC']);
    $customer = Addrbook::factory()->customer()->create(['name' => 'Shopee Channel']);

    $this->actingAs($this->user)
        ->post(route('reports.entities.fulfillment.store'), [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'notes' => 'Marketplace outbound',
        ])
        ->assertRedirect(route('reports.entities.index'));

    $row = ReportingWarehouseFulfillment::query()
        ->where('warehouse_id', $warehouse->id)
        ->where('customer_id', $customer->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->notes)->toBe('Marketplace outbound');

    $this->actingAs($this->user)
        ->get(route('reports.entities.index'))
        ->assertOk()
        ->assertSee('Gudang WTC', false)
        ->assertSee('Shopee Channel', false);
});
