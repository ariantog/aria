<?php

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\LedgerMergeMap;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingTaxAccount;
use App\Support\ProductionLedgerCopy;
use Illuminate\Support\Facades\Artisan;

it('retires pt core entity and soft-deletes its tax ledgers', function () {
    $ptCore = ReportingEntity::create([
        'name' => 'PT Core',
        'slug' => 'pt-core',
        'is_pkp' => true,
        'is_active' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA GIRO', 'type' => Addrbook::TYPE_BANK]);
    $ledger = Addrbook::create(['name' => 'PPN PT CORE', 'type' => Addrbook::TYPE_ACCOUNT]);
    $ptCore->banks()->attach($bank->id, ['is_active' => true]);
    ReportingTaxAccount::create([
        'legacy_ledger_id' => $ledger->id,
        'reporting_entity_id' => $ptCore->id,
        'tax_type' => 'ppn',
    ]);

    Artisan::call('reporting:apply-ledger-plan');

    $ptCore->refresh();
    expect($ptCore->is_active)->toBeFalse()
        ->and($ptCore->banks)->toBeEmpty()
        ->and(ReportingTaxAccount::where('reporting_entity_id', $ptCore->id)->count())->toBe(0);
});

it('dry-run lists pt core retirement without applying changes', function () {
    $ptCore = ReportingEntity::create([
        'name' => 'PT Core',
        'slug' => 'pt-core',
        'is_pkp' => true,
        'is_active' => true,
    ]);
    $bank = Addrbook::create(['name' => 'BCA GIRO', 'type' => Addrbook::TYPE_BANK]);
    $ptCore->banks()->attach($bank->id, ['is_active' => true]);

    Artisan::call('reporting:apply-ledger-plan', ['--dry-run' => true]);

    expect($ptCore->fresh()->is_active)->toBeTrue()
        ->and($ptCore->fresh()->banks)->toHaveCount(1);
});

it('simplifies operation categories and re-parents accounts', function () {
    $marketing = Operation::forceCreate(['id' => 3, 'name' => 'Marketing', 'description' => '']);
    $utilitas = Operation::forceCreate(['id' => 9, 'name' => 'Utilitas', 'description' => '']);
    $kantor = Operation::forceCreate(['id' => 8, 'name' => 'Perlengkapan Kantor', 'description' => '']);
    $nonOp = Operation::forceCreate(['id' => 24, 'name' => 'Non-Operational', 'description' => '']);

    $utilitasAccount = Addrbook::create([
        'name' => 'PDAM',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $utilitas->id,
    ]);
    $shopee = Addrbook::unguarded(fn () => Addrbook::create([
        'id' => 2234,
        'name' => 'Shopee Cost',
        'type' => Addrbook::TYPE_ACCOUNT,
        'parent_id' => $nonOp->id,
    ]));

    Artisan::call('reporting:apply-ledger-plan');

    expect($marketing->fresh()->name)->toBe('Marketing Umum')
        ->and($kantor->fresh()->name)->toBe('Kantor & Utilitas')
        ->and($utilitasAccount->fresh()->parent_id)->toBe($kantor->id)
        ->and($shopee->fresh()->parent_id)->toBe(29)
        ->and(Operation::find(29)?->name)->toBe('Biaya Marketplace')
        ->and(Operation::find(9))->toBeNull();
});

it('dry-run succeeds on a fresh bootstrap without production ledger ids', function () {
    $exit = Artisan::call('reporting:apply-ledger-plan', ['--dry-run' => true]);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Dry run complete.')
        ->and($output)->not->toContain('Skip soft-delete op');
});

it('fills descriptions and roles on prod-shaped ledgers', function () {
    Operation::forceCreate(['id' => 3, 'name' => 'Marketing', 'description' => '']);
    Operation::forceCreate(['id' => 4, 'name' => 'Gaji dan Upah', 'description' => '']);
    Operation::forceCreate(['id' => 27, 'name' => 'Ongkos Produksi', 'description' => '']);
    Operation::forceCreate(['id' => 28, 'name' => 'Operational Luar', 'description' => '']);
    Operation::forceCreate(['id' => 24, 'name' => 'Non-Operational', 'description' => '']);

    $seed = function (int $id, string $name, int $parent): void {
        Addrbook::unguarded(fn () => Addrbook::create([
            'id' => $id,
            'name' => $name,
            'type' => Addrbook::TYPE_ACCOUNT,
            'parent_id' => $parent,
            'description' => '',
        ]));
    };

    $seed(2234, 'Shopee Cost', 24);
    $seed(2250, 'Social Media Cost', 3);
    $seed(2640, 'Collab Cost', 3);
    $seed(2889, 'WTC Cost', 28);
    $seed(2184, 'WTC Transport Cost', 17);
    $seed(1558, 'Material Produksi', 27);
    $seed(2696, 'Gaji Mingguan', 4);
    $seed(817, 'Gaji Harian', 4);
    $seed(2106, 'PPH Crystal', 18);

    ReportingEntity::create(['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true]);

    $exit = Artisan::call('reporting:apply-ledger-plan');

    expect($exit)->toBe(0)
        ->and(Addrbook::find(2234)?->name)->toBe('Biaya Shopee')
        ->and(Addrbook::find(2234)?->description)->toBe('Biaya channel Shopee')
        ->and(Addrbook::find(2234)?->ledger_hint)->toContain('Shopee')
        ->and(Addrbook::find(2250)?->name)->toBe('Biaya Marketing Digital')
        ->and(Addrbook::find(2640))->toBeNull()
        ->and(LedgerMergeMap::resolveCanonicalCustomerId(2640))->toBe(2250)
        ->and(Addrbook::find(2889)?->name)->toBe('Biaya Toko WTC')
        ->and(Addrbook::find(2889)?->description)->not->toBeEmpty()
        ->and(Addrbook::find(817))->toBeNull()
        ->and(Addrbook::find(2106))->not->toBeNull()
        ->and(Addrbook::find(2106)?->description)->toBe('PPh Crystal')
        ->and(ReportingLedgerRoleModel::query()->where('customer_id', 2106)->value('role'))
        ->toBe(ReportingLedgerRole::TaxPayment)
        ->and(ReportingLedgerRoleModel::query()->where('customer_id', 1558)->value('role'))
        ->toBe(ReportingLedgerRole::Material)
        ->and(ReportingTaxAccount::query()->where('legacy_ledger_id', 2106)->exists())->toBeTrue();
});

it('restores previously soft-deleted cash-out tax ledgers', function () {
    Addrbook::unguarded(fn () => Addrbook::create([
        'id' => 2802,
        'name' => 'PPN',
        'type' => Addrbook::TYPE_ACCOUNT,
        'description' => '',
    ]));
    Addrbook::find(2802)?->delete();

    expect(Addrbook::find(2802))->toBeNull()
        ->and(Addrbook::withTrashed()->find(2802))->not->toBeNull();

    Artisan::call('reporting:apply-ledger-plan');

    $restored = Addrbook::find(2802);
    expect($restored)->not->toBeNull()
        ->and($restored->trashed())->toBeFalse()
        ->and($restored->description)->toBe('PPN (setor)')
        ->and(ReportingLedgerRoleModel::query()->where('customer_id', 2802)->value('role'))
        ->toBe(ReportingLedgerRole::TaxPayment);
});

it('does not overwrite an existing ledger description or role', function () {
    Addrbook::unguarded(fn () => Addrbook::create([
        'id' => 2234,
        'name' => 'Biaya Shopee',
        'type' => Addrbook::TYPE_ACCOUNT,
        'description' => 'Custom staff note',
        'ledger_hint' => 'Custom hint',
    ]));
    ReportingLedgerRoleModel::create([
        'customer_id' => 2234,
        'role' => ReportingLedgerRole::Exclude,
    ]);

    ProductionLedgerCopy::apply();
    ProductionLedgerCopy::applyRoles();

    expect(Addrbook::find(2234)?->description)->toBe('Custom staff note')
        ->and(Addrbook::find(2234)?->ledger_hint)->toBe('Custom hint')
        ->and(ReportingLedgerRoleModel::query()->where('customer_id', 2234)->value('role'))
        ->toBe(ReportingLedgerRole::Exclude);
});
