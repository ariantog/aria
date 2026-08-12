<?php

namespace App\Services;

use App\Support\SettingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsCleanupService
{
    /**
     * @return array{duplicate_rows: int, legacy_rows: int, unmanaged_rows: int, invoice_rows: int}
     */
    public function preview(): array
    {
        return [
            'duplicate_rows' => $this->duplicateRowCount(),
            'legacy_rows' => $this->legacyRowCount(),
            'unmanaged_rows' => $this->unmanagedRowCount(),
            'invoice_rows' => $this->deprecatedInvoiceRowCount(),
        ];
    }

    /**
     * @return array{duplicate_rows: int, legacy_rows: int, unmanaged_rows: int, invoice_rows: int}
     */
    public function run(bool $dryRun = false): array
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'slug')) {
            return [
                'duplicate_rows' => 0,
                'legacy_rows' => 0,
                'unmanaged_rows' => 0,
                'invoice_rows' => 0,
            ];
        }

        if ($dryRun) {
            return $this->preview();
        }

        $duplicateRows = $this->deduplicateBySlug();
        $legacyRows = $this->deleteLegacyRows();
        $invoiceRows = $this->deleteDeprecatedInvoiceRows();
        $unmanagedRows = $this->deleteUnmanagedRows();

        return [
            'duplicate_rows' => $duplicateRows,
            'legacy_rows' => $legacyRows,
            'unmanaged_rows' => $unmanagedRows,
            'invoice_rows' => $invoiceRows,
        ];
    }

    private function duplicateRowCount(): int
    {
        $count = 0;

        foreach ($this->duplicateSlugs() as $slug) {
            $count += max(0, DB::table('settings')->where('slug', $slug)->count() - 1);
        }

        return $count;
    }

    private function legacyRowCount(): int
    {
        return DB::table('settings')->whereIn('slug', SettingRegistry::LEGACY_SLUGS)->count();
    }

    private function unmanagedRowCount(): int
    {
        return DB::table('settings')
            ->whereNotIn('slug', $this->allowedSlugs())
            ->whereNotIn('slug', ['invoice_company_name', 'invoice_address', 'invoice_phone'])
            ->count();
    }

    private function deprecatedInvoiceRowCount(): int
    {
        return DB::table('settings')
            ->whereIn('slug', ['invoice_company_name', 'invoice_address', 'invoice_phone'])
            ->count();
    }

    /**
     * @return list<string>
     */
    private function duplicateSlugs()
    {
        return DB::table('settings')
            ->select('slug')
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allowedSlugs(): array
    {
        return array_merge(
            SettingRegistry::slugs(),
            SettingRegistry::SYSTEM_SLUGS,
        );
    }

    private function deduplicateBySlug(): int
    {
        $deleted = 0;

        foreach ($this->duplicateSlugs() as $slug) {
            $keepId = DB::table('settings')
                ->where('slug', $slug)
                ->orderByDesc('id')
                ->value('id');

            if ($keepId === null) {
                continue;
            }

            $deleted += DB::table('settings')
                ->where('slug', $slug)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        return $deleted;
    }

    private function deleteLegacyRows(): int
    {
        return DB::table('settings')
            ->whereIn('slug', SettingRegistry::LEGACY_SLUGS)
            ->delete();
    }

    private function deleteDeprecatedInvoiceRows(): int
    {
        return DB::table('settings')
            ->whereIn('slug', ['invoice_company_name', 'invoice_address', 'invoice_phone'])
            ->delete();
    }

    private function deleteUnmanagedRows(): int
    {
        $allowedSlugs = $this->allowedSlugs();

        if ($allowedSlugs === []) {
            return 0;
        }

        return DB::table('settings')
            ->whereNotIn('slug', $allowedSlugs)
            ->delete();
    }
}
