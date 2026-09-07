# Panduan Warehouse Arrangement

Panduan ini menjelaskan cara menggunakan fitur **Warehouse Arrangement** di Aria Core untuk merencanakan pemindahan stok antar gudang ke gudang tujuan (misalnya toko flagship atau gudang fulfillment).

---

## Apa itu Warehouse Arrangement?

Warehouse Arrangement membantu staf gudang/inventory:

- Melihat **SKU yang kurang** di gudang tujuan, berdasarkan **permintaan penjualan** (sell/return) dalam periode tertentu.
- Membandingkan stok tujuan dengan **dua gudang sumber** sekaligus.
- Memilih ukuran yang perlu dipindah, lalu **membuat draft transaksi Move** (pindah gudang) — mirip alur Restock.

Fitur ini **tidak memindahkan stok otomatis**. Setelah draft dibuat, Anda tetap harus meninjau dan **menyimpan transaksi Move** seperti biasa.

Data permintaan diambil dari statistik penjualan bulanan (`warehouse_item_monthly_stats`). Cache susunan gudang diperbarui lewat tombol rebuild atau cron harian.

---

## Siapa yang bisa mengakses?

Menu: **Reports → Warehouse Arrangement** (`/reports/warehouse-arrangement`).

Izin Spatie: **`report-warehouse-arrangement`**. Superadmin (user id 1) selalu bisa akses.

Untuk mengatur izin, minta admin menambahkan permission tersebut ke role pengguna yang bertanggung jawab atas restock/pemindahan stok.

---

## Persiapan (wajib sebelum pertama kali dipakai)

### 1. Tentukan gudang tujuan

Gudang tujuan adalah lokasi yang **menerima** stok (contoh: toko Surabaya, gudang online).

1. Buka **Address Book → Warehouse** (atau edit kontak gudang yang sudah ada).
2. Aktifkan toggle **Arrangement destination**.
3. Centang **Source warehouses** — gudang-gudang tempat sistem **mencari stok cadangan** untuk dipindah.
4. Simpan.

Catatan:

- Hanya gudang dengan **Arrangement destination** aktif yang muncul di dropdown Destination pada laporan.
- Centang minimal **satu** gudang sumber. Tanpa sumber, tidak ada saran pemindahan.
- Gudang sumber tidak perlu toggle Arrangement destination; yang penting dicentang di gudang tujuan.

### 2. Pastikan data penjualan sudah ada

Stat permintaan dibangun dari transaksi **Sell** dan **Return** yang sudah tercatat. Jika gudang tujuan baru atau stat belum pernah diisi:

1. Buka halaman Warehouse Arrangement.
2. Pilih gudang tujuan.
3. Klik **Rebuild stats & refresh** (lihat bagian di bawah).

Cron harian juga menjalankan sinkronisasi cache (`app:sync-warehouse-arrangement`), tetapi stat bulanan perlu rebuild manual jika belum pernah ada.

---

## Membuka halaman

1. Login ke Aria.
2. Sidebar → **Reports** → **Warehouse Arrangement**.
3. Jika muncul pesan *“No destination warehouses enabled”*, aktifkan **Arrangement destination** pada minimal satu gudang (lihat Persiapan).

---

## Bagian atas halaman

### Destination & Demand window

| Kontrol | Fungsi |
|--------|--------|
| **Destination** | Gudang tujuan yang akan diisi stoknya. |
| **Demand window** | Periode permintaan: **30 / 90 / 180 / 365 hari** terakhir. Default 365 hari. |
| **Apply** | Terapkan pilihan destination dan periode. |

Di bawah filter, sistem menampilkan kapan cache terakhir disinkronkan. Jika data **lebih dari 1 hari** tanpa refresh, muncul peringatan *stale* — gunakan **Rebuild stats & refresh** atau tunggu cron harian.

### Rebuild stats & refresh

Tombol ini menjalankan proses dua fase:

1. **Rebuild monthly stats** — menghitung ulang stat penjualan per SKU di gudang tujuan (±300 SKU per menit).
2. **Refresh arrangement cache** — memperbarui daftar kandidat pemindahan dan snapshot per pcode.

**Tips:**

- Biarkan halaman **tetap terbuka** saat rebuild berjalan; halaman akan memproses batch otomatis (~300 SKU setiap 15 detik).
- Jangan memulai rebuild kedua untuk gudang yang sama sebelum yang pertama selesai.
- Gunakan **Cancel rebuild** jika perlu menghentikan proses.

### Export Excel

Mengunduh semua saran pemindahan untuk kombinasi **destination + mode + demand window** saat ini ke file `.xlsx`. Berguna untuk review offline atau dibagikan ke tim gudang.

---

## Mode tampilan (View)

Sticky bar di bawah filter memiliki dua mode:

### Demand (default)

Menampilkan **SKU yang stoknya nol di tujuan** tetapi punya **permintaan penjualan** dalam periode Demand window yang dipilih, dan ada stok di salah satu gudang sumber.

Contoh: Size M terjual 3 unit dalam 90 hari terakhir, stok tujuan 0, stok gudang pusat 5 → muncul sebagai kandidat.

### Complete family

Menampilkan **keluarga warna (pcode)** yang **kelengkapan ukurannya di bawah 75%** di gudang tujuan — meskipun permintaan per SKU kecil, selama ada stok di sumber.

Berguna untuk melengkapi **grade ukuran** (misalnya hanya S dan L yang ada, M/XL kosong) agar display toko rapi.

Badge di setiap kartu pcode menampilkan misalnya `3/5 sizes (60%)`.

---

## Memilih gudang sumber untuk perbandingan

Di sticky bar:

| Kontrol | Fungsi |
|--------|--------|
| **Warehouse 1** | Gudang sumber pertama (kolom biru muda). Default: gudang dengan **match SKU terbanyak**. |
| **Warehouse 2** | Gudang sumber kedua (kolom ungu muda). Opsional — pilih **— None —** jika cukup satu sumber. |
| **Search pcode** | Filter cepat berdasarkan pcode, contoh `CX90028-02`. |

Dropdown menampilkan jumlah SKU yang cocok, misalnya `Gudang Pusat · 42 SKUs`.

Label **Best sources** menampilkan dua gudang dengan match terbanyak untuk referensi.

---

## Membaca kartu per pcode (warna)

Setiap kartu mewakili satu **pcode warna** (contoh `CX90028-02`).

Header kartu:

- **Pcode** — jika grup item sudah ada di Aria, pcode bisa diklik menuju halaman grup (`items-group/{id}`). Jika belum ada, muncul badge **No group**.
- **Nama produk** — hanya ditampilkan jika bukan pcode duplikat/legacy (mis. tidak menampilkan `CB00207/01` di samping `CB00207/02`).
- **Warna** (jika ada)
- **demand (365d)** — skor permintaan keluarga 365 hari (sorting prioritas)
- **X/Y sizes (Z%)** — berapa ukuran yang ada stok di tujuan vs total ukuran yang dikenali

### Tabel ukuran

Kolom:

| Warna latar | Arti |
|-------------|------|
| Hijau muda | Gudang **tujuan** (Destination) |
| Biru muda | **Warehouse 1** (sumber) |
| Ungu muda | **Warehouse 2** (sumber) |

Baris:

| Baris | Arti |
|-------|------|
| **Demand** | Jumlah permintaan SKU di tujuan untuk periode Demand window (hanya di kolom tujuan). |
| **Stock** | Stok fisik per ukuran. |

Di kolom sumber, sel yang **bisa dipindah** menampilkan:

- Checkbox + angka stok
- Input **qty** setelah dicentang (default mengikuti saran: minimal 1, maksimal stok sumber; mode Demand membatasi ke ceil(demand))

Sel yang hanya menampilkan angka tanpa checkbox = stok ada tetapi **bukan kandidat pemindahan** (misalnya SKU sudah ada di tujuan).

Tombol **Select all / Clear all** di header kartu memilih semua ukuran yang bisa dipindah untuk pcode tersebut.

---

## Membuat draft transaksi Move

1. Centang SKU di kolom **Warehouse 1** dan/atau **Warehouse 2** sesuai kebutuhan.
2. Sesuaikan **qty** jika perlu (tidak boleh melebihi stok sumber).
3. Klik tombol draft:
   - **`[Nama WH1] → [Nama Tujuan]`** — untuk item yang dicentang di kolom WH1 saja.
   - **`[Nama WH2] → [Nama Tujuan]`** — untuk item yang dicentang di kolom WH2 saja.

Satu draft **hanya satu pasangan** gudang asal → tujuan. Jika perlu memindah dari dua gudang berbeda, buat **dua draft terpisah**.

Setelah klik draft, Anda diarahkan ke **form transaksi Move** dengan sender/receiver dan baris item sudah terisi. Langkah selanjutnya:

1. Periksa tanggal, catatan, dan qty.
2. Simpan transaksi Move seperti biasa.

SKU yang sudah didraft **disembunyikan sementara** dari daftar arrangement (per sesi browser) agar tidak terduplikasi. Refresh halaman atau sesi baru akan menampilkannya lagi jika belum dipindah.

---

## Pagination & pencarian

- Daftar pcode dipaginasi (**50 pcode per halaman**).
- Gunakan **Previous / Next** di bawah daftar.
- **Search pcode** membatasi hasil tanpa mengubah mode atau periode.

---

## Troubleshooting

| Gejala | Kemungkinan penyebab | Solusi |
|--------|---------------------|--------|
| Tidak ada gudang di dropdown Destination | Belum ada gudang dengan Arrangement destination | Aktifkan toggle di Address Book → Warehouse |
| *No cached data yet* | Belum pernah rebuild | Centang Source warehouses, klik **Rebuild stats & refresh** |
| *No monthly sell stats* | Stat belum dibangun | Klik **Rebuild stats & refresh** |
| *No source warehouses configured* | Belum centang gudang sumber | Edit gudang tujuan → centang **Source warehouses** |
| Daftar kosong, stat sudah ada | Stok tujuan sudah lengkap atau tidak ada penjualan dalam periode | Coba periode lebih panjang (365 hari) atau mode **Complete family** |
| Kandidat ada tapi tidak ada checkbox | Tidak ada stok di gudang sumber yang dipilih | Pilih gudang sumber lain atau pastikan stok ada di WH1/WH2 |
| Data terasa usang | Cache > 1 hari | **Rebuild stats & refresh** atau tunggu cron harian |
| Rebuild macet | Halaman ditutup terlalu cepat | Buka kembali halaman untuk gudang yang sama; progress akan lanjut via queue/cron |

### Diagnostik cache (teks kecil di bawah filter)

Baris **Cache:** menampilkan ringkasan:

- `monthly stat rows` — baris stat penjualan bulanan
- `source warehouse(s)` — jumlah gudang sumber terkonfigurasi
- `candidate SKU(s)` — SKU yang kurang di tujuan
- `with source stock` — kandidat yang punya stok di sumber
- `pcode snapshot(s)` — jumlah keluarga warna tercatat

---

## Alur kerja harian (rekomendasi)

1. **Pagi:** Buka Warehouse Arrangement → pilih gudang tujuan → mode **Demand** → window **90 hari**.
2. Review kartu dengan demand tertinggi (urutan default).
3. Pilih ukuran dari gudang sumber terbaik → **draft Move** → simpan transaksi.
4. **Mingguan:** Jalankan mode **Complete family** untuk melengkapi grade ukuran di toko.
5. **Setelah perubahan besar** (promo, restock massal): klik **Rebuild stats & refresh** atau export Excel untuk audit.

---

## Istilah singkat

| Istilah (UI) | Bahasa Indonesia |
|--------------|------------------|
| Warehouse Arrangement | Susunan / pengaturan gudang |
| Arrangement destination | Gudang tujuan susunan |
| Source warehouses | Gudang sumber stok |
| Demand window | Periode permintaan |
| Demand | Permintaan (qty terjual neto dalam periode) |
| Complete family | Lengkapi keluarga ukuran |
| Rebuild stats & refresh | Hitung ulang stat & perbarui cache |
| Draft move | Buat draft pindah gudang |

---

## Referensi teknis (opsional)

| Item | Nilai |
|------|-------|
| Route | `/reports/warehouse-arrangement` |
| Permission | `report-warehouse-arrangement` |
| Threshold kelengkapan keluarga | 75% |
| Pagination | 50 pcode/halaman |
| Rebuild batch | ~300 SKU/menit |
| Cron cache | `app:sync-warehouse-arrangement` (harian) |
| Stat bulanan | `warehouse_item_monthly_stats` |

Untuk backfill stat historis lama, admin dapat menggunakan **System Settings → Warehouse Stats Backfill** (izin sama dengan Warehouse Arrangement).
