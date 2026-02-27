<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyAddrbook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-addrbook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate customers from legacy database to addrbooks table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting migration of Address Book data...');

        $legacyDb = DB::connection('core_legacy');

        try {
            Schema::disableForeignKeyConstraints();

            // 1. Clear existing data
            $this->warn('Clearing existing addrbooks...');
            DB::table('addrbooks')->truncate();

            // 2. Mapping for types
            $typeMapping = [
                1 => Addrbook::TYPE_CUSTOMER,    // 1 -> 1
                2 => Addrbook::TYPE_RESELLER,    // 2 -> 7
                3 => Addrbook::TYPE_SUPPLIER,    // 3 -> 4
                4 => Addrbook::TYPE_WAREHOUSE,   // 4 -> 2
                5 => Addrbook::TYPE_V_WAREHOUSE, // 5 -> 5
                6 => Addrbook::TYPE_ACCOUNT,     // 6 -> 8
                7 => Addrbook::TYPE_V_ACCOUNT,   // 7 -> 6
            ];

            // 3. Migrate Customers
            $this->info('Migrating Customers to Addrbooks...');
            $totalCount = $legacyDb->table('customers')->count();
            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            $legacyDb->table('customers')->orderBy('id')->chunk(100, function ($customers) use ($typeMapping, $progressBar) {
                foreach ($customers as $customer) {
                    DB::table('addrbooks')->insert([
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'member_id' => $customer->memberId ?: null,
                        'address' => $customer->address,
                        'phone' => $customer->phone,
                        'email' => $customer->email,
                        'contact_person' => null, // Legacy lacks this column
                        'is_online' => (bool) $customer->is_online,
                        'ppn' => (bool) $customer->ppn,
                        'type' => $typeMapping[$customer->type] ?? Addrbook::TYPE_OTHER,
                        'description' => $customer->description,
                        'deleted_at' => $this->validateDate($customer->deleted_at),
                        'created_at' => $this->validateDate($customer->created_at),
                        'updated_at' => $this->validateDate($customer->updated_at),
                    ]);
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine();

            Schema::enableForeignKeyConstraints();
            $this->info('Migration completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Schema::enableForeignKeyConstraints();
            $this->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Validate and format date.
     */
    private function validateDate(?string $date): ?string
    {
        if (!$date || str_starts_with($date, '-') || str_contains($date, '0000-00-00')) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
