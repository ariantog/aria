<?php

namespace Database\Seeders;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingTaxAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportingBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['name' => 'CV Crystal', 'slug' => 'cv-crystal', 'is_pkp' => true, 'is_active' => true],
            ['name' => 'CV Cipta', 'slug' => 'cv-cipta', 'is_pkp' => true, 'is_active' => true],
            ['name' => 'PT Indosport', 'slug' => 'pt-indosport', 'is_pkp' => true, 'is_active' => true],
            ['name' => 'CV Cakra', 'slug' => 'cv-cakra', 'is_pkp' => false, 'is_active' => true],
            ['name' => 'AGM', 'slug' => 'agm', 'is_pkp' => true, 'is_active' => true],
            ['name' => 'UAI', 'slug' => 'uai', 'is_pkp' => true, 'is_active' => true],
            ['name' => 'Pribadi', 'slug' => 'pribadi', 'is_pkp' => false, 'is_active' => true],
            ['name' => 'PT Core', 'slug' => 'pt-core', 'is_pkp' => true, 'is_active' => false],
        ];

        foreach ($entities as $row) {
            ReportingEntity::updateOrCreate(['slug' => $row['slug']], $row);
        }

        $ptCore = ReportingEntity::where('slug', 'pt-core')->first();
        if ($ptCore) {
            $ptCore->banks()->detach();
            ReportingTaxAccount::where('reporting_entity_id', $ptCore->id)->delete();
        }

        $operationSlugs = [
            3 => 'marketing',
            4 => 'gaji',
            7 => 'sewa',
            8 => 'kantor',
            9 => 'kantor',
            10 => 'bank',
            11 => 'marketing',
            13 => 'maintenance',
            14 => 'jasa',
            15 => 'sdm',
            16 => 'kantor',
            17 => 'logistik',
            18 => 'pajak',
            19 => 'lain',
            20 => 'lain',
            21 => 'sdm',
            22 => 'lain',
            24 => 'lain',
            25 => 'lain',
            26 => 'lain',
            27 => 'produksi',
            28 => 'marketplace',
        ];

        foreach ($operationSlugs as $id => $slug) {
            Operation::where('id', $id)->update(['report_slug' => $slug]);
        }

        $taxMap = [
            2106 => ['cv-crystal', 'pph'],
            2883 => ['cv-crystal', 'spt'],
            2861 => ['cv-cipta', 'pph'],
            2884 => ['cv-cipta', 'spt'],
            2849 => ['pt-indosport', 'ppn'],
            2885 => ['pt-indosport', 'spt'],
            2862 => ['pt-indosport', 'pph'],
            2863 => ['cv-cakra', 'pph'],
            2896 => ['cv-cakra', 'pph'],
            2941 => ['agm', 'pph'],
            2944 => ['uai', 'pph'],
            2865 => ['pribadi', 'pph'],
            2797 => ['pribadi', 'spt'],
        ];

        foreach ($taxMap as $ledgerId => [$entitySlug, $taxType]) {
            $entity = ReportingEntity::where('slug', $entitySlug)->first();
            if (! $entity || ! Addrbook::find($ledgerId)) {
                continue;
            }
            ReportingTaxAccount::updateOrCreate(
                ['legacy_ledger_id' => $ledgerId],
                ['reporting_entity_id' => $entity->id, 'tax_type' => $taxType],
            );
        }

        $hints = [
            2889 => 'Biaya operasional toko WTC: sewa, transport, utilitas. Juga gudang pengiriman marketplace. Isi catatan untuk detail.',
            2842 => 'Biaya operasional toko Citos: sewa, utilitas, perlengkapan. Juga gudang pengiriman marketplace.',
            2234 => 'Komisi dan biaya platform Shopee.',
            2788 => 'Komisi dan biaya platform TikTok Shop.',
            2881 => 'Biaya platform Lazada.',
            2273 => 'Biaya platform Tokopedia.',
            2099 => 'Biaya partner Metro: sample, fixture, lampu, banner, display.',
            2178 => 'Biaya partner Sogo: sample, fixture, display.',
            2633 => 'Biaya partner Central: sample, fixture, display.',
            1558 => 'Pembelian bahan baku / material produksi.',
            2696 => 'Gaji mingguan jahit — biaya produksi aktual (bukan borongan).',
            830 => 'Sewa HQ Sambisari — kantor pusat, bukan toko WTC/Citos.',
        ];

        foreach ($hints as $id => $hint) {
            Addrbook::where('id', $id)->where('type', Addrbook::TYPE_ACCOUNT)->update(['ledger_hint' => $hint]);
        }

        $roles = [
            1558 => ReportingLedgerRole::Material,
            2696 => ReportingLedgerRole::ProductionCost,
            2889 => ReportingLedgerRole::TokoCost,
            2842 => ReportingLedgerRole::TokoCost,
            2234 => ReportingLedgerRole::MarketplaceCost,
            2788 => ReportingLedgerRole::MarketplaceCost,
        ];

        foreach ($roles as $id => $role) {
            if (! Addrbook::find($id)) {
                continue;
            }
            ReportingLedgerRoleModel::updateOrCreate(
                ['customer_id' => $id],
                ['role' => $role->value],
            );
        }
    }
}
