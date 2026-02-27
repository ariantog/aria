<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or find the 'superadmin' role
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        // 2. Ensure at least one location exists
        $locationId = DB::table('locations')->value('id');
        if (!$locationId) {
            $locationId = DB::table('locations')->insertGetId([
                'name' => 'Default Location',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Create the superadmin user
        $user = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@mail.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'location_id' => $locationId,
            ]
        );

        // 4. Assign the role
        $user->syncRoles([$role]);

        $this->command->info('SuperAdmin user created with username: superadmin and password: password');
    }
}
