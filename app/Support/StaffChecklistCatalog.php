<?php

namespace App\Support;

/**
 * Master catalog of operational staff roles and checklist templates for Aria Core.
 *
 * Nine roles cover the main job functions; each person may hold multiple roles via
 * staff_role_user. Templates are seeded once and assigned per user.
 */
class StaffChecklistCatalog
{
    /**
     * @return list<array{slug: string, name: string, description: string, sort_order: int}>
     */
    public static function roles(): array
    {
        return [
            ['slug' => 'pemilik', 'name' => 'Pemilik / Direktur', 'description' => 'Oversight operasional, keuangan, dan risiko.', 'sort_order' => 1],
            ['slug' => 'akuntansi', 'name' => 'Akuntansi & Keuangan', 'description' => 'Transaksi, tutup buku, laporan keuangan.', 'sort_order' => 2],
            ['slug' => 'penjualan_online', 'name' => 'Penjualan Online', 'description' => 'Jubelio, marketplace, order online.', 'sort_order' => 3],
            ['slug' => 'gudang', 'name' => 'Gudang & Persediaan', 'description' => 'Stok, restock, mutasi, arrangement.', 'sort_order' => 4],
            ['slug' => 'produksi', 'name' => 'Produksi & Borongan', 'description' => 'Potong, jahit, QC, borongan mingguan.', 'sort_order' => 5],
            ['slug' => 'sdm', 'name' => 'SDM & Payroll', 'description' => 'Karyawan, gaji bulanan, cuti.', 'sort_order' => 6],
            ['slug' => 'toko', 'name' => 'Toko / Kasir', 'description' => 'Penjualan toko, kas masuk/keluar harian.', 'sort_order' => 7],
            ['slug' => 'produk', 'name' => 'Produk & Desain', 'description' => 'Item, harga, tag, barcode.', 'sort_order' => 8],
            ['slug' => 'admin', 'name' => 'Admin & Data', 'description' => 'Kontak, user, cron, pengaturan sistem.', 'sort_order' => 9],
        ];
    }

    /**
     * @return list<array{role: string, frequency: string, title: string, description?: string, route_name?: string, sort_order: int}>
     */
    public static function templates(): array
    {
        $items = [];

        $add = function (string $role, string $frequency, string $title, int $sort, ?string $route = null, ?string $desc = null) use (&$items) {
            $items[] = [
                'role' => $role,
                'frequency' => $frequency,
                'title' => $title,
                'description' => $desc,
                'route_name' => $route,
                'sort_order' => $sort,
            ];
        };

        // ── Pemilik ──
        $add('pemilik', 'daily', 'Cek strip kesehatan sistem di dashboard (Jubelio, queue, cron)', 1, 'dashboard');
        $add('pemilik', 'daily', 'Review transaksi besar / cash out hari ini', 2, 'transactions.index');
        $add('pemilik', 'weekly', 'Review laba rugi minggu berjalan', 1, 'reports.laba-rugi');
        $add('pemilik', 'weekly', 'Follow up piutang & hutang aging', 2, 'reports.receivables');
        $add('pemilik', 'biweekly', 'Rapat singkat tim: produksi, gudang, online', 1);
        $add('pemilik', 'monthly', 'Review neraca & arus kas bulan lalu', 1, 'reports.neraca');
        $add('pemilik', 'monthly', 'Setujui tutup buku bulan berjalan', 2);

        // ── Akuntansi ──
        $add('akuntansi', 'daily', 'Posting cash in / cash out hari ini', 1, 'transactions.cash.create', 'type=cash-in');
        $add('akuntansi', 'daily', 'Cocokkan saldo bank vs transaksi', 2, 'transactions.index');
        $add('akuntansi', 'daily', 'Follow up transaksi pending / error', 3, 'transactions.index');
        $add('akuntansi', 'weekly', 'Reconcile piutang pelanggan', 1, 'reports.receivables');
        $add('akuntansi', 'weekly', 'Reconcile hutang supplier', 2, 'reports.payables');
        $add('akuntansi', 'biweekly', 'Review PPN & PPh final periode berjalan', 1, 'reports.tax.ppn');
        $add('akuntansi', 'monthly', 'Jalankan / review penyusutan asset tetap', 1, 'assettetap.index');
        $add('akuntansi', 'monthly', 'Proses gaji bulanan & transfer bank', 2, 'gaji.index');
        $add('akuntansi', 'monthly', 'Tutup buku & set tanggal cutoff', 3);

        // ── Penjualan online ──
        $add('penjualan_online', 'daily', 'Import / sync order Jubelio', 1, 'jubelio.get-orders.index');
        $add('penjualan_online', 'daily', 'Selesaikan order pending & error SKU', 2, 'jubelio.index');
        $add('penjualan_online', 'daily', 'Proses cancellation / return Jubelio', 3, 'jubelio.returns.index');
        $add('penjualan_online', 'weekly', 'Jalankan stock check Jubelio vs Aria', 1, 'jubelio-stock-checks.index');
        $add('penjualan_online', 'weekly', 'Review performa SKU online', 2, 'items.index');
        $add('penjualan_online', 'biweekly', 'Update stok display di marketplace', 1, 'jubelio.index');
        $add('penjualan_online', 'monthly', 'Review iklan Shopee & biaya ads', 1, 'shopee-ads.index');

        // ── Gudang ──
        $add('gudang', 'daily', 'Cek urgent restock di dashboard', 1, 'restock.index');
        $add('gudang', 'daily', 'Tindak lanjuti stock alert (sold out di satu gudang)', 2, 'stock-notifications.index');
        $add('gudang', 'daily', 'Terima barang buy / update stok gudang', 3, 'transactions.create', 'type=buy');
        $add('gudang', 'weekly', 'Update restock sheet & flag urgent', 1, 'restock.index');
        $add('gudang', 'weekly', 'Review inventory health report', 2, 'reports.inventory-health');
        $add('gudang', 'biweekly', 'Mutasi stok antar gudang bila perlu', 1, 'transactions.create', 'type=move');
        $add('gudang', 'monthly', 'Jalankan warehouse arrangement refresh', 1, 'reports.warehouse-arrangement');

        // ── Produksi ──
        $add('produksi', 'daily', 'Cek job in production di dashboard', 1, 'produksi.index');
        $add('produksi', 'daily', 'Update status potong / jahit / QC', 2, 'produksi.index');
        $add('produksi', 'weekly', 'Generate borongan mingguan per jahit', 1, 'borongan.index');
        $add('produksi', 'weekly', 'Review backlog produksi', 2, 'produksi.index');
        $add('produksi', 'biweekly', 'Abonemen gaji mingguan borongan ke payroll', 1, 'borongan.index');
        $add('produksi', 'monthly', 'Review output vs target produksi', 1, 'produksi.index');

        // ── SDM ──
        $add('sdm', 'daily', 'Catat cuti / izin karyawan hari ini', 1, 'karyawan.index');
        $add('sdm', 'daily', 'Follow up telat / absen (input ke gaji nanti)', 2, 'karyawan.index');
        $add('sdm', 'weekly', 'Review daftar karyawan & rekening bank', 1, 'karyawan.index');
        $add('sdm', 'biweekly', 'Reminder cuti tahunan yang akan habis', 1, 'karyawan.index');
        $add('sdm', 'monthly', 'Buat / review gaji bulanan semua karyawan', 1, 'gaji.index');
        $add('sdm', 'monthly', 'Finalisasi potongan telat, izin, lembur', 2, 'gaji.index');

        // ── Toko ──
        $add('toko', 'daily', 'Input penjualan toko (sell)', 1, 'transactions.create', 'type=sell');
        $add('toko', 'daily', 'Setor kas / cash in dari toko', 2, 'transactions.cash.create', 'type=cash-in');
        $add('toko', 'daily', 'Cek stok display toko', 3, 'items.index');
        $add('toko', 'weekly', 'Reconcile kas toko vs transaksi', 1, 'transactions.index');
        $add('toko', 'monthly', 'Review diskon & promo toko', 1);

        // ── Produk ──
        $add('produk', 'daily', 'Update item baru / revisi harga bila ada', 1, 'items.index');
        $add('produk', 'weekly', 'Review item tanpa stok / tanpa barcode', 1, 'items.index');
        $add('produk', 'weekly', 'Cek stat item & performa penjualan', 2, 'items.index');
        $add('produk', 'biweekly', 'Koordinasi restock dengan gudang untuk SKU kritis', 2, 'restock.index');
        $add('produk', 'biweekly', 'Review tag, size, dan grouping produk', 1, 'items.index');
        $add('produk', 'monthly', 'Audit master item vs fisik', 1, 'items.index');

        // ── Admin ──
        $add('admin', 'daily', 'Cek queue & cron disabled di dashboard', 1, 'scheduled-tasks.index');
        $add('admin', 'daily', 'Monitor user aktif & permission issue', 2, 'users.index');
        $add('admin', 'weekly', 'Backup review: cron berjalan normal', 1, 'scheduled-tasks.index');
        $add('admin', 'weekly', 'Update addrbook kontak baru', 2, 'addrbook.type.index', 'type=customer');
        $add('admin', 'biweekly', 'Review pengaturan sistem (HR, restock, produksi)', 1, 'system-settings.index');
        $add('admin', 'monthly', 'Audit role & permission matrix', 1, 'roles.index');
        $add('admin', 'monthly', 'Review data retention / archive', 2, 'archive.index');

        return $items;
    }
}
