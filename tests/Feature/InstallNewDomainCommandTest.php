<?php

use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingEntity;

beforeEach(function () {
    config([
        'app.url' => 'http://localhost',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => false,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);
});

it('refuses to install on the current production host', function () {
    config(['app.url' => 'https://aria.corenationactive.com']);

    $this->artisan('app:install-new-domain', [
        '--force' => true,
        '--skip-migrate' => true,
    ])->assertFailed();

    expect(Addrbook::query()->where('name', 'Pelanggan')->exists())->toBeFalse()
        ->and(Operation::query()->where('name', 'Biaya Marketplace')->exists())->toBeFalse();
});

it('refuses to install when the database has a Crystal fingerprint', function () {
    ReportingEntity::create([
        'name' => 'CV Crystal',
        'slug' => 'cv-crystal',
        'is_pkp' => true,
        'is_active' => true,
    ]);

    $this->artisan('app:install-new-domain', [
        '--force' => true,
        '--skip-migrate' => true,
    ])->assertFailed();

    expect(Addrbook::query()->count())->toBe(0);
});

it('ships a maintainer install guide', function () {
    $guide = base_path('doc/new-domain-install.md');

    expect(is_file($guide))->toBeTrue();

    $source = file_get_contents($guide);

    expect($source)->toContain('php artisan app:install-new-domain')
        ->and($source)->toContain('scripts/install-new-domain.sh')
        ->and($source)->toContain('NewDomainSeeder')
        ->and($source)->toContain('ReportingBootstrapSeeder')
        ->and($source)->toContain('DemoDataSeeder')
        ->and($source)->toContain('ARIA_LEGACY_PRODUCTION');
});

it('seeds the new-domain baseline without re-running migrations', function () {
    $this->artisan('app:install-new-domain', [
        '--force' => true,
        '--skip-migrate' => true,
    ])->assertSuccessful();

    expect(Addrbook::query()->where('name', 'Pelanggan')->exists())->toBeTrue()
        ->and(Addrbook::query()->where('name', 'Supplier')->exists())->toBeTrue()
        ->and(Operation::query()->where('name', 'Produksi')->exists())->toBeTrue()
        ->and(Addrbook::query()->where('name', 'Gaji Mingguan')->exists())->toBeTrue();
});
