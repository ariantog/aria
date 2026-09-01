<?php

use App\Enums\AddrbookType;
use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\AddrbookStat;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Support\NewDomainChartOfAccounts;
use Database\Seeders\AddrbookPlaceholderSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NewDomainSeeder;
use Database\Seeders\TypicalLedgerSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    config([
        'app.url' => 'http://localhost',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => false,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);
});

it('seeds one placeholder addrbook per type', function () {
    Artisan::call('db:seed', ['--class' => AddrbookPlaceholderSeeder::class, '--force' => true]);

    $placeholders = NewDomainChartOfAccounts::placeholders();
    expect($placeholders)->toHaveCount(count(AddrbookType::cases()));

    foreach ($placeholders as $row) {
        $addrbook = Addrbook::query()->where('name', $row['name'])->where('type', $row['type'])->first();
        expect($addrbook)->not->toBeNull();
        expect(AddrbookStat::query()->where('customer_id', $addrbook->id)->exists())->toBeTrue();
    }
});

it('seeds typical operations and ledgers with reporting roles', function () {
    Artisan::call('db:seed', ['--class' => TypicalLedgerSeeder::class, '--force' => true]);

    foreach (NewDomainChartOfAccounts::operations() as $row) {
        $operation = Operation::query()->where('name', $row['name'])->first();
        expect($operation)->not->toBeNull()
            ->and($operation->report_slug)->toBe($row['report_slug']);
    }

    foreach (NewDomainChartOfAccounts::ledgers() as $row) {
        $ledger = Addrbook::query()
            ->where('name', $row['name'])
            ->where('type', Addrbook::TYPE_ACCOUNT)
            ->first();
        expect($ledger)->not->toBeNull();

        $operation = Operation::query()->where('name', $row['operation'])->first();
        expect((int) $ledger->parent_id)->toBe($operation->id)
            ->and($ledger->ledger_hint)->toBe($row['hint']);

        if (isset($row['role'])) {
            expect(ReportingLedgerRoleModel::query()
                ->where('customer_id', $ledger->id)
                ->where('role', $row['role'])
                ->exists())->toBeTrue();
        }
    }

    expect(ReportingLedgerRoleModel::query()->where('role', ReportingLedgerRole::Material->value)->exists())->toBeTrue()
        ->and(ReportingLedgerRoleModel::query()->where('role', ReportingLedgerRole::ProductionCost->value)->exists())->toBeTrue()
        ->and(ReportingLedgerRoleModel::query()->where('role', ReportingLedgerRole::MarketplaceCost->value)->count())->toBe(4);
});

it('is idempotent when placeholder and ledger seeders run twice', function () {
    Artisan::call('db:seed', ['--class' => TypicalLedgerSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => AddrbookPlaceholderSeeder::class, '--force' => true]);
    $addrbookCount = Addrbook::query()->count();
    $operationCount = Operation::query()->count();

    Artisan::call('db:seed', ['--class' => TypicalLedgerSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => AddrbookPlaceholderSeeder::class, '--force' => true]);

    expect(Addrbook::query()->count())->toBe($addrbookCount)
        ->and(Operation::query()->count())->toBe($operationCount);
});

it('refuses typical ledger seeding on the current production domain', function () {
    ReportingEntity::create([
        'name' => 'CV Crystal',
        'slug' => 'cv-crystal',
        'is_pkp' => true,
        'is_active' => true,
    ]);

    Artisan::call('db:seed', ['--class' => TypicalLedgerSeeder::class, '--force' => true]);
    Artisan::call('db:seed', ['--class' => AddrbookPlaceholderSeeder::class, '--force' => true]);

    expect(Operation::query()->where('name', 'Biaya Marketplace')->exists())->toBeFalse()
        ->and(Addrbook::query()->where('name', 'Pelanggan')->exists())->toBeFalse();
});

it('runs the main new-domain seeder on an empty database', function () {
    Artisan::call('db:seed', ['--class' => NewDomainSeeder::class, '--force' => true]);

    expect(Addrbook::query()->where('name', 'Pelanggan')->where('type', Addrbook::TYPE_CUSTOMER)->exists())->toBeTrue()
        ->and(Addrbook::query()->where('name', 'Kas / Bank')->where('type', Addrbook::TYPE_BANK)->exists())->toBeTrue()
        ->and(Operation::query()->where('name', 'Gaji & Upah')->exists())->toBeTrue()
        ->and(Addrbook::query()->where('name', 'Material Produksi')->exists())->toBeTrue()
        ->and(\App\Models\User::query()->where('username', 'superadmin')->exists())->toBeTrue();
});

it('does not seed from the new-domain migration during tests', function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_09_01_140000_seed_new_domain_baseline.php',
        '--force' => true,
    ]);

    expect(Addrbook::query()->where('name', 'Pelanggan')->exists())->toBeFalse()
        ->and(Operation::query()->where('name', 'Biaya Marketplace')->exists())->toBeFalse();
});

it('includes placeholders and typical ledgers from DatabaseSeeder on a new domain', function () {
    Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(Addrbook::query()->where('name', 'Gudang')->exists())->toBeTrue()
        ->and(Operation::query()->where('report_slug', 'marketplace')->exists())->toBeTrue()
        ->and(Addrbook::query()->where('name', 'BCA Operasional')->exists())->toBeTrue();
});
