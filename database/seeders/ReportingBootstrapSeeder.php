<?php

namespace Database\Seeders;

use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingTaxAccount;
use App\Support\OperationSimplificationPlan;
use App\Support\ProductionLedgerCopy;
use Illuminate\Database\Seeder;

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

        $operationSlugs = [];
        foreach (array_merge(
            OperationSimplificationPlan::newOperations(),
            OperationSimplificationPlan::renames(),
        ) as $id => $data) {
            $operationSlugs[$id] = $data['report_slug'];
        }

        foreach ($operationSlugs as $id => $slug) {
            Operation::where('id', $id)->update(['report_slug' => $slug]);
        }

        ProductionLedgerCopy::applyTaxMaps();
        ProductionLedgerCopy::apply();
        ProductionLedgerCopy::applyRoles();
    }
}
