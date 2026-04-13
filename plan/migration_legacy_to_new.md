# Rencana Migrasi Data Legacy ke Database Project (v2)

Dokumen ini merinci langkah-langkah untuk melakukan migrasi data transaksi dan relasinya dari database legacy (`core`) ke database project saat ini dengan fokus pada konsistensi data dan laporan.

## 1. Lingkungan & Parameter Migrasi
- **Database Sumber (Legacy):**
    - Nama DB: `core`
    - Host: `localhost`
    - User: `root`
    - Password: (kosong)
- **Database Tujuan:** Database project saat ini.
- **Scope Data:**
    - Tabel Utama: `transactions`
    - Tabel Relasi: `transaction_details`, `items`, `addrbooks`, `users`.
    - Rentang Waktu: Mulai dari tahun **2024** (berdasarkan kolom `date`).

## 2. Prinsip Utama & Konsistensi
- **User ID Konsistensi:** `user_id` pada transaksi harus tetap sama dengan yang ada di database legacy. 
    - *Langkah:* Pastikan tabel `users` di project ini sudah sinkron dengan database legacy sebelum migrasi transaksi dimulai. Jika ada `user_id` yang belum ada, harus di-import terlebih dahulu ke tabel `users`.
- **Integritas Laporan (Reporting):** Migrasi tidak hanya memindahkan header dan detail transaksi, tapi juga harus memicu pembaruan pada:
    - `addrbook_stats`: Saldo (balance) terkini untuk setiap kontak/warehouse.
    - `addrbook_dailies`: Laporan harian (buy, sell, return, dll) agar dashboard/report tetap akurat.
    - `warehouse_items`: Stok di level warehouse.
    - `items`: Stok global (kolom `qty`).

## 3. Tahapan Eksekusi

### Tahap 1: Sinkronisasi Data Master (Wajib)
1.  **Users:** Bandingkan `core.users` dan `project.users`. Pastikan semua ID user yang aktif di transaksi (2024+) sudah ada di database project dengan ID yang identik.
2.  **Addrbooks:** Pastikan semua kontak/warehouse yang terlibat dalam transaksi sudah termigrasi dengan ID yang sama.
3.  **Items:** Pastikan ID produk konsisten.

### Tahap 2: Persiapan Command & Koneksi
- Buat Artisan Command `migrate:legacy-transactions`.
- Register koneksi `legacy` secara dinamis.

### Tahap 3: Proses Migrasi Transaksi
Proses dilakukan secara kronologis (berdasarkan tanggal dan ID) untuk memastikan perhitungan saldo (balance) konsisten:
1.  **Hapus Data Existing (Opsional/Sesuai Instruksi):** Jika ingin benar-benar bersih, bersihkan data transaksi >= 2024 di target.
2.  **Import Header (`transactions`):**
    - Ambil data `core.transactions` WHERE `date >= '2024-01-01'`.
    - Simpan ke `project.transactions` dengan mempertahankan `id` aslinya (jika memungkinkan/tidak bentrok) atau menggunakan mapping.
    - **PENTING:** `user_id` harus sama persis dengan legacy.
3.  **Import Detail (`transaction_details`):**
    - Pindahkan semua item detail yang terkait dengan transaksi tahun 2024+.

### Tahap 4: Rekalkulasi Laporan & Stok (Post-Migration)
Karena data dipindahkan secara massal, kita perlu memicu logic report:
1.  **Reset Stats:** Kosongkan `addrbook_stats` dan `addrbook_dailies` (atau khusus untuk data 2024+).
2.  **Recalculate:** Jalankan fungsi `TransactionService::handleTransaction` atau script khusus yang melakukan iterasi pada transaksi yang baru di-import untuk:
    - Mengupdate `sender_balance` dan `receiver_balance` di tiap baris transaksi secara berurutan.
    - Mengupdate `addrbook_stats.balance`.
    - Mengisi `addrbook_dailies` berdasarkan tipe transaksi.
    - Mengupdate stok di `warehouse_items` dan `items.qty`.

## 4. Validasi Akhir
1.  **Row Count:** Jumlah transaksi di legacy (2024+) == Jumlah di project.
2.  **Financial Check:** `SUM(grand_total)` per tipe transaksi harus sama antara legacy dan project.
3.  **Sample Audit:** Ambil 1 user dan 1 contact, bandingkan riwayat transaksi dan saldo akhirnya.

---
**Status:** Plan v2 Siap. Menunggu instruksi untuk pembuatan command.
