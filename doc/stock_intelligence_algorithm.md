# Algoritma Stock Intelligence & Performance

Dokumen ini menjelaskan logika di balik fitur **Stock Intelligence** yang digunakan untuk mengukur kesehatan perputaran stok barang di setiap gudang.

## Parameter Utama (Settings)

Sistem menggunakan empat parameter utama yang dapat dikonfigurasi melalui menu *Settings* di halaman Stock Intelligence:

### 1. Max Days (Batas Penjualan Maksimal)
*   **Definisi:** Rentang waktu maksimal (dalam hari) sebuah barang dianggap masih "aktif".
*   **Fungsi:** Jika barang tidak terjual di suatu gudang melebihi jumlah hari ini, maka skor performanya akan menjadi **0** dan dikategorikan sebagai **Deadstock**.
*   **Contoh:** Jika diset `90`, maka barang yang terakhir laku 91 hari yang lalu dianggap stok mati.

### 2. Max Gap (Batas Selisih Antar Gudang)
*   **Definisi:** Batas maksimal ketertinggalan hari penjualan antara gudang saat ini dengan gudang terbaik (*best performing warehouse*).
*   **Fungsi:** Mengukur seberapa jauh perbedaan kecepatan laku barang tersebut di satu lokasi dibanding lokasi lainnya. Digunakan untuk menghitung nilai "relatif".
*   **Kegunaan:** Indikator utama untuk melakukan *rebalancing* (pemindahan stok).

### 3. Bobot Sale (Sale Weight) - Default: 0.8 (80%)
*   **Definisi:** Persentase pengaruh **kecepatan penjualan lokal** terhadap skor akhir.
*   **Fungsi:** Semakin besar bobot ini, sistem akan semakin mengutamakan data riil kapan terakhir kali barang itu laku di gudang tersebut (Performa Absolut).

### 4. Bobot Gap (Gap Weight) - Default: 0.2 (20%)
*   **Definisi:** Persentase pengaruh **perbandingan antar gudang** terhadap skor akhir.
*   **Fungsi:** Memberikan perspektif perbandingan. Jika barang macet di gudang A tapi sangat laris di gudang B, bobot ini membantu memberikan nilai bahwa barang tersebut sebenarnya "berpotensi" jika dipindahkan (Performa Relatif).

---

## Perhitungan Skor Performa

Skor dihitung dalam skala **0.0000 hingga 1.0000** menggunakan rumus bobot terintegrasi:

`Skor Akhir = (Nilai Gap * Bobot Gap) + (Nilai Sale * Bobot Sale)`

### Kategori Tingkat Performa (Performance Levels)

| Level | Kunci (Key) | Ambang Skor | Keterangan |
| :--- | :--- | :--- | :--- |
| **1. Elite** | `elite` | >= 0.90 | Barang sangat laris, baru saja laku, performa terbaik. |
| **2. Good** | `good` | >= 0.70 | Performa aktif dan sehat. |
| **3. Active** | `active` | >= 0.50 | Performa normal, masih rutin terjual. |
| **4. Lagging** | `lagging` | >= 0.30 | Penjualan melambat, mulai masuk fase waspada. |
| **5. Stagnant** | `stagnant` | < 0.30 | Sangat jarang laku, mendekati deadstock. |
| **6. Deadstock** | `deadstock` | 0.00 | Sudah melewati batas `Max Days` sejak penjualan terakhir. |
| **7. Critical** | `critical` | 0.00 | Barang belum pernah terjual sama sekali sejak pertama kali input. |

---

## Pemeliharaan Data (Maintenance)

Untuk memastikan skor selalu akurat berdasarkan transaksi terbaru, tersedia perintah khusus untuk sinkronisasi stok:

### Sinkronisasi Stok Saja (Direkomendasikan)
Perintah ini menghitung ulang stok global dan stok per gudang tanpa menyentuh data saldo keuangan.
```bash
php artisan inventory:recalculate
```

### Sinkronisasi Laporan Total
Perintah ini menghitung ulang seluruh statistik termasuk stok, saldo akun, dan laporan harian.
```bash
php artisan report:recalculate
```
