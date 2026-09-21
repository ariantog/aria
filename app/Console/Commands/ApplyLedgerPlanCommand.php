<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Operation;
use App\Models\ReportingEntity;
use App\Models\ReportingTaxAccount;
use App\Support\LedgerDuplicateMergePlan;
use App\Support\OperationSimplificationPlan;
use App\Support\ProductionLedgerCopy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLedgerPlanCommand extends Command
{
    protected $signature = 'reporting:apply-ledger-plan {--dry-run : Show changes without applying}';

    protected $description = 'Rename/merge ledgers, fill descriptions, map reporting roles, and soft-delete obsolete accounts';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $softDelete = ProductionLedgerCopy::softDeleteIds();

        if ($dry) {
            $this->info('Dry run — no changes applied.');
        }

        DB::transaction(function () use ($dry, $softDelete) {
            LedgerDuplicateMergePlan::apply($dry, function (string $message): void {
                $this->line($message);
            });

            foreach ($softDelete as $id) {
                $a = Addrbook::find($id);
                if (! $a) {
                    continue;
                }
                $this->line("Soft-delete {$id}: {$a->name}");
                if (! $dry) {
                    $a->delete();
                }
            }

            $this->retirePtCoreEntity($dry);
            $this->applyOperationPlan($dry);

            ProductionLedgerCopy::apply($dry, function (string $message): void {
                $this->line($message);
            });
            ProductionLedgerCopy::applyRoles($dry, function (string $message): void {
                $this->line($message);
            });
            ProductionLedgerCopy::applyTaxMaps($dry, function (string $message): void {
                $this->line($message);
            });
        });

        $this->info($dry ? 'Dry run complete.' : 'Ledger plan applied.');

        return self::SUCCESS;
    }

    private function retirePtCoreEntity(bool $dry): void
    {
        $entity = ReportingEntity::query()->where('slug', 'pt-core')->first();
        if (! $entity) {
            return;
        }

        $bankCount = $entity->banks()->count();
        $this->line("Retire reporting entity: {$entity->name} (detach {$bankCount} bank(s), mark inactive)");

        if ($dry) {
            return;
        }

        $entity->banks()->detach();
        ReportingTaxAccount::query()->where('reporting_entity_id', $entity->id)->delete();
        $entity->update([
            'is_active' => false,
            'notes' => trim(($entity->notes ? $entity->notes."\n" : '').'Retired Aug 2026 — legal entity no longer operating.'),
        ]);
    }

    private function applyOperationPlan(bool $dry): void
    {
        $this->info('Operation categories:');

        foreach (OperationSimplificationPlan::newOperations() as $id => $data) {
            $existing = Operation::withTrashed()->find($id);
            $label = $data['name'];
            if ($existing && ! $existing->trashed()) {
                $this->line("Ensure op {$id}: {$label}");
            } else {
                $this->line("Create op {$id}: {$label}");
            }
            if (! $dry) {
                $op = Operation::withTrashed()->find($id);
                if ($op) {
                    if ($op->trashed()) {
                        $op->restore();
                    }
                    $op->update($data);
                } else {
                    Operation::forceCreate(array_merge(['id' => $id], $data));
                }
            }
        }

        foreach (OperationSimplificationPlan::renames() as $id => $data) {
            $op = Operation::find($id);
            if (! $op) {
                continue;
            }
            $this->line("Rename op {$id}: {$op->name} → {$data['name']}");
            if (! $dry) {
                $op->update($data);
            }
        }

        foreach (OperationSimplificationPlan::bulkReparentByOperationEarly() as $from => $to) {
            $count = Addrbook::account()->where('parent_id', $from)->count();
            if ($count === 0) {
                continue;
            }
            $this->line("Re-parent {$count} account(s): operation {$from} → {$to}");
            if (! $dry) {
                Addrbook::account()->where('parent_id', $from)->update(['parent_id' => $to]);
            }
        }

        foreach (OperationSimplificationPlan::ledgerReparents() as $ledgerId => $operationId) {
            $ledger = Addrbook::account()->find($ledgerId);
            if (! $ledger || (int) $ledger->parent_id === $operationId) {
                continue;
            }
            $this->line("Re-parent ledger {$ledgerId} ({$ledger->name}): op {$ledger->parent_id} → {$operationId}");
            if (! $dry) {
                $ledger->update(['parent_id' => $operationId]);
            }
        }

        foreach (OperationSimplificationPlan::bulkReparentByOperationLate() as $from => $to) {
            $count = Addrbook::account()->where('parent_id', $from)->count();
            if ($count === 0) {
                continue;
            }
            $this->line("Re-parent {$count} account(s): operation {$from} → {$to}");
            if (! $dry) {
                Addrbook::account()->where('parent_id', $from)->update(['parent_id' => $to]);
            }
        }

        foreach (OperationSimplificationPlan::softDeleteOperationIds() as $id) {
            $op = Operation::find($id);
            if (! $op) {
                continue;
            }
            $remaining = Addrbook::account()->where('parent_id', $id)->count();
            if ($remaining > 0) {
                $this->warn("Skip soft-delete op {$id} ({$op->name}): {$remaining} account(s) still linked");

                continue;
            }
            $this->line("Soft-delete op {$id}: {$op->name}");
            if (! $dry) {
                $op->delete();
            }
        }
    }
}
