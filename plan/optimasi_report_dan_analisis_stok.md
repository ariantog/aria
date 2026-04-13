# Rencana Pemisahan Tabel Agregasi (Monthly Financials & Daily Inventory Detail)

Dokumen ini merinci pemisahan tabel agregasi dengan granulasi waktu yang berbeda dan pemecahan tipe transaksi untuk akurasi analisis yang lebih tajam.

---

## 1. Tabel Nett Cash: `monthly_account_summaries`
Melacak performa finansial per akun (Customer/Bank/Reseller) setiap bulan.

| Kolom | Tipe | Kegunaan |
| :--- | :--- | :--- |
| `year`, `month` | SmallInt, TinyInt | Periode |
| `addrbook_id` | Foreign Key | ID Akun/Bank/Customer |
| `cash_in`, `cash_out` | Decimal | Aliran kas masuk/keluar |
| `sell`, `return` | Decimal | Nilai transaksi penjualan/retur |

---

## 2. Tabel Cash Flow: `monthly_category_summaries`
Melacak aliran dana global berdasarkan kategori entitas setiap bulan.

| Kolom | Tipe | Kegunaan |
| :--- | :--- | :--- |
| `year`, `month` | SmallInt, TinyInt | Periode |
| `addrbook_type` | TinyInt | Kategori (Bank, Customer, dll) |
| `cash_in`, `cash_out` | Decimal | Kas global |
| `sell`, `buy` | Decimal | Penjualan/Pembelian global |
| `return`, `return_supplier` | Decimal | Retur global |

---

## 3. Tabel Analisis Stok Detail: `daily_inventory_summaries`
Tabel ini dipecah berdasarkan tipe transaksi agar sistem bisa membedakan mana barang yang "Laku" (Sell) dan mana yang hanya "Pindah" (Move).

| Kolom | Tipe | Kegunaan |
| :--- | :--- | :--- |
| `date` | Date | Tanggal |
| `warehouse_id` | Foreign Key | ID Gudang |
| `item_id` | Foreign Key | ID Produk |
| **--- Kolom Detail Pergerakan (Qty) ---** | | |
| `qty_sell` | Decimal | Keluar karena Penjualan (**Indikator Utama Fast Moving**) |
| `qty_buy` | Decimal | Masuk karena Pembelian |
| `qty_move_in` | Decimal | Masuk karena Transfer antar gudang |
| `qty_move_out` | Decimal | Keluar karena Transfer antar gudang |
| `qty_return_in` | Decimal | Masuk karena Retur Customer |
| `qty_return_out` | Decimal | Keluar karena Retur ke Supplier |
| `qty_adjust_in`, `qty_adjust_out` | Decimal | Perubahan karena Adjustment stok |
| `stock_on_hand` | Decimal | Saldo stok akhir hari tersebut (Snapshot) |

---

## 4. Intelijen Stok & Rekomendasi Pemindahan

Dengan data di atas, sistem akan memberikan rekomendasi yang jauh lebih cerdas:

1.  **Analisis Fast Moving (Murni Penjualan):**
    *   Sistem menghitung `SUM(qty_sell)`. Jika tinggi, barang tersebut benar-benar diminati pasar.
2.  **Identifikasi Dead Stock:**
    *   Jika `SUM(qty_sell)` = 0 selama 90 hari, tapi `stock_on_hand` > 0, maka itu adalah **Dead Stock**.
3.  **Logika Rekomendasi Pemindahan:**
    *   **Gudang A:** `qty_sell` tinggi, `stock_on_hand` menipis.
    *   **Gudang B:** `qty_sell` nol (Dead Stock), `stock_on_hand` menumpuk.
    *   **Action:** Sistem menyarankan `MOVE` dari Gudang B ke Gudang A.

---

## 5. Implementasi (Pipeline)

*   **`TransactionObserver`** akan memetakan tipe transaksi (1, 2, 7, 9, 15, 17, dsb) ke kolom yang sesuai di tabel summary.
*   Hal ini memastikan query laporan tidak perlu lagi menggunakan `CASE WHEN` atau `WHERE type = ...` pada tabel transaksi asli yang berat.

---

**Langkah Selanjutnya:**
Struktur ini sangat ideal untuk analisis mendalam. Apakah Anda ingin saya lanjut ke pembuatan migrasi untuk ketiga tabel ini?
