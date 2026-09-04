<?php

namespace Database\Seeders;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Support\NewDomainInstall;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Point new-domain settings at the placeholder addrbooks / typical ledgers.
 *
 * Only fills empty SettingRegistry keys so a re-run does not overwrite
 * values the operator already changed.
 */
class NewDomainSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! NewDomainInstall::allowsBaselineSeed()) {
            $this->command?->error('NewDomainSettingsSeeder refused: '.NewDomainInstall::refusalReason());

            return;
        }

        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'slug')) {
            return;
        }

        $gudang = Addrbook::query()->where('name', 'Gudang')->where('type', Addrbook::TYPE_WAREHOUSE)->first();
        $supplier = Addrbook::query()->where('name', 'Supplier')->where('type', Addrbook::TYPE_SUPPLIER)->first();
        $perawatan = Addrbook::query()->where('name', 'Biaya Perawatan')->where('type', Addrbook::TYPE_ACCOUNT)->first();
        $penyesuaian = Addrbook::query()->where('name', 'Penyesuaian Umum')->where('type', Addrbook::TYPE_ACCOUNT)->first();

        $this->fillIfEmpty('restock.default_supplier_id', $supplier?->id);
        $this->fillIfEmpty('restock.default_receiver_id', $gudang?->id);
        $this->fillIfEmpty('restock.default_warehouse_ids', $gudang ? [$gudang->id] : null);
        $this->fillIfEmpty('produksi.default_warehouse_id', $gudang?->id);
        $this->fillIfEmpty('asset_tetap.depreciation_expense_account_id', $perawatan?->id);
        $this->fillIfEmpty('asset_tetap.depreciation_contra_account_id', $penyesuaian?->id);
    }

    private function fillIfEmpty(string $slug, mixed $value): void
    {
        if ($value === null) {
            return;
        }

        $setting = Setting::query()->where('slug', $slug)->first();
        if (! $setting || filled($setting->value)) {
            return;
        }

        $setting->update(['value' => $value]);
    }
}
