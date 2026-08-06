# 02 — Tax Reporting Design (Indonesia)

What the business is obliged to report, what Aria Core would need to hold in order to produce it,
and the concrete report list.

> Regulatory references were checked in August 2026 and are cited inline. They are here so the
> design can be re-verified, not as tax advice — the numbers below should be confirmed with your
> konsultan pajak before anything is filed.

---

## 1. The obligations, and which ones Aria Core can serve

| Obligation | Frequency | Deadline | Can Aria Core produce it? |
|---|---|---|---|
| **SPT Masa PPN** (if PKP) | monthly | end of following month | Yes — as a recap that feeds Coretax. Needs faktur + NPWP data |
| **Faktur Pajak** | per transaction | upload by the 20th of the following month | Only if faktur numbers are stored. Optional — see §4 |
| **SPT Masa PPh 21** | monthly | the 20th | Partially — `gajis` exists, `borongans` needs worker tax identity |
| **SPT Masa PPh 23** | monthly | the 20th | Only if the penjahit are treated as businesses |
| **PPh 25 installment** | monthly | the 15th | Derived from last year's SPT, not from transaction data |
| **SPT Tahunan Badan (1771)** | annual | 30 April | Needs **neraca + laba rugi** — this is the main driver for `03` |
| **Daftar penyusutan aktiva tetap** | annual, attached to 1771 | with 1771 | Needs a fixed-asset register — not present today |
| **Daftar persediaan** | annual, attached to 1771 | with 1771 | Needs inventory valuation — see `04` |

### Decision 1 — PKP status

Everything in §2–§5 assumes the business is a **Pengusaha Kena Pajak**. The registration threshold
is omzet above Rp 4,8 miliar in a year; below it, registration is optional.

If **not PKP**: there is no output VAT obligation, `addrbooks.ppn` becomes purely a pricing flag,
and the PPN reports in §6 collapse into a single "sales that were invoiced with an 11% uplift"
report. Input VAT paid to suppliers simply becomes part of cost.

If **PKP**: §3 is the most important thing in this document.

> **Record the answer here:** PKP status = ______, sejak ______, NPWP ______, KLU ______

---

## 2. How PPN actually works right now, and why the app's model is too thin

Since 1 January 2025 the nominal rate under UU HPP is **12%**. For non-luxury goods and services
the government holds the effective burden at 11% by setting the DPP to a *nilai lain* of 11/12 of
the selling price (PMK 131/2024, consolidated and superseded by PMK 11/2025 effective retroactively
from 1 January 2025, amended by PMK 53/2025):

```
DPP Nilai Lain = 11/12 × Harga Jual
PPN            = 12% × DPP Nilai Lain
               = 11% × Harga Jual        (effective)
```

Only goods subject to PPnBM use the full 12% on the undiscounted DPP.

Garments are not barang mewah, so the effective rate is 11% and the app's arithmetic
(`total_before_tax × 0.11`) produces the **correct rupiah amount**.

**But the faktur and the SPT do not want the effective rate — they want the components.** A faktur
must show DPP Nilai Lain and a 12% rate. Aria Core currently stores only `tax_amount`, from which
you cannot reconstruct whether the base was the full price or the 11/12 nilai lain, nor at what
nominal rate. And because `ppn_rate` is a single non-dated setting, recomputing a 2024 period today
would apply today's rate.

### What to store instead of one number

Per taxable transaction, persist all of:

| Field | Example | Why |
|---|---|---|
| `taxable_base` | 1.000.000 | harga jual before the nilai-lain factor |
| `dpp` | 916.667 | 11/12 × taxable_base, what the faktur shows |
| `dpp_basis` | `nilai_lain_11_12` \| `harga_jual` | which rule applied |
| `ppn_rate` | 12.00 | nominal rate on the faktur |
| `ppn_amount` | 110.000 | 12% × dpp |
| `ppnbm_amount` | 0 | luxury surcharge, normally zero here |

`ppn_amount` stays identical to today's `tax_amount`, so nothing about pricing or balances changes
— this is purely making the components explicit and reproducible. The rates and the 11/12 factor
move into an effective-dated `tax_rates` table (`05`) so historical periods recompute correctly.

---

## 3. The `addrbooks.ppn` flag — the real problem to solve first

The flag today means: charge 11% and add it to the total, or don't.

Verified behaviour, two identical Rp 1.000.000 sales:

```
Sell   total 1,000,000   tax_amount 110,000   grand_total -1,110,000   (customer ppn = true)
Sell   total 1,000,000   tax_amount       0   grand_total -1,000,000   (customer ppn = false)
```

The second sale carries **no tax at all**. If the business is PKP, that is a penyerahan BKP and PPN
is owed on it regardless of what the customer wanted. The customer's preference changes who bears
the cost; it does not change whether the tax exists.

There are three commercially distinct situations that the single boolean cannot tell apart:

| Situation | What should happen | What happens now |
|---|---|---|
| Customer is PKP, wants a faktur | DPP = price, PPN added on top, faktur issued | Correct |
| Customer is non-PKP, doesn't want an invoice showing PPN | PPN is still owed; the quoted price should be treated as **tax-inclusive** and grossed down (DPP = price × 100/111) | PPN treated as zero — understates output VAT |
| Genuinely non-taxable | no PPN | Correct, but indistinguishable from the row above |

So the first deliverable is not a filing — it is **a report that sizes the gap**, in both readings:

- *If those sales were meant to be tax-inclusive*: undeclared PPN = `Σ non-PPN sales × 11/111`.
- *If they were treated as outside the system*: unreported omzet = `Σ non-PPN sales`, which also
  affects the PPh Badan peredaran usaha and the equalisasi in §6.7.

Once you have seen the number, the modelling decision follows. The proposal is to replace the
boolean's *meaning* (not the column, which stays for backwards compatibility) with an explicit
enum on the addrbook:

| `tax_treatment` | Meaning | DPP derivation |
|---|---|---|
| `exclusive` | PPN added on top, faktur issued | DPP = 11/12 × price; PPN = 12% × DPP |
| `inclusive` | quoted price already contains PPN, faktur still issued | DPP = price × 100/111 × 11/12; PPN = price × 11/111 |
| `none` | no PPN — non-taxable, or pre-PKP history | no tax rows |

Existing rows migrate as `ppn = true → exclusive`, `ppn = false → none`, so nothing changes until
you deliberately reclassify a customer to `inclusive`.

### Returns must reverse the tax

`Return` (15) and `ReturnSupplier` (17) always write `tax_amount = 0`. A customer return therefore
never reverses the PPN charged on the original sale, so output VAT is overstated by the tax content
of every return. In SPT terms a return is a **nota retur**, reported in the same lampiran as the
original faktur with a negative sign, and it needs a reference to the faktur it reverses. This
should be fixed at the same time as §2.

---

## 4. Faktur pajak — how much to build

Since 1 January 2025, faktur are created in **Coretax**. Under PER-11/PJ/2025 (effective 22 May
2025) the NSFP is **17 digits** — 2 digits kode transaksi + 2 digits kode status + 13 digits serial
— up from 16, and there are now 10 kode transaksi (01–10, code 10 added for non-standard rates).
Numbers are allocated by the system when the faktur is approved, rather than requested up front
through e-Nofa. The upload deadline moved to the 20th of the following month.

Relevant kode transaksi here:

| Kode | Use |
|---|---|
| `01` | ordinary sale to a non-Pemungut buyer — the normal case |
| `02` / `03` | buyer is a Pemungut (bendaharawan / other) |
| `04` | DPP Nilai Lain |
| `07` | PPN tidak dipungut (e.g. kawasan berikat) |
| `08` | PPN dibebaskan |

**Decision 5 — which model?**

**Option A (recommended for Phase 1): Coretax stays the system of record.** Aria Core produces a
recap per masa pajak matching lampiran A2 and B2, exported as CSV/XLSX for cross-checking against
Coretax. Aria Core stores only `faktur_number` and `faktur_date`, entered or imported after the
faktur exists. Small, useful immediately, no numbering logic to get wrong.

**Option B: Aria Core holds faktur data.** Adds allocation and status tracking (normal / pengganti
`01`, `02`, … / batal), a validity check on the 17-digit format, and generating the Coretax import
file. Substantially more work and more ways to be wrong; only worth it if manual faktur entry is
currently a real bottleneck.

Either way the transaction table needs the columns in `05`; Option A simply leaves most of them
nullable.

---

## 5. Counterparty tax identity — currently entirely absent

`addrbooks` has no tax fields at all. Lampiran A2 and B2 require, per faktur, the buyer's/seller's
NPWP or NIK, name and address. Without them no recap can be produced.

Minimum additions (detail in `05`):

| Field | Notes |
|---|---|
| `npwp` | 16 digits since 2024; for orang pribadi the NIK serves as NPWP |
| `nik` | for non-NPWP individual buyers |
| `nitku` | branch identifier, needed when the counterparty transacts through a branch |
| `is_pkp` | determines whether input VAT from this supplier is creditable |
| `tax_name` | legal name on the faktur, often different from the trading name in `name` |
| `tax_address` | `addrbooks.address` is a single free-text field and is frequently blank |
| `tax_treatment` | the enum from §3 |
| `default_kode_transaksi` | usually `01` |

The same applies to the business itself — there is nowhere to put your own NPWP, PKP date, KLU, or
the name of the faktur signatory. `05` proposes a `company_profile` settings group.

---

## 6. The reports to build

Ordered by value. Every one of them is monthly with a masa pajak filter, shows a grand total, and
exports to XLSX.

### 6.1 Rekap Pajak Keluaran (output VAT)

The A2 equivalent. One row per taxable `Sell`, plus `Return` rows as negative nota retur.

Columns: `tanggal`, `no_invoice`, `no_faktur`, `tanggal_faktur`, `kode_transaksi`, `nama_pembeli`,
`npwp_pembeli`, `dpp`, `ppn`, `ppnbm`, `keterangan`.

Grouped subtotals per customer, with a masa pajak total that must tie to the SPT induk.

### 6.2 Rekap Pajak Masukan (input VAT)

The B2 equivalent. One row per `Buy` from a PKP supplier, plus `ReturnSupplier` as negative.
Same columns, from the supplier side. Only suppliers with `is_pkp = true` are creditable — the rest
go to a "tidak dapat dikreditkan" section (B3 equivalent), where the VAT becomes part of cost.

### 6.3 Ringkasan SPT Masa PPN

The induk view: Pajak Keluaran − Pajak Masukan = kurang/lebih bayar, per masa, with a 12-month
strip so the year is visible at a glance and a carried-forward *lebih bayar* column.

### 6.4 Laporan Penjualan Non-PPN (the exposure report)

The one to build first, per §3. Sales to customers with `tax_treatment = none`, per month and per
customer, with two computed columns: PPN if the prices were tax-inclusive (`× 11/111`) and PPN if
tax were added on top (`× 11%`). This is a management report, not a filing.

### 6.5 Rekap PPh 21 — Gaji

From `gajis`, per masa: employee, bruto, PTKP status, DPP, PPh 21 withheld. Requires the payroll
tax fields in `05`. See §7.

### 6.6 Rekap PPh 21/23 — Borongan

From `borongans` / `borongan_details`, per masa and per penjahit: gross piece-rate paid, days
worked, average daily wage, withholding. Which regime applies is Decision 3 — see §7.

### 6.7 Equalisasi PPN vs Peredaran Usaha

The reconciliation DJP asks for in almost every audit: total omzet per the 12 SPT Masa PPN versus
peredaran usaha in the SPT Tahunan. Reconciling items are listed explicitly — non-PPN sales, returns,
timing differences between delivery and faktur date, and non-operating income. This report is the
best defence document the system can produce, and it is cheap once 6.1 and the laba rugi exist.

### 6.8 Coretax import file *(only if Option B in §4)*

---

## 7. PPh on the tailors — a real fork in the road

The penjahit are the largest labour cost, and the withholding treatment depends on the commercial
relationship. The system needs to know which, because the calculation and the reporting form differ.

### If they are `pegawai tidak tetap` (you employ them, paid per piece)

PPh 21 under PMK 168/2023, using **TER Harian**, applied to the *average daily* wage derived from
the borongan payment (the rules explicitly cover upah harian, mingguan, satuan and borongan):

| Average daily bruto | Withholding |
|---|---|
| ≤ Rp 450.000 | 0% |
| > Rp 450.000 to Rp 2.500.000 | 0,5% × bruto harian |
| > Rp 2.500.000 | Pasal 17 rate × (50% × bruto) |

Most piece-rate tailors will land in the 0% band, which is a useful thing to be able to *demonstrate*
rather than assume. To compute it the system needs days worked per borongan period — `borongans`
already has `from` and `to`, so the working-day count is derivable.

Data needed: worker NIK/NPWP on `workers`, and the day count. Reporting: SPT Masa PPh 21.

### If they are businesses invoicing you for jasa maklon

PPh 23 at **2% of gross** (4% if the provider has no NPWP), per PMK 141/2015 — jasa maklon is
explicitly listed. Gross excludes PPN and excludes reimbursed material where documented, which
matters here because you supply the fabric. A bukti potong is required per payment.

Note the nuance: jasa maklon performed by an **orang pribadi** falls under PPh 21 for bukan pegawai
(Pasal 17 × 50% × bruto), not PPh 23 — PMK 168/2023 Pasal 3(2). So "orang pribadi vs badan" is the
deciding question, not the word maklon.

> **Record the answer here:** penjahit treatment = ______

Whichever applies, the same structural change is needed: **borongan and gaji must post to the
ledger**, so the expense appears in laba rugi and the unpaid portion appears in neraca. `04` §6
covers this.

---

## 8. Other taxes worth capturing

| Tax | Trigger | What to add |
|---|---|---|
| **PPh 4(2) final** | rent of land/building, 10% | flag on the expense account; withhold on payment |
| **PPh 22** | purchase of certain goods | rare here; note only |
| **PPh 25** | monthly corporate installment | a settings value plus a payment record; not derivable from transactions |
| **PPh Badan** | annual, 22%; or PP 55/2022 final 0,5% of omzet if turnover ≤ Rp 4,8 M and eligible | needs laba rugi + koreksi fiskal |
| **Pajak Daerah** | signage, etc. | ordinary expense accounts; no special handling |

### Koreksi fiskal

SPT 1771 reconciles laba komersial to laba fiskal. The recurring adjustments in a business like this
are non-deductible expenses (sanksi pajak, sumbangan, entertainment without a daftar nominatif,
some natura) and the difference between commercial and fiscal depreciation, since fiscal rates are
fixed by Pasal 11 UU PPh (kelompok 1/2/3/4 at 4/8/16/20 years for harta berwujud bukan bangunan;
20 years permanent buildings, 10 non-permanent).

Cheapest useful implementation: a `fiscal_correction` flag plus a category on each expense account,
so the laba rugi can present a "koreksi fiskal positif" column without any separate data entry.

---

## 9. What Phase 1 actually is

Deliberately small, and deliverable without touching inventory valuation:

1. Tax identity fields on `addrbooks` and a company profile.
2. Effective-dated `tax_rates`, with `dpp`/`rate`/`ppn` persisted per transaction alongside the
   existing `tax_amount`.
3. Reports 6.1, 6.2, 6.3 and 6.4, with XLSX export.
4. Nota retur handling so returns reverse the tax.

Reports 6.5–6.7 depend on the payroll and statement work in `03` and `04`.
