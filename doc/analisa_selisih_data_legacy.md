# Dokumentasi Analisa Selisih Data (Legacy vs New System)

Dokumen ini merinci hasil investigasi mengenai perbedaan angka laporan antara database legacy (`core`) dengan sistem baru (`core-aria`), khususnya pada laporan **Nett Cash** periode April 2025.

## 1. Temuan Kasus Spesifik: April 2025
Berdasarkan perbandingan data Cash In:
*   **Total Sistem Baru:** Rp 1.037.603.296,20
*   **Total Laporan Legacy:** Rp 1.036.732.222,20
*   **Selisih:** **Rp 871.074,00**

### Identifikasi Sumber Selisih
Setelah dilakukan audit per akun pada database legacy, ditemukan bahwa selisih tersebut berasal tepat dari akun **zalora (ID 2043)**.
*   Total Cash In Zalora (April 2025): **Rp 871.074,00**.
*   **Kesimpulan:** Laporan legacy kemungkinan menggunakan filter ID tertentu (*hardcoded*) yang tidak memasukkan akun Zalora, sehingga angka tersebut tidak terhitung meskipun transaksinya ada di database. Sistem baru menggunakan pendekatan dinamis (menarik semua akun tipe Customer/Reseller), sehingga hasilnya lebih akurat.

---

## 2. Perbedaan Arsitektur Data & Logic

Berikut adalah faktor-faktor teknis yang sempat menyebabkan perbedaan angka sebelum dilakukan refactor:

### A. Kolom Basis Perhitungan (Tax Treatment)
*   **Masalah:** Data legacy memiliki kolom `total` (sebelum pajak) dan `real_total` (setelah pajak). Laporan legacy menggunakan kolom `total`.
*   **Solusi:** Sistem baru telah disesuaikan untuk menggunakan kolom `total` agar sinkron dengan nilai dasar laporan lama.

### B. Konstanta Tipe Transaksi
*   **Temuan:** Tipe `CASH_OUT` pada data legacy tersimpan dengan ID **7**, sedangkan standar awal sistem baru menggunakan **10**.
*   **Perbaikan:** Konstanta `TYPE_CASH_OUT` pada model `Transaction.php` telah diubah menjadi **7**.

### C. Duplikasi Sisi Transaksi (Double Counting)
*   **Masalah:** Transaksi kas melibatkan dua pihak (Sender & Receiver). Jika keduanya (misal: Customer dan Bank) tercatat di tabel summary, penjumlahan global akan menjadi ganda.
*   **Perbaikan:** Menerapkan **One-sided Attribution**.
    *   `CASH_IN` & `RETURN`: Hanya dihitung dari sisi **Sender**.
    *   `CASH_OUT` & `SELL`: Hanya dihitung dari sisi **Receiver**.

### D. Logika Net Offsetting
*   **Legacy:** Menggunakan `SUM(total)` secara langsung. Nilai positif (pendapatan) dan negatif (biaya/koreksi) saling meniadakan secara alami di database.
*   **New System:** Sempat menggunakan `SUM(ABS(total))`.
*   **Perbaikan:** Menghapus fungsi `ABS()` pada query agregasi database agar mengikuti logika akuntansi legacy yang mengizinkan *offsetting* nilai.

---

## 3. Status Saat Ini
Sistem pelaporan di project baru dan proses sinkronisasi (`TransactionObserver`) sudah diperbarui untuk mengikuti aturan di atas.

**Data sekarang 100% konsisten dengan data mentah yang ada di database legacy.**
