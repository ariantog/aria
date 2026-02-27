<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportCoreCustomersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clean existing data
        Schema::disableForeignKeyConstraints();
        DB::table('addrbook_stats')->truncate();
        DB::table('addrbook_classes')->truncate();
        DB::table('addrbooks')->truncate();
        Schema::enableForeignKeyConstraints();

        $this->command->info('Addrbooks and related tables truncated.');

        // 2. Fetch from Core Legacy DB
        $query = DB::connection('core_legacy')->table('customers')->orderBy('id');

        $count = $query->count();
        $this->command->info("Found {$count} customers in Core DB.");

        $bar = $this->command->getOutput()->createProgressBar($count);
        $bar->start();

        // 3. Process and Insert
        $query->chunk(100, function ($customers) use ($bar) {
            $addrbooksToInsert = [];
            $customerIds = [];

            foreach ($customers as $customer) {
                // Sanitize Dates
                $createdAt = ($customer->created_at === '0000-00-00 00:00:00' || ! $customer->created_at) ? now() : $customer->created_at;
                $updatedAt = ($customer->updated_at === '0000-00-00 00:00:00' || ! $customer->updated_at) ? now() : $customer->updated_at;

                $addrbooksToInsert[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'address' => $customer->address ?? null,
                    'phone' => $customer->phone ?? null,
                    'email' => $customer->email ?? null,
                    'contact_person' => $customer->contact_person ?? ($customer->cp ?? null),
                    'is_online' => $customer->is_online ?? false,
                    'ppn' => $customer->ppn ?? false,
                    'member_id' => $customer->member_id ?? null,
                    'type' => $customer->type, // Direct use of core type
                    'description' => $customer->description ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];

                $customerIds[] = $customer->id;
            }

            // Insert Addrbooks
            DB::table('addrbooks')->insert($addrbooksToInsert);

            // Fetch and Insert Classes
            $classes = DB::connection('core_legacy')
                ->table('customer_class')
                ->whereIn('customer_id', $customerIds)
                ->get();

            $classesToInsert = [];
            foreach ($classes as $class) {
                // Sanitize Date
                $classDate = ($class->date === '0000-00-00' || ! $class->date) ? '2000-01-01' : $class->date;

                $classesToInsert[] = [
                    'addrbook_id' => $class->customer_id,
                    'type' => $class->customer_type, // Mapping customer_type to type
                    'date' => $classDate,
                    'cash_in' => $class->cash_in,
                    'cash_out' => $class->cash_out,
                    'sell' => $class->sell,
                    'buy' => $class->buy,
                    'return' => $class->return,
                    'return_supplier' => $class->return_supplier ?? 0,
                    'use' => $class->use,
                    'move' => $class->move,
                    'transfer' => $class->transfer,
                    'adjust' => $class->adjust,
                    'depreciation' => $class->depreciation,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($classesToInsert)) {
                foreach (array_chunk($classesToInsert, 100) as $chunk) {
                    DB::table('addrbook_classes')->insert($chunk);
                }
            }

            // Fetch and Insert Stats
            $stats = DB::connection('core_legacy')
                ->table('customerstat')
                ->whereIn('customer_id', $customerIds)
                ->get();

            $statsToInsert = [];
            foreach ($stats as $stat) {
                $createdAt = ($stat->created_at === '0000-00-00 00:00:00' || ! $stat->created_at) ? now() : $stat->created_at;
                $updatedAt = ($stat->updated_at === '0000-00-00 00:00:00' || ! $stat->updated_at) ? now() : $stat->updated_at;

                $statsToInsert[] = [
                    'addrbook_id' => $stat->customer_id,
                    'balance' => $stat->balance,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];
            }

            if (! empty($statsToInsert)) {
                foreach (array_chunk($statsToInsert, 100) as $chunk) {
                    DB::table('addrbook_stats')->insert($chunk);
                }
            }

            $bar->advance(count($customers));
        });

        $bar->finish();
        $this->command->newLine();
        $this->command->info('Import completed successfully.');
    }
}
