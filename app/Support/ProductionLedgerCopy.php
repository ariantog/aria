<?php

namespace App\Support;

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingTaxAccount;
use Illuminate\Support\Facades\Schema;

/**
 * Production (Crystal) ledger copy, roles, and tax-history maps.
 *
 * IDs are live L10 customers.id values. Missing rows are skipped so the same
 * apply path is safe on a fresh SQLite bootstrap and on prod-shaped data.
 *
 * @phpstan-type CopyRow array{description: string, hint?: string}
 */
final class ProductionLedgerCopy
{
    /**
     * Soft-delete unused / catch-all / retired-PT-Core ledgers (plan §9).
     *
     * Entity tax ledgers (PPH/SPT/PPN per company) stay active — staff still
     * Cash Out to them. Reports attribute those ids via taxMaps().
     *
     * @return list<int>
     */
    public static function softDeleteIds(): array
    {
        return [
            817, 1644, 2731, // Gaji Harian, Plotter, Pendapatan FitBox
            891, 900, // vague catch-alls
            2805, 2806, 2808, 2809, // PT Core tax (entity retired)
        ];
    }

    /**
     * Live Cash Out destinations for setor pajak / PT Core leftovers.
     *
     * @return list<int>
     */
    public static function cashOutTaxLedgerIds(): array
    {
        return [
            2802, 2818,
            2106, 2797, 2849, 2861, 2862, 2863, 2865,
            2883, 2884, 2885, 2896, 2941, 2944,
        ];
    }

    /**
     * @return array<int, CopyRow>
     */
    public static function copy(): array
    {
        return [
            814 => [
                'description' => 'Gaji outsource, helper, jahit luar, finishing',
                'hint' => 'Payroll outsource / borongan luar. Bukan gaji mingguan produksi internal.',
            ],
            816 => [
                'description' => 'Gaji SPG, pembantu, guru, pesangon',
                'hint' => 'Gaji yang tidak masuk bulanan, mingguan, atau outsourcing.',
            ],
            822 => [
                'description' => 'Gaji tetap bulanan',
                'hint' => 'Gaji tetap bulanan (bukan gaji mingguan produksi).',
            ],
            823 => [
                'description' => 'Servis mesin jahit',
                'hint' => 'Perbaikan dan servis mesin jahit.',
            ],
            828 => [
                'description' => 'Pengeluaran personal',
                'hint' => 'Biaya personal — minimalkan; cek apakah seharusnya privee.',
            ],
            829 => [
                'description' => 'Konsultan dan jasa profesional',
                'hint' => 'Konsultan, legal, audit, jasa profesional.',
            ],
            830 => [
                'description' => 'Sewa HQ Sambisari',
                'hint' => 'Sewa HQ Sambisari — kantor pusat, bukan toko WTC/Citos.',
            ],
            832 => ['description' => 'Sponsor', 'hint' => 'Sponsorship event atau komunitas.'],
            833 => ['description' => 'Pameran', 'hint' => 'Booth, event, dan pameran.'],
            834 => ['description' => 'Katalog', 'hint' => 'Cetak katalog dan materi cetak marketing.'],
            835 => [
                'description' => 'Perjalanan marketing',
                'hint' => 'Perjalanan dinas marketing, bukan transport toko.',
            ],
            837 => ['description' => 'Model', 'hint' => 'Fee model untuk foto / campaign.'],
            838 => [
                'description' => 'Iklan umum',
                'hint' => 'Iklan offline / umum, bukan iklan marketplace.',
            ],
            839 => ['description' => 'Banner', 'hint' => 'Banner dan materi visual offline.'],
            840 => ['description' => 'HRD', 'hint' => 'Biaya HRD di luar gaji dan training.'],
            841 => ['description' => 'Telepon', 'hint' => 'Telepon kantor / pulsa.'],
            842 => ['description' => 'Air PDAM', 'hint' => 'Tagihan air PDAM (bukan toko).'],
            843 => ['description' => 'Listrik token', 'hint' => 'Token / listrik kantor atau gudang.'],
            844 => ['description' => 'Mess karyawan', 'hint' => 'Biaya mess / asrama karyawan.'],
            846 => ['description' => 'Fotokopi', 'hint' => 'Fotokopi dan cetak kantor.'],
            847 => ['description' => 'Perlengkapan kantor', 'hint' => 'Perlengkapan kantor yang tidak dijual kembali.'],
            848 => ['description' => 'Kontribusi', 'hint' => 'Iuran / kontribusi komunitas atau asosiasi.'],
            849 => ['description' => 'ATK', 'hint' => 'Alat tulis dan perlengkapan kantor.'],
            850 => ['description' => 'Biaya bank', 'hint' => 'Admin bank, transfer fee, materai rekening.'],
            851 => ['description' => 'Website', 'hint' => 'Hosting, domain, dan biaya website.'],
            852 => ['description' => 'Internet', 'hint' => 'Internet, telepon data, langganan jaringan.'],
            853 => ['description' => 'Asuransi kantor', 'hint' => 'Asuransi gedung / kantor.'],
            854 => ['description' => 'Parkir dan tol', 'hint' => 'Parkir dan tol logistik / operasional.'],
            855 => [
                'description' => 'Bensin dan transport',
                'hint' => 'Bensin, tol, transport logistik (bukan toko).',
            ],
            856 => [
                'description' => 'Pajak kendaraan',
                'hint' => 'Pajak STNK / kendaraan dinas.',
            ],
            857 => [
                'description' => 'SSP',
                'hint' => 'Setoran pajak generic (bukan PPh per entitas).',
            ],
            858 => [
                'description' => 'Ongkir',
                'hint' => 'Ongkos kirim HQ / umum, bukan biaya toko.',
            ],
            859 => ['description' => 'Perbaikan alat kantor', 'hint' => 'Servis printer, AC kantor, alat kantor.'],
            860 => ['description' => 'Perbaikan gedung', 'hint' => 'Perbaikan dan maintenance gedung HQ.'],
            861 => ['description' => 'Mesin jahit', 'hint' => 'Pembelian / overhaul mesin jahit.'],
            862 => ['description' => 'Servis kendaraan', 'hint' => 'Servis dan suku cadang kendaraan dinas.'],
            864 => ['description' => 'Peralatan listrik', 'hint' => 'Kabel, lampu, peralatan listrik (bukan toko).'],
            865 => [
                'description' => 'Privee / penarikan pemilik',
                'hint' => 'Penarikan pemilik — exclude dari laba rugi opex.',
            ],
            868 => ['description' => 'Penjualan aset lain', 'hint' => 'Hasil penjualan aset non-stok.'],
            869 => ['description' => 'Bunga bank', 'hint' => 'Pendapatan bunga rekening.'],
            870 => ['description' => 'Riset dan pengembangan', 'hint' => 'R&D produk; bukan iklan marketplace.'],
            871 => ['description' => 'Donasi', 'hint' => 'Sumbangan dan donasi. Minimalkan pemakaian.'],
            872 => ['description' => 'Entertainment', 'hint' => 'Jamuan / entertain. Minimalkan pemakaian.'],
            873 => ['description' => 'Perijinan', 'hint' => 'Izin usaha, NIB, perpanjangan legalitas.'],
            874 => ['description' => 'Pengobatan', 'hint' => 'Pengobatan karyawan di luar asuransi.'],
            875 => ['description' => 'Toilet tickets', 'hint' => 'Tiket toilet / iuran kebersihan kecil.'],
            876 => ['description' => 'Air minum', 'hint' => 'Galon / air minum kantor.'],
            877 => ['description' => 'Konsumsi', 'hint' => 'Konsumsi rapat / karyawan harian.'],
            878 => ['description' => 'Asuransi kesehatan', 'hint' => 'Asuransi / BPJS kesehatan di luar gaji.'],
            879 => [
                'description' => 'Penghapusan hutang',
                'hint' => 'Write-off hutang. Isi catatan untuk pihak dan alasan.',
            ],
            880 => [
                'description' => 'Penyesuaian umum',
                'hint' => 'Penyesuaian, write-off, koreksi saldo.',
            ],
            885 => ['description' => 'Permak', 'hint' => 'Permak / perbaikan barang jadi.'],
            886 => ['description' => 'Jual sisa kain perca', 'hint' => 'Pendapatan sisa kain / perca.'],
            896 => ['description' => 'Packing', 'hint' => 'Bahan packing umum, bukan biaya toko.'],
            897 => ['description' => 'LPG', 'hint' => 'Gas LPG dapur / produksi.'],
            898 => ['description' => 'Penalti dan sanksi', 'hint' => 'Denda, penalti, sanksi resmi.'],
            901 => [
                'description' => 'Promosi',
                'hint' => 'Promosi, sample marketing, bukan iklan marketplace.',
            ],
            904 => ['description' => 'CSR', 'hint' => 'CSR dan kegiatan sosial.'],
            905 => [
                'description' => 'Bonus, insentif, lembur, THR',
                'hint' => 'Bonus, insentif, lembur, dan THR.',
            ],
            947 => ['description' => 'Sampah', 'hint' => 'Retribusi / biaya sampah.'],
            995 => ['description' => 'Cashback reseller', 'hint' => 'Cashback untuk reseller.'],
            1168 => [
                'description' => 'PBB',
                'hint' => 'Pajak bumi dan bangunan.',
            ],
            1558 => [
                'description' => 'Bahan baku',
                'hint' => 'Pembelian bahan baku / material produksi.',
            ],
            1995 => ['description' => 'Print', 'hint' => 'Jasa print / cetak di luar katalog.'],
            2099 => [
                'description' => 'Biaya partner Metro',
                'hint' => 'Biaya partner Metro: sample, fixture, lampu, banner, display.',
            ],
            2149 => ['description' => 'Fotografi', 'hint' => 'Foto produk dan campaign.'],
            2178 => [
                'description' => 'Biaya partner Sogo',
                'hint' => 'Biaya partner Sogo: sample, fixture, display.',
            ],
            2182 => ['description' => 'Penjualan lain-lain', 'hint' => 'Penjualan tanpa stok / non-SKU.'],
            2234 => [
                'description' => 'Biaya channel Shopee',
                'hint' => 'Komisi, iklan, dan biaya platform Shopee.',
            ],
            2250 => [
                'description' => 'Marketing digital non-platform',
                'hint' => 'Social media, kolaborasi, influencer — bukan komisi marketplace.',
            ],
            2252 => [
                'description' => 'Pembulatan',
                'hint' => 'Pembulatan kasir / selisih kecil.',
            ],
            2273 => [
                'description' => 'Biaya channel Tokopedia',
                'hint' => 'Komisi dan biaya platform Tokopedia.',
            ],
            2364 => ['description' => 'Cukai', 'hint' => 'Cukai dan bea terkait barang.'],
            2405 => ['description' => 'Pajak bunga', 'hint' => 'Pajak atas bunga bank.'],
            2422 => ['description' => 'Perlengkapan komputer', 'hint' => 'Hardware / aksesoris komputer kantor.'],
            2424 => ['description' => 'Bunga kredit', 'hint' => 'Bunga pinjaman / kredit bank.'],
            2431 => ['description' => 'Aplikasi', 'hint' => 'Langganan software / aplikasi.'],
            2434 => ['description' => 'Komisi lain-lain', 'hint' => 'Komisi di luar channel utama.'],
            2596 => ['description' => 'Komisi web', 'hint' => 'Komisi penjualan website.'],
            2633 => [
                'description' => 'Biaya partner Central',
                'hint' => 'Biaya partner Central: sample, fixture, display.',
            ],
            2696 => [
                'description' => 'Gaji mingguan produksi',
                'hint' => 'Gaji mingguan jahit — biaya produksi aktual (bukan borongan).',
            ],
            2719 => ['description' => 'Biaya partner FitBox', 'hint' => 'Biaya channel / partner FitBox.'],
            2788 => [
                'description' => 'Biaya channel TikTok Shop',
                'hint' => 'Komisi, iklan, dan biaya platform TikTok Shop.',
            ],
            2795 => ['description' => 'Kebersihan', 'hint' => 'Cleaning service / kebersihan kantor.'],
            2796 => ['description' => 'Ongkir luar negeri', 'hint' => 'Pengiriman ekspor / luar negeri.'],
            2799 => [
                'description' => 'Perlengkapan produksi',
                'hint' => 'Aksesoris mesin, spare part, perlengkapan jahit.',
            ],
            2824 => ['description' => 'Live shopping', 'hint' => 'Biaya live shopping (bukan komisi platform).'],
            2832 => ['description' => 'Hutang BCA', 'hint' => 'Cicilan / bunga hutang BCA.'],
            2833 => ['description' => 'Hutang BRI', 'hint' => 'Cicilan / bunga hutang BRI.'],
            2835 => [
                'description' => 'Operasional lain-lain',
                'hint' => 'Pengeluaran yang tidak masuk kategori lain. Minimalkan pemakaian.',
            ],
            2841 => ['description' => 'Pajak sewa', 'hint' => 'PPh sewa / pajak sewa generic.'],
            2842 => [
                'description' => 'Biaya operasional toko Citos',
                'hint' => 'Biaya operasional toko Citos: sewa, utilitas, perlengkapan. Juga gudang pengiriman marketplace.',
            ],
            2846 => [
                'description' => 'Biaya produksi lain',
                'hint' => 'Biaya produksi yang tidak terukur per SKU.',
            ],
            2857 => ['description' => 'Kembalian', 'hint' => 'Kembalian kasir yang tidak diambil.'],
            2859 => ['description' => 'Paper.id', 'hint' => 'Langganan Paper.id / administrasi faktur.'],
            2881 => [
                'description' => 'Biaya channel Lazada',
                'hint' => 'Komisi dan biaya platform Lazada.',
            ],
            2887 => ['description' => 'Kartu kredit', 'hint' => 'Bunga / biaya kartu kredit perusahaan.'],
            2889 => [
                'description' => 'Biaya operasional toko WTC',
                'hint' => 'Biaya operasional toko WTC: sewa, transport, utilitas. Juga gudang pengiriman marketplace. Isi catatan untuk detail.',
            ],
            2899 => ['description' => 'Biaya channel BSD', 'hint' => 'Biaya channel BSD: sewa, rak, SPG, dll.'],
            2938 => [
                'description' => 'Transfer tidak diketahui',
                'hint' => 'TF/setor yang belum teridentifikasi. Investigasi lalu pindahkan.',
            ],
            2957 => ['description' => 'Biaya partner MUKU', 'hint' => 'Biaya channel / partner MUKU.'],
            2959 => [
                'description' => 'Sewa gedung HQ',
                'hint' => 'Sewa / biaya gedung HQ, bukan sewa toko.',
            ],
            2963 => ['description' => 'Biaya partner AF', 'hint' => 'Biaya channel / partner AF.'],
            2964 => ['description' => 'Biaya partner Prop', 'hint' => 'Biaya channel / partner Prop.'],
            2969 => ['description' => 'Komunitas Surabaya', 'hint' => 'Biaya komunitas SBY (bukan iklan marketplace).'],

            // Per-entity tax / SPT — stay active so Cash Out can pick them.
            2802 => [
                'description' => 'PPN (setor)',
                'hint' => 'Cash Out setor PPN. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2818 => [
                'description' => 'Pengeluaran PT CORE',
                'hint' => 'Cash Out sisa biaya PT Core (entitas sudah retired). Bukan akun setor pajak.',
            ],
            2106 => [
                'description' => 'PPh Crystal',
                'hint' => 'Cash Out setor PPh Crystal. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2797 => [
                'description' => 'SPT Pribadi',
                'hint' => 'Cash Out SPT pribadi. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2849 => [
                'description' => 'PPN PT Indosport',
                'hint' => 'Cash Out setor PPN Indosport. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2861 => [
                'description' => 'PPh Cipta',
                'hint' => 'Cash Out setor PPh Cipta. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2862 => [
                'description' => 'PPh Indosport',
                'hint' => 'Cash Out setor PPh Indosport. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2863 => [
                'description' => 'PPh PT Cakra',
                'hint' => 'Cash Out setor PPh PT Cakra. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2865 => [
                'description' => 'PPh Pribadi',
                'hint' => 'Cash Out setor PPh pribadi. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2883 => [
                'description' => 'SPT Crystal',
                'hint' => 'Cash Out SPT Crystal. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2884 => [
                'description' => 'SPT Cipta',
                'hint' => 'Cash Out SPT Cipta. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2885 => [
                'description' => 'SPT Indosport',
                'hint' => 'Cash Out SPT Indosport. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2896 => [
                'description' => 'PPh CV Cakra',
                'hint' => 'Cash Out setor PPh CV Cakra. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2941 => [
                'description' => 'PPh AGM',
                'hint' => 'Cash Out setor PPh AGM. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
            2944 => [
                'description' => 'PPh UAI',
                'hint' => 'Cash Out setor PPh UAI. Laporan pajak memakai akun ini + reporting_tax_accounts.',
            ],
        ];
    }

    /**
     * @return array<int, string> ledger id => ReportingLedgerRole value
     */
    public static function roles(): array
    {
        $marketplace = [
            2234, 2788, 2881, 2273, 2899, 2099, 2178, 2633, 2719, 2957, 2963, 2964, 2250,
        ];
        $tax = array_values(array_unique(array_merge(
            [857, 1168, 856, 2364, 2405, 2841],
            array_values(array_filter(
                self::cashOutTaxLedgerIds(),
                static fn (int $id): bool => $id !== 2818,
            )),
        )));
        $adjustment = [880, 2252, 2857, 879];

        $map = [
            1558 => ReportingLedgerRole::Material->value,
            2696 => ReportingLedgerRole::ProductionCost->value,
            2889 => ReportingLedgerRole::TokoCost->value,
            2842 => ReportingLedgerRole::TokoCost->value,
            865 => ReportingLedgerRole::Exclude->value,
        ];

        foreach ($marketplace as $id) {
            $map[$id] = ReportingLedgerRole::MarketplaceCost->value;
        }
        foreach ($tax as $id) {
            $map[$id] = ReportingLedgerRole::TaxPayment->value;
        }
        foreach ($adjustment as $id) {
            $map[$id] = ReportingLedgerRole::Adjustment->value;
        }

        return $map;
    }

    /**
     * Live Cash Out tax ledgers → reporting entity + tax type (plan §4.6 / §9E).
     *
     * These ids stay on the chart so staff can still Cash Out setor pajak.
     * 2802 (generic PPN) and 2818 (PT Core leftover) are not entity-mapped.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function taxMaps(): array
    {
        return [
            2106 => ['cv-crystal', 'pph'],
            2883 => ['cv-crystal', 'spt'],
            2861 => ['cv-cipta', 'pph'],
            2884 => ['cv-cipta', 'spt'],
            2849 => ['pt-indosport', 'ppn'],
            2885 => ['pt-indosport', 'spt'],
            2862 => ['pt-indosport', 'pph'],
            2863 => ['cv-cakra', 'pph'],
            2896 => ['cv-cakra', 'pph'],
            2941 => ['agm', 'pph'],
            2944 => ['uai', 'pph'],
            2865 => ['pribadi', 'pph'],
            2797 => ['pribadi', 'spt'],
        ];
    }

    /**
     * Re-activate entity tax ledgers if an earlier apply soft-deleted them.
     *
     * @param  (callable(string): void)|null  $log
     */
    public static function restoreCashOutTaxLedgers(bool $dry = false, ?callable $log = null): void
    {
        $log ??= static function (string $message): void {};

        if (! Schema::hasTable('customers')) {
            return;
        }

        foreach (self::cashOutTaxLedgerIds() as $id) {
            $ledger = Addrbook::onlyTrashed()->find($id);
            if (! $ledger || (int) $ledger->type !== Addrbook::TYPE_ACCOUNT) {
                continue;
            }

            $log("Restore cash-out tax ledger {$id} ({$ledger->name})");
            if (! $dry) {
                $ledger->restore();
            }
        }
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    public static function apply(bool $dry = false, ?callable $log = null): void
    {
        $log ??= static function (string $message): void {};

        if (! Schema::hasTable('customers')) {
            $log('Skip ledger copy: customers table missing.');

            return;
        }

        self::restoreCashOutTaxLedgers($dry, $log);

        $catalog = self::copy();
        $hasHint = Schema::hasColumn('customers', 'ledger_hint');

        foreach (Addrbook::account()->orderBy('id')->get() as $ledger) {
            $row = $catalog[$ledger->id] ?? self::rowFromTypicalName((string) $ledger->name);
            $description = $row['description'] ?? self::fallbackDescription((string) $ledger->name);
            $hint = $row['hint'] ?? null;

            $updates = [];
            if (blank($ledger->description) && filled($description)) {
                $updates['description'] = $description;
            }
            if ($hasHint && blank($ledger->ledger_hint) && filled($hint)) {
                $updates['ledger_hint'] = $hint;
            }

            if ($updates === []) {
                continue;
            }

            $log("Copy ledger {$ledger->id} ({$ledger->name}): ".implode(', ', array_keys($updates)));
            if (! $dry) {
                $ledger->update($updates);
            }
        }
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    public static function applyRoles(bool $dry = false, ?callable $log = null): void
    {
        $log ??= static function (string $message): void {};

        if (! Schema::hasTable('reporting_ledger_roles')) {
            $log('Skip ledger roles: reporting_ledger_roles table missing.');

            return;
        }

        foreach (self::roles() as $id => $role) {
            $ledger = Addrbook::query()->find($id);
            if (! $ledger || (int) $ledger->type !== Addrbook::TYPE_ACCOUNT) {
                continue;
            }

            $existing = ReportingLedgerRoleModel::query()->where('customer_id', $id)->first();
            if ($existing) {
                continue;
            }

            $log("Role {$id} ({$ledger->name}): {$role}");
            if (! $dry) {
                ReportingLedgerRoleModel::query()->updateOrCreate(
                    ['customer_id' => $id],
                    ['role' => $role],
                );
            }
        }
    }

    /**
     * @param  (callable(string): void)|null  $log
     */
    public static function applyTaxMaps(bool $dry = false, ?callable $log = null): void
    {
        $log ??= static function (string $message): void {};

        if (! Schema::hasTable('reporting_tax_accounts') || ! Schema::hasTable('reporting_entities')) {
            $log('Skip tax maps: reporting tables missing.');

            return;
        }

        foreach (self::taxMaps() as $ledgerId => [$entitySlug, $taxType]) {
            $entity = ReportingEntity::query()->where('slug', $entitySlug)->first();
            $ledger = Addrbook::withTrashed()->find($ledgerId);
            if (! $entity || ! $ledger) {
                continue;
            }

            $exists = ReportingTaxAccount::query()->where('legacy_ledger_id', $ledgerId)->exists();
            if ($exists) {
                continue;
            }

            $log("Tax map {$ledgerId} ({$ledger->name}) → {$entitySlug}/{$taxType}");
            if (! $dry) {
                ReportingTaxAccount::query()->updateOrCreate(
                    ['legacy_ledger_id' => $ledgerId],
                    ['reporting_entity_id' => $entity->id, 'tax_type' => $taxType],
                );
            }
        }
    }

    /**
     * @return CopyRow|null
     */
    public static function rowFromTypicalName(string $name): ?array
    {
        $typical = NewDomainChartOfAccounts::ledgerByName($name);
        if (! $typical) {
            return null;
        }

        return [
            'description' => $typical['description'],
            'hint' => $typical['hint'],
        ];
    }

    public static function fallbackDescription(string $name): string
    {
        $name = trim($name);

        return $name === '' ? 'Akun biaya' : $name;
    }
}
