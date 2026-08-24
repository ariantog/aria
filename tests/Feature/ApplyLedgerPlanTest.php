<?php

use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingTaxAccount;
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
