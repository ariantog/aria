<?php

namespace Database\Seeders;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Support\NewDomainBaselineWriter;
use App\Support\NewDomainChartOfAccounts;
use App\Support\NewDomainInstall;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Typical operations (journal categories) and ledgers for a new subdomain.
 *
 * Names follow the simplified chart of accounts. IDs are auto-increment —
 * this does not reuse Crystal production ledger IDs.
 *
 * Refuses to run on the current production domain.
 */
class TypicalLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (! NewDomainInstall::allowsBaselineSeed()) {
            $this->command?->error('TypicalLedgerSeeder refused: '.NewDomainInstall::refusalReason());

            return;
        }

        $operationsByName = [];
        foreach (NewDomainChartOfAccounts::operations() as $row) {
            $attributes = ['description' => $row['description']];
            if (Schema::hasColumn('operations', 'report_slug')) {
                $attributes['report_slug'] = $row['report_slug'];
            }

            $operation = Operation::query()->firstOrCreate(
                ['name' => $row['name']],
                $attributes,
            );

            if (Schema::hasColumn('operations', 'report_slug') && blank($operation->report_slug)) {
                $operation->update(['report_slug' => $row['report_slug']]);
            }

            $operationsByName[$row['name']] = $operation;
        }

        $hasRolesTable = Schema::hasTable('reporting_ledger_roles');

        foreach (NewDomainChartOfAccounts::ledgers() as $row) {
            $operation = $operationsByName[$row['operation']] ?? null;
            if (! $operation) {
                continue;
            }

            $attributes = [
                'description' => $row['description'],
                'operation_id' => $operation->id,
            ];
            if (Schema::hasColumn('customers', 'ledger_hint')) {
                $attributes['ledger_hint'] = $row['hint'];
            }
            if (Schema::hasColumn('customers', 'is_active_in_reports')) {
                $attributes['is_active_in_reports'] = true;
            }

            $ledger = NewDomainBaselineWriter::ensureAddrbook(
                $row['name'],
                Addrbook::TYPE_ACCOUNT,
                $attributes,
            );

            if ($hasRolesTable && isset($row['role'])) {
                ReportingLedgerRoleModel::query()->updateOrCreate(
                    ['customer_id' => $ledger->id],
                    ['role' => ReportingLedgerRole::from($row['role'])->value],
                );
            }
        }

        $this->command?->info(
            'Typical ledgers seeded: '.count(NewDomainChartOfAccounts::operations()).' operations, '
            .count(NewDomainChartOfAccounts::ledgers()).' ledgers.',
        );
    }
}
