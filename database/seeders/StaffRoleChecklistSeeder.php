<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\StaffRole;
use App\Models\User;
use App\Support\StaffChecklistCatalog;
use Illuminate\Database\Seeder;

class StaffRoleChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $roleIdsBySlug = [];

        foreach (StaffChecklistCatalog::roles() as $roleDef) {
            $role = StaffRole::query()->updateOrCreate(
                ['slug' => $roleDef['slug']],
                [
                    'name' => $roleDef['name'],
                    'description' => $roleDef['description'],
                    'sort_order' => $roleDef['sort_order'],
                    'is_active' => true,
                ],
            );

            $roleIdsBySlug[$roleDef['slug']] = $role->id;
        }

        foreach (StaffChecklistCatalog::templates() as $templateDef) {
            $roleId = $roleIdsBySlug[$templateDef['role']] ?? null;
            if ($roleId === null) {
                continue;
            }

            ChecklistTemplate::query()->updateOrCreate(
                [
                    'staff_role_id' => $roleId,
                    'frequency' => $templateDef['frequency'],
                    'title' => $templateDef['title'],
                ],
                [
                    'description' => $templateDef['description'] ?? null,
                    'route_name' => $templateDef['route_name'] ?? null,
                    'sort_order' => $templateDef['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        $superadmin = User::find(User::SUPERADMIN_ID);
        if ($superadmin) {
            $superadmin->staffRoles()->sync(array_values($roleIdsBySlug));
        }
    }
}
