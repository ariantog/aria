<?php

namespace App\Support;

use App\Models\Addrbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guards new-subdomain migrate+seed so it never runs on the current production domain.
 *
 * Current domain = live L10/L12 host (aria.corenationactive.com) or a clone that
 * still has Crystal production fingerprints. New subdomains get an empty database
 * and run `php artisan app:install-new-domain`.
 */
final class NewDomainInstall
{
    /** @var list<int> Well-known Crystal production ledger ids (type=8). */
    public const PRODUCTION_LEDGER_FINGERPRINT_IDS = [1558, 2696, 2889, 2234, 830];

    public const PRODUCTION_ENTITY_SLUG = 'cv-crystal';

    public const FINGERPRINT_MIN_LEDGERS = 3;

    public static function isCurrentProductionDomain(): bool
    {
        if (self::hasProductionFingerprint()) {
            return true;
        }

        if (self::forceNewDomain()) {
            return false;
        }

        if (self::legacyProductionFlag()) {
            return true;
        }

        return self::appHostIsLegacy();
    }

    public static function allowsBaselineSeed(): bool
    {
        return ! self::isCurrentProductionDomain();
    }

    public static function allowsInstall(): bool
    {
        return ! self::isCurrentProductionDomain();
    }

    public static function refusalReason(): ?string
    {
        if (self::hasProductionFingerprint()) {
            return 'This database matches the current production domain (Crystal ledger IDs or cv-crystal reporting entity).';
        }

        if (self::forceNewDomain()) {
            return null;
        }

        if (self::legacyProductionFlag()) {
            return 'ARIA_LEGACY_PRODUCTION is set.';
        }

        if (self::appHostIsLegacy()) {
            $host = self::appHost();

            return 'APP_URL host is the current production domain ('.$host.').';
        }

        return null;
    }

    public static function forceNewDomain(): bool
    {
        return (bool) config('core-nation.new_domain', false);
    }

    public static function legacyProductionFlag(): bool
    {
        return (bool) config('core-nation.legacy_production', false);
    }

    public static function appHostIsLegacy(): bool
    {
        $host = self::appHost();
        if ($host === '') {
            return false;
        }

        $legacyHosts = array_map('strtolower', config('core-nation.legacy_hosts', []));

        return in_array($host, $legacyHosts, true);
    }

    public static function appHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return strtolower((string) $host);
    }

    public static function hasProductionFingerprint(): bool
    {
        if (self::hasCrystalReportingEntity()) {
            return true;
        }

        if (! Schema::hasTable('customers')) {
            return false;
        }

        $found = DB::table('customers')
            ->whereIn('id', self::PRODUCTION_LEDGER_FINGERPRINT_IDS)
            ->where('type', Addrbook::TYPE_ACCOUNT)
            ->count();

        return $found >= self::FINGERPRINT_MIN_LEDGERS;
    }

    private static function hasCrystalReportingEntity(): bool
    {
        if (! Schema::hasTable('reporting_entities')) {
            return false;
        }

        return DB::table('reporting_entities')
            ->where('slug', self::PRODUCTION_ENTITY_SLUG)
            ->exists();
    }
}
