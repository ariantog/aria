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

            $existing = ChecklistTemplate::withTrashed()
                ->where(function ($query) use ($templateDef, $roleId) {
                    $query->where('catalog_key', $templateDef['catalog_key'])
                        ->orWhere(function ($inner) use ($templateDef, $roleId) {
                            $inner->whereNull('catalog_key')
                                ->where('staff_role_id', $roleId)
                                ->where('frequency', $templateDef['frequency'])
                                ->where('title', $templateDef['title']);
                        });
                })
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    continue;
                }

                if (! $existing->catalog_key) {
                    $existing->update(['catalog_key' => $templateDef['catalog_key']]);
                }

                continue;
            }

            ChecklistTemplate::query()->create([
                'staff_role_id' => $roleId,
                'catalog_key' => $templateDef['catalog_key'],
                'frequency' => $templateDef['frequency'],
                'title' => $templateDef['title'],
                'description' => $templateDef['description'] ?? null,
                'route_name' => $templateDef['route_name'] ?? null,
                'route_query' => $templateDef['route_query'] ?? null,
                'sort_order' => $templateDef['sort_order'],
                'is_active' => true,
            ]);
        }

        $superadmin = User::find(User::SUPERADMIN_ID);
        if ($superadmin) {
            $superadmin->staffRoles()->sync([]);
        }
    }
}
