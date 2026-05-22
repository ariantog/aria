# Logika Pembuatan Kode Barang (SKU) Berdasarkan Tag

Dokumen ini menjelaskan bagaimana sistem Aria menghasilkan kode barang (`code`) dan nama barang (`name`) secara otomatis berdasarkan kombinasi **PCode** dan **Tag** yang dipilih.

## 1. Prinsip Dasar
Sistem menggunakan logika **Cross-Product** (Perkalian Silang) saat pembuatan barang. Jika pengguna memilih lebih dari satu **Tag Type** atau **Tag Size**, sistem akan menghasilkan item untuk setiap kombinasi yang memungkinkan.

- **Wajib Ada:** Setiap item (baik produk maupun aset) harus memiliki minimal satu **Tag Type** dan satu **Tag Size**.
- **Sanitasi PCode:** Karakter khusus seperti garis miring (`/`), strip (`-`), dan spasi akan dihapus atau dibersihkan saat membentuk string kode SKU.

---

## 2. Sistem Grup (ItemGroup)

Baik tipe **Item** maupun **Asset Lancar** menggunakan arsitektur pengelompokan yang sama di database.

- **Kunci Grup:** Grup diidentifikasi berdasarkan **PCode**. Semua item dengan PCode yang sama akan bernaung di bawah satu `group_id` yang sama.
- **Fungsi Grup:** Digunakan untuk menyimpan data global yang berlaku bagi seluruh variasi, seperti:
    - Deskripsi (Description 1 & 2) dan Alias.
    - Foto Produk/Aset (Gambar biasanya dikelola pada level grup).
- **Perbedaan Pengisian Kolom:**
    - **Item (Produk):** Sistem memecah PCode (misal: `CX12345/01`) ke kolom `master` (`CX12345`) dan `variant` (`01`).
    - **Asset Lancar:** Kolom `master` dan `variant` biasanya dikosongkan karena format PCode aset lebih fleksibel.

---

## 3. Logika Tipe: ITEM (Produk Jadi)

Digunakan untuk barang jadi yang siap dijual. Kode dihasilkan dengan menyertakan identitas kategori di depan.

### Rumus SKU (Code)
> **[Kode Tag Type]** + **[PCode tanpa /]** + **[Kode Tag Size]**

### Rumus Nama (Name)
> **[Kode Tag Type]** + **[PCode]** + **[Nama Tag Size]**

**Keterangan:**
- **Tag Type (Cat 3):** Kodenya diletakkan sebagai awalan (prefix).
- **Tag Size (Cat 7):** Kodenya diletakkan sebagai akhiran (suffix).
- **Tag Warna (Cat 20):** Disimpan dalam data item tetapi **tidak** muncul dalam string kode SKU.
- **Tag Jahit:** Disimpan sebagai referensi tetapi **tidak** muncul dalam string kode SKU.

---

## 3. Logika Tipe: ASSET LANCAR (Aksesoris/Bahan)

Digunakan untuk material pendukung seperti kancing, benang, atau bahan kain. Fokus utama adalah pada identifikasi warna.

### Rumus SKU (Code)
> **[PCode Bersih]** + **[Kode Tag Size]** + **[Kode Tag Warna]**

### Rumus Nama (Name)
> **[PCode]** + **-** + **[Kode Tag Warna]** + **-** + **[Kode Tag Size]**

**Keterangan:**
- **Tag Type (Cat 3):** Tetap **wajib dipilih** sebagai pengelompokan (Genre/Kategori), namun kodenya **tidak dimasukkan** ke dalam string SKU otomatis.
- **Tag Size (Cat 7):** Kodenya diletakkan di tengah/akhir.
- **Tag Warna (Cat 20):** Kodenya diletakkan di bagian akhir SKU. Ini adalah pembeda utama dengan tipe Item biasa.

---

## 4. Contoh Kasus

| Data Input | Tipe: ITEM | Tipe: ASSET LANCAR |
| :--- | :--- | :--- |
| **PCode** | `CX12345/01` | `BOXING-01` |
| **Tag Type** | `AJD` (T-Shirt) | `BOXING` (Type aset lancar di tag) |
| **ALIAS** | `ESSENTIAL SHIRT` (nama) | `APEX BOXING GLOVE` (nama) |
| **Tag Size** | `L` (Large) | `10OZ` (10 oz) |
| **Tag Warna** | `RED` (Merah) | `WHITE` (White) |
| **Hasil SKU** | **`AJD CX12345/01 L`** | **`BOXING-01-WHITE-10OZ`** |
| **Hasil Nama**| `ESSENTIAL SHIRT - RED - L` | `APEX BOXING GLOVE - WHITE - 10OZ` |

---

## 5. Logika Brand (Merek) Otomatis
Sistem menentukan brand berdasarkan prefix dari PCode:
- Mengambil 2 karakter pertama (misal: `AB...` -> Brand A).
- Khusus untuk prefix `CX`, sistem akan mengambil 3 karakter pertama.
- Jika tidak cocok dengan pola yang ada, akan dikategorikan sebagai `NO BRAND`.
