<?php

use App\Models\Addrbook;
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
