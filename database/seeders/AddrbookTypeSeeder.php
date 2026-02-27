<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AddrbookTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'Customer', 'slug' => 'customer', 'description' => 'Regular customer'],
            ['id' => 2, 'name' => 'Warehouse', 'slug' => 'warehouse', 'description' => 'Internal storage location'],
            ['id' => 3, 'name' => 'Bank', 'slug' => 'bank', 'description' => 'Bank account entity'],
            ['id' => 4, 'name' => 'Supplier', 'slug' => 'supplier', 'description' => 'Vendor or supplier'],
            ['id' => 5, 'name' => 'V. Warehouse', 'slug' => 'v-warehouse', 'description' => 'Virtual Warehouse'],
            ['id' => 6, 'name' => 'V. Account', 'slug' => 'v-account', 'description' => 'Virtual Account'],
            ['id' => 7, 'name' => 'Reseller', 'slug' => 'reseller', 'description' => 'Reseller partner'],
            ['id' => 8, 'name' => 'Account', 'slug' => 'account', 'description' => 'General Account'],
            ['id' => 99, 'name' => 'Other', 'slug' => 'other', 'description' => 'Other entities'],
        ];

        foreach ($types as $type) {
            \App\Models\AddrbookType::updateOrCreate(
                ['id' => $type['id']],
                $type
            );
        }
    }
}
