<?php

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Support\NewDomainInstall;

it('treats localhost as a new domain when the database has no production fingerprint', function () {
    config([
        'app.url' => 'http://localhost',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => false,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::isCurrentProductionDomain())->toBeFalse()
        ->and(NewDomainInstall::allowsBaselineSeed())->toBeTrue()
        ->and(NewDomainInstall::allowsInstall())->toBeTrue();
});

it('refuses when APP_URL is the current production host', function () {
    config([
        'app.url' => 'https://aria.corenationactive.com',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => false,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::isCurrentProductionDomain())->toBeTrue()
        ->and(NewDomainInstall::allowsInstall())->toBeFalse()
        ->and(NewDomainInstall::refusalReason())->toContain('aria.corenationactive.com');
});

it('refuses when ARIA_LEGACY_PRODUCTION is set', function () {
    config([
        'app.url' => 'https://shop.example.com',
        'core-nation.legacy_production' => true,
        'core-nation.new_domain' => false,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::isCurrentProductionDomain())->toBeTrue()
        ->and(NewDomainInstall::refusalReason())->toContain('ARIA_LEGACY_PRODUCTION');
});

it('allows ARIA_NEW_DOMAIN to bypass a legacy host when the database is empty', function () {
    config([
        'app.url' => 'https://aria.corenationactive.com',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => true,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::isCurrentProductionDomain())->toBeFalse()
        ->and(NewDomainInstall::allowsInstall())->toBeTrue();
});

it('always refuses when Crystal reporting entity exists even if ARIA_NEW_DOMAIN is set', function () {
    ReportingEntity::create([
        'name' => 'CV Crystal',
        'slug' => NewDomainInstall::PRODUCTION_ENTITY_SLUG,
        'is_pkp' => true,
        'is_active' => true,
    ]);

    config([
        'app.url' => 'http://localhost',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => true,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::hasProductionFingerprint())->toBeTrue()
        ->and(NewDomainInstall::isCurrentProductionDomain())->toBeTrue()
        ->and(NewDomainInstall::refusalReason())->toContain('cv-crystal');
});

it('always refuses when well-known production ledger ids exist', function () {
    $ids = array_slice(NewDomainInstall::PRODUCTION_LEDGER_FINGERPRINT_IDS, 0, 3);
    foreach ($ids as $id) {
        Addrbook::unguarded(fn () => Addrbook::create([
            'id' => $id,
            'name' => 'Prod ledger '.$id,
            'type' => Addrbook::TYPE_ACCOUNT,
        ]));
    }

    config([
        'app.url' => 'http://localhost',
        'core-nation.legacy_production' => false,
        'core-nation.new_domain' => true,
        'core-nation.legacy_hosts' => ['aria.corenationactive.com'],
    ]);

    expect(NewDomainInstall::hasProductionFingerprint())->toBeTrue()
        ->and(NewDomainInstall::isCurrentProductionDomain())->toBeTrue();
});
