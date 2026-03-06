<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Operation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyJournalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-journals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy operations to the new operations table and update operation_ids in addrbooks.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of legacy journals...');

        $legacyDb = DB::connection('core_legacy');

        // Migrate operations
        $this->info('Migrating legacy operations...');
        $legacyOperations = $legacyDb->table('operations')->get();

        foreach ($legacyOperations as $op) {
            Operation::updateOrCreate(
                ['id' => $op->id],
                [
                    'name' => $op->name,
                    'description' => $op->note ?? '',
                    'created_at' => property_exists($op, 'created_at') ? $op->created_at : now(),
                    'updated_at' => property_exists($op, 'updated_at') ? $op->updated_at : now(),
                ]
            );
        }
        $this->info('Successfully migrated '.count($legacyOperations).' operations.');

        // Update addrbooks' operation_id based on legacy customers type=8 parent_id
        $this->info("Updating addrbooks' operation_id from legacy customers...");
        $legacyAccounts = $legacyDb->table('customers')->where('type', 8)->get();

        $updatedCount = 0;
        foreach ($legacyAccounts as $acc) {
            // Find by ID in current DB addrbooks (including trashed ones)
            $addrbook = Addrbook::withTrashed()->find($acc->id);
            $operationExists = DB::table('operations')->where('id', $acc->parent_id)->exists();
            $operationId = $operationExists ? $acc->parent_id : null;

            if ($addrbook) {
                if ($addrbook->operation_id !== $operationId || $addrbook->type != Addrbook::TYPE_ACCOUNT) {
                    $addrbook->type = Addrbook::TYPE_ACCOUNT;
                    $addrbook->operation_id = $operationId;
                    $addrbook->save();
                    $updatedCount++;
                }
            } else {
                // If it doesn't exist, insert new record
                $this->warn("Addrbook ID {$acc->id} (from legacy customer) not found. Inserting new record...");
                $newAddrbook = new Addrbook;
                $newAddrbook->id = $acc->id;
                $newAddrbook->name = $acc->name;
                $newAddrbook->type = Addrbook::TYPE_ACCOUNT;
                $newAddrbook->operation_id = $operationId;
                $newAddrbook->save();
                $updatedCount++;
            }
        }

        $this->info("Successfully updated/inserted {$updatedCount} addrbooks with operation IDs.");

        $this->info('Legacy migration completed successfully!');
    }
}
