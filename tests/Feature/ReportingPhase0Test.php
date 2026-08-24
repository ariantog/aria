<?php

use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingChannelBank;
use App\Models\ReportingEntity;
use App\Models\User;
use Database\Seeders\ReportingBootstrapSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $permissions = Addrbook::getPermissions();
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }
    $this->user->givePermissionTo(array_values($permissions));
});

it('renders reporting entities index for superadmin', function () {
    $this->actingAs($this->user)
        ->get(route('reports.entities.index'))
        ->assertOk()
        ->assertSee('Reporting Entities', false);
});

it('lists assigned banks on the entities index', function () {
    $bank = Addrbook::create(['name' => 'BCA Crystal', 'type' => Addrbook::TYPE_BANK]);
    $entity = ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal-test', 'is_pkp' => true]);
    $entity->banks()->attach($bank->id, ['is_active' => true]);

    $this->actingAs($this->user)
        ->get(route('reports.entities.index'))
        ->assertOk()
        ->assertSee('BCA Crystal', false)
        ->assertSee('CV Crystal', false);
});

it('forbids reporting entities for non-superadmin', function () {
    $other = User::factory()->create(['id' => 99]);

    $this->actingAs($other)
        ->get(route('reports.entities.index'))
        ->assertForbidden();
});

it('seeds reporting entities', function () {
    $this->seed(ReportingBootstrapSeeder::class);

    expect(ReportingEntity::count())->toBeGreaterThanOrEqual(8);
    expect(ReportingEntity::where('slug', 'cv-crystal')->exists())->toBeTrue();
});

it('stores reporting fields on customer create', function () {
    $bank = Addrbook::create(['name' => 'BCA Report', 'type' => Addrbook::TYPE_BANK]);

    $this->actingAs($this->user)
        ->post(route('addrbook.store'), [
            'name' => 'Shopee Channel',
            'type' => Addrbook::TYPE_CUSTOMER,
            'npwp' => '12.345.678.9-000.000',
            'default_bank_id' => $bank->id,
            'is_internal_lending' => true,
            'is_online' => false,
            'ppn' => false,
            'initial_balance' => 0,
        ])
        ->assertRedirect();

    $customer = Addrbook::where('name', 'Shopee Channel')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->npwp)->toBe('12.345.678.9-000.000')
        ->and($customer->default_bank_id)->toBe($bank->id)
        ->and($customer->is_internal_lending)->toBeTrue();

    expect(ReportingChannelBank::where('customer_id', $customer->id)->value('bank_id'))->toBe($bank->id);
});

it('stores ledger hint on account create', function () {
    $operation = Operation::factory()->create(['name' => 'Test Op']);

    $this->actingAs($this->user)
        ->post(route('addrbook.store'), [
            'name' => 'Biaya Test',
            'type' => Addrbook::TYPE_ACCOUNT,
            'operation_id' => $operation->id,
            'ledger_hint' => 'Use for test expenses only.',
            'is_online' => false,
            'ppn' => false,
            'initial_balance' => 0,
        ])
        ->assertRedirect();

    $account = Addrbook::where('name', 'Biaya Test')->first();
    expect($account->ledger_hint)->toBe('Use for test expenses only.')
        ->and($account->operation_id)->toBe($operation->id);
});

it('returns ledger_hint in transaction lookup', function () {
    Addrbook::create([
        'name' => 'Hinted Ledger',
        'type' => Addrbook::TYPE_ACCOUNT,
        'ledger_hint' => 'Pick this for office supplies.',
    ]);

    $url = route('transactions.lookup', [
        'type' => 'cash-out',
        'role' => 'receiver',
        'addrbook_type' => Addrbook::TYPE_ACCOUNT,
    ]).'&search=Hint';

    $row = collect($this->actingAs($this->user)->getJson($url)->assertOk()->json())->first();

    expect($row)->not->toBeNull()
        ->and($row['ledger_hint'])->toBe('Pick this for office supplies.');
});

it('assigns banks to a reporting entity', function () {
    $entity = ReportingEntity::create(['name' => 'Test Entity', 'slug' => 'test-entity', 'is_pkp' => true]);
    $bank = Addrbook::create(['name' => 'Entity Bank', 'type' => Addrbook::TYPE_BANK]);

    $this->actingAs($this->user)
        ->put(route('reports.entities.update', $entity), [
            'name' => 'Test Entity',
            'slug' => 'test-entity',
            'is_pkp' => true,
            'is_active' => true,
            'bank_ids' => [$bank->id],
        ])
        ->assertRedirect(route('reports.entities.index'));

    expect($entity->fresh()->banks->pluck('id')->all())->toBe([$bank->id]);
});

it('rejects assigning a bank that already belongs to another entity', function () {
    $bank = Addrbook::create(['name' => 'Shared Bank', 'type' => Addrbook::TYPE_BANK]);
    $entityA = ReportingEntity::create(['name' => 'Entity A', 'slug' => 'entity-a', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'Entity B', 'slug' => 'entity-b', 'is_pkp' => false]);
    $entityA->banks()->attach($bank->id, ['is_active' => true]);

    $this->actingAs($this->user)
        ->from(route('reports.entities.edit', $entityB))
        ->put(route('reports.entities.update', $entityB), [
            'name' => 'Entity B',
            'slug' => 'entity-b',
            'is_pkp' => false,
            'is_active' => true,
            'bank_ids' => [$bank->id],
        ])
        ->assertRedirect(route('reports.entities.edit', $entityB))
        ->assertSessionHasErrors('bank_ids');

    expect($entityB->fresh()->banks)->toBeEmpty()
        ->and($entityA->fresh()->banks->pluck('id')->all())->toBe([$bank->id]);
});
