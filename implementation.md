# Implementation Spec — Web Builder Migration System (Accurate Online)

> Dokumen ini adalah spesifikasi teknis lengkap untuk membangun **web builder migration system** baru (via Lovable/AI builder).
> Sistem lama (`accurate-customizer`) menjadi referensi arsitektur, alur API, dan data modul yang sudah teruji.
> Tiga fitur flagship: **AOL-to-AOL**, **Modul-to-Modul**, **Transaksi-to-Transaksi**.

---

## 1. Visi & Tiga Fitur Flagship

Sistem baru memindahkan data antar-database **Accurate Online (AOL)** dengan 3 level granularitas — semuanya satu produk, satu kodebase:

| # | Fitur Flagship | Deskripsi | Cocok untuk |
|---|---|---|---|
| 1 | **AOL-to-AOL** | Migrasi **seluruh database** (semua modul) dari satu AOL ke AOL lain dalam satu *Migration Run* terencana & terpantau. | Setup/perpindahan/duplikasi database penuh |
| 2 | **Modul-to-Modul** | Migrasi **satu modul penuh** (mis. seluruh Sales Invoice) antar dua AOL dengan preview & penomoran. | Migrasi bertahap per modul |
| 3 | **Transaksi-to-Transaksi** | Migrasi **transaksi terpilih** (checkbox) dalam satu modul — fitur granular yang sudah ada. | Migrasi selektif / perbaikan data |

### Ketiga flagship = alur TERPISAH, bukan stepping

Ketiganya **bukan tahapan berurutan** dari satu proses (bukan wizard lintas flagship). Masing-masing adalah **alur berdiri sendiri** dengan halaman & entry point sendiri, dijalankan dari **Dashboard sesuai scope-nya masing-masing**:

| Flagship | Scope | Cara menjalankan (dari Dashboard) |
|---|---|---|
| **AOL-to-AOL** | seluruh modul × seluruh transaksi | Halaman **Migration Run**: buat run → konfigurasi per modul → jalankan → report |
| **Modul-to-Modul** | 1 modul × seluruh transaksinya | Halaman **Module**: pilih modul → preview → jalankan |
| **Transaksi-to-Transaksi** | 1 modul × transaksi terpilih | Halaman **Migrasi**: filter → pilih checkbox → modal konfirmasi → jalankan |

Ketiganya tetap berbagi **pipeline inti** (capture → proses → migrate), tapi eksekusinya **tidak pernah disambung-stepping** — setiap alur memulai dari titik awal scope-nya sendiri.

### Pipeline inti (dipakai oleh setiap alur)
```
[ CAPTURE ] ──► [ PROSES ] ──► [ MIGRATE ] ──► [ REPORT ]
 tarik data      filter/edit/     bulk-save +      summary, export,
 (detail-only)   preview nomor    mapping          retry
```

---

## 2. Arsitektur & Komponen

| Layer | Komponen | Fungsi |
|---|---|---|
| OAuth | AuthService | OAuth2 `access_token` / `refresh_token` ke Accurate |
| Database | DatabaseManager | Kelola daftar & koneksi database AOL (source + target) |
| Capture | `CaptureJob` (queue `capture`) | Tarik data dari source → simpan raw JSON ke `transactions` |
| Capture mapping | `CaptureNumberMappingJob` | Tarik riwayat mapping nomor dari Accurate |
| UI | Landing Page, Dashboard, Module Library, Migration Planner | Interaksi pengguna |
| Planner | Run/Plan builder | Susun *Migration Run* (AOL-to-AOL) atau seleksi (modul/transaksi) |
| Simulasi | `DryRunService` | Validasi & preview penuh tanpa menulis ke target |
| Preview | `previewNumbering` | Generate preview nomor + invoice terkait |
| Migrate | `MigrateJob` (queue `migrate`) | Transform + bulk-save ke target, chunk 100 |
| Transform | `DataCleaner` + `ModuleHandler` | Sesuaikan payload per modul |
| Mapping | `NumberMappingManager` | Simpan/ambil nomor lama→baru |
| Tracking | `SystemLog` / Run Status | Progress, status, error, report |

**Alur payload**:
1. `MigrateJob` menyiapkan data: injeksi nomor target (`number`/`no` + `_custom_number`), mapping invoice manual (`_custom_invoice_mappings`).
2. `DataCleaner::cleanDataItem()` membersihkan payload (strip `id`/internal, objek→skalar, dst).
3. Kirim `{"data": [...]}` ke `/api/{module}/bulk-save.do`.
4. Parsing respons `{s:bool, d:[{s,d}], e:[]}` → update status + simpan mapping lama→baru.

**Queue**: `QUEUE_LIST=capture,migrate,default`. Semua proses capture & migrate berjalan async dengan progress real-time.

---

## 3. Strategi Capture — Detail-Only (anti-ambiguity)

**Keputusan desain**: tidak ada lagi pilihan mode capture (`list_only` / `list_and_detail` / `detail_only`). Sistem **selalu capture via detail**:

1. Panggil `/api/{module}/list.do` **hanya untuk mendapatkan daftar ID** + field identitas ringan.
2. Untuk setiap ID, panggil `/api/{module}/detail.do` → simpan **raw JSON lengkap**.

Alasan:
- Menghilangkan ambiguitas pilihan mode di UI lama.
- Data yang tersimpan selalu **full detail** sehingga preview penomoran, mapping faktur, dan payload migrasi selalu lengkap.
- Kinerja diatur dengan concurrency terbatas (mis. 5–10 request paralel) dan pagination list (20–100/`page`).

Filter yang ditawarkan saat capture (tetap):
- Rentang tanggal `filter.transDate.op` (`BETWEEN`, `EQUAL`, `GREATER_EQUAL_THAN`, `LESS_EQUAL_THAN`) format `d/m/Y`.
- Atau filter `filter.lastUpdate` (`BETWEEN`/`GREATER_EQUAL_THAN`/`LESS_EQUAL_THAN`, format `d/m/Y H:i:s`).
- `filter.invoiceDp=true` untuk modul down-payment.

> Catatan implementasi: untuk master data tertentu (branch, currency, unit, dll.) respons list sudah memuat seluruh field; boleh memakai respons list langsung sebagai "detail" secara internal — ini optimasi, bukan mode yang dipilih pengguna.

---

## 4. Autentikasi & Scope OAuth

Login OAuth2 → pilih database → `access_token` + `accurate_database`. Scope (pattern `{module}_view {module}_save`):

```
bank_transfer_view bank_transfer_save bill_of_material_view bill_of_material_save
branch_view branch_save currency_view currency_save customer_save customer_view
customer_category_view customer_category_save customer_claim_view customer_claim_save
data_classification_view data_classification_save delivery_order_view delivery_order_save
department_view department_save employee_view employee_save
exchange_invoice_view exchange_invoice_save expense_accrual_view expense_accrual_save
fob_view fob_save glaccount_view glaccount_save item_view item_save
item_adjustment_view item_adjustment_save item_category_view item_category_save
item_transfer_view item_transfer_save job_order_view job_order_save
journal_voucher_view journal_voucher_save material_adjustment_view material_adjustment_save
material_slip_view material_slip_save finished_good_slip_view finished_good_slip_save
price_category_view price_category_save project_view project_save
purchase_invoice_view purchase_invoice_save purchase_order_save purchase_order_view
purchase_payment_view purchase_payment_save purchase_requisition_view purchase_requisition_save
purchase_return_view purchase_return_save receive_item_view receive_item_save
roll_over_view roll_over_save sales_invoice_view sales_invoice_save
sales_order_save sales_order_view sales_quotation_view sales_quotation_save
sales_receipt_view sales_receipt_save sales_return_view sales_return_save
shipment_view shipment_save stock_opname_order_view stock_opname_order_save
stock_opname_result_view stock_opname_result_save tax_view tax_save unit_view unit_save
vendor_view vendor_save vendor_category_view vendor_category_save
vendor_claim_view vendor_claim_save vendor_price_view vendor_price_save
warehouse_view warehouse_save work_order_view work_order_save
other_payment_view other_payment_save other_deposit_view other_deposit_save
```

> `down-payment-*` memakai scope induknya (`sales_invoice_*` / `purchase_invoice_*`).

---

## 5. Daftar Lengkap Modul (53 = 20 master + 33 transaksi)

Konvensi endpoint: list `/api/{slug}/list.do`, detail `/api/{slug}/detail.do`, bulk-save `/api/{slug}/bulk-save.do`.
Pengecualian: `expense-accrual` → `/api/expense/...`; `down-payment-sales-invoice` & `down-payment-purchase-invoice` → endpoint induk + `filter.invoiceDp=true`.

Kolom per modul: **identifier_field** (identitas tampilan), **number_field** (lookup mapping), **prefix** (penomoran kustom), **scope**.

### 5.1 Master Data (20) — nomor asli dipertahankan

| Slug | Nama | identifier_field | number_field | Scope |
|---|---|---|---|---|
| `branch` | Branch | `name` | `id` | branch_view / save |
| `currency` | Currency | `name` | `id` | currency_view / save |
| `customer` | Customer | `customerNo` | `customerNo` | customer_view / save |
| `customer-category` | Customer Category | `name` | `id` | customer_category_view / save |
| `data-classification` | Data Classification | `name` | `id` | data_classification_view / save |
| `department` | Department | `name` | `id` | department_view / save |
| `employee` | Employee | `employeeNo` | `no` | employee_view / save |
| `fob` | FOB | `name` | `id` | fob_view / save |
| `glaccount` | GL Account | `no` | `no` | glaccount_view / save |
| `item` | Item | `no` | `no` | item_view / save |
| `item-category` | Item Category | `name` | `id` | item_category_view / save |
| `bill-of-material` | Bill of Material | `name` | `id` | bill_of_material_view / save |
| `price-category` | Price Category | `name` | `id` | price_category_view / save |
| `project` | Project | `name` | `no` | project_view / save |
| `tax` | Tax | `name` | `no` | tax_view / save |
| `unit` | Unit | `name` | `id` | unit_view / save |
| `vendor` | Vendor | `vendorNo` | `vendorNo` | vendor_view / save |
| `vendor-category` | Vendor Category | `name` | `id` | vendor_category_view / save |
| `vendor-price` | Vendor Price | `name` | `id` | vendor_price_view / save |
| `warehouse` | Warehouse | `name` | `id` | warehouse_view / save |

### 5.2 Transaksi — Penjualan (8)

| Slug | Nama | number_field | Prefix | Scope |
|---|---|---|---|---|
| `sales-quotation` | Sales Quotation | `number` | `SQ` | sales_quotation_view / save |
| `sales-order` | Sales Order | `number` | `SO` | sales_order_view / save |
| `delivery-order` | Delivery Order | `number` | `DO` | delivery_order_view / save |
| `sales-invoice` | Sales Invoice | `charField1` | `SI` | sales_invoice_view / save |
| `down-payment-sales-invoice` | Down Payment Sales Invoice | `charField1` | `SI` | sales_invoice_view / save |
| `sales-return` | Sales Return | `number` | `SRT` | sales_return_view / save |
| `sales-receipt` | Sales Receipt | `charField1` | `SR` | sales_receipt_view / save |
| `shipment` | Shipment | `number` | default | shipment_view / save |

### 5.3 Transaksi — Pembelian (8)

| Slug | Nama | number_field | Prefix | Scope |
|---|---|---|---|---|
| `purchase-requisition` | Purchase Requisition | `number` | `PRQ` | purchase_requisition_view / save |
| `purchase-order` | Purchase Order | `number` | `PO` | purchase_order_view / save |
| `receive-item` | Receive Item | `charField1` | `RI` | receive_item_view / save |
| `purchase-invoice` | Purchase Invoice | `charField1` | `PI` | purchase_invoice_view / save |
| `down-payment-purchase-invoice` | Down Payment Purchase Invoice | `charField1` | `PI` | purchase_invoice_view / save |
| `purchase-return` | Purchase Return | `number` | `PRT` | purchase_return_view / save |
| `purchase-payment` | Purchase Payment | `charField1` | `PP` | purchase_payment_view / save |
| `vendor-claim` | Vendor Claim | `id` | default | vendor_claim_view / save |

### 5.4 Transaksi — Keuangan (7)

| Slug | Nama | number_field | Prefix | Scope |
|---|---|---|---|---|
| `journal-voucher` | Journal Voucher | `number` | `JV` | journal_voucher_view / save |
| `other-deposit` | Cash Penerimaan | `number` | `OD` | other_deposit_view / save |
| `other-payment` | Cash Pembayaran | `number` | `OP` | other_payment_view / save |
| `bank-transfer` | Bank Transfer | `number` | default | bank_transfer_view / save |
| `exchange-invoice` | Exchange Invoice | `number` | default | exchange_invoice_view / save |
| `expense-accrual` | Expense Accrual | `number` | default | expense_accrual_view / save |
| `roll-over` | Roll Over | `number` | default | roll_over_view / save |

### 5.5 Transaksi — Inventori & Produksi (10)

| Slug | Nama | number_field | Prefix | Scope |
|---|---|---|---|---|
| `item-transfer` | Item Transfer | `number` | default | item_transfer_view / save |
| `item-adjustment` | Item Adjustment | `number` | `IA` | item_adjustment_view / save |
| `stock-opname-order` | Stock Opname Order | `number` | default | stock_opname_order_view / save |
| `stock-opname-result` | Stock Opname Result | `number` | default | stock_opname_result_view / save |
| `work-order` | Work Order | `number` | default | work_order_view / save |
| `job-order` | Job Order | `number` | default | job_order_view / save |
| `material-adjustment` | Material Adjustment | `number` | default | material_adjustment_view / save |
| `material-slip` | Material Slip | `number` | default | material_slip_view / save |
| `finished-good-slip` | Finished Good Slip | `number` | default | finished_good_slip_view / save |
| `customer-claim` | Customer Claim | `number` | default | customer_claim_view / save |

> Prefix "default" = `strtoupper(substr(slug,0,2))`.

---

## 6. Sistem Penomoran Transaksi

3 mode penomoran (per modul/per run, bisa diatur di Planner):

1. **Nomor Asli (Lama)** — pakai nomor sumber.
2. **Nomor Kustom (kronologis)** — format `{PREFIX}.{YYYY.MM.DD}.{SEQ}` (contoh `SI.2026.07.12.001`); di-group per `module_slug+transDate`, sequence lanjut dari mapping terakhir di target.
3. **Auto (Accurate)** — field `number` tidak dikirim, Accurate yang membuat.

Mekanisme injeksi (`MigrateJob`):
- `data['number']=data['no']=nomorTarget` + `_custom_number=true` (modul transaksi non-master); master data tidak diubah.
- Simpan `_sourceNumber` = nomor asli agar mapping lama→baru akurat.
- Field `number` di-**strip** (biarkan Accurate auto-number) untuk modul: `delivery-order, purchase-invoice, purchase-order, purchase-payment, purchase-requisition, purchase-return, sales-invoice, sales-order, sales-quotation, sales-receipt, sales-return, receive-item, job-order, item-transfer` — kecuali sudah `_custom_number`.
- **`charField1`** dipakai sebagai field nomor kustom untuk: `sales-invoice`, `down-payment-sales-invoice`, `purchase-invoice`, `down-payment-purchase-invoice`, `sales-receipt`, `purchase-payment`, `receive-item`.

**Kasus khusus `invoiceNo` (detail faktur) untuk sales-receipt / purchase-payment**:
- `detailInvoice[].invoiceNo` = mapping manual pengguna (prioritas) → fallback `reset(mapping)` → resolve tabel mapping (`sales-invoice`, `purchase-invoice`, atau `down-payment-purchase-invoice` bila `invoice.invoiceDp=true`) → nomor lama.
- Sumber nomor invoice dari setting `sales_invoice_number_source` / `purchase_invoice_number_source` / `down_payment_purchase_invoice_number_source`.

---

## 7. Aturan Transformasi Payload (DataCleaner)

Aturan global:
- Hapus `_sourceNumber`, `_custom_number`, `_custom_invoice_mappings`, `id` (create), `vendorType`, `optLock`/`branchId` (sub-item), nilai `null`/`''`/`*Id`=0.
- NPWP: strip non-digit, pad 16.

| Modul/field | Transformasi |
|---|---|
| `customer` | → `customerNo` |
| `vendor` | → `vendorNo` |
| `bank` (sales-receipt, purchase-payment, other-payment, other-deposit) | → `bankNo` |
| `glaccount` | `parent`→`parentNo`, `currency`→`currencyCode` |
| `detailInvoice` (sales-receipt, purchase-payment) | `invoice`+`invoiceId` → `invoiceNo` |
| `invoice` (sales-return, purchase-return) | → `invoiceNumber` |
| `detailDownPayment` (sales-invoice, purchase-invoice) | → `invoiceNumber` |
| `detailItem` (banyak modul) | `item.no`→`itemNo`, `warehouse.name`→`warehouseName`, `itemUnit.name`→`itemUnitName`, `purchaseOrder.number`→`purchaseOrderNumber`, `salesOrder.number`→`salesOrderNumber`, `salesQuotation.number`→`salesQuotationNumber` |
| `detailExpense` (work-order, b.o.m, purchase-invoice, purchase-order) | `item.no`→`itemNo`, `account.no`→`accountNo`, `purchaseOrder.number`→`purchaseOrderNumber` |
| `detailAccount` (expense, other-payment, other-deposit) | `account.no`→`accountNo`, `department.name`→`departmentName`, `project.no`→`projectNo` |
| `detailJournalVoucher` | `glAccount.no`→`accountNo`, `vendor.vendorNo`→`vendorNo`, `customer.customerNo`→`customerNo`; buang amount < 1 |
| `detailOpenBalance` (item) | `warehouse.name`→`warehouseName` |
| `detailMaterial` / `detailExtraFinishGood` (work-order, b.o.m) | `item.no`→`itemNo` |
| `detailProcess` (work-order, b.o.m) | `processCategory.name`→`processCategoryName` |
| `detailSerialNumber` | `serialNumber.number`→`serialNumberNo` |
| `fromBank`/`toBank` (bank-transfer) | → `fromBankNo`/`toBankNo` |
| `fromItemTransfer` (item-transfer) | → `fromItemTransferNo` |
| `billOfMaterial` (work-order) | → `billOfMaterialNo` |
| `manufactureOrder` (work-order) | → `manufactureOrderNo` |
| `jobOrder` (roll-over) | → `jobOrderNumber` |
| `order` (stock-opname-result) | → `orderNumber` |
| `expensePayable` (expense) | → `expensePayableNo` |
| `itemCategory` (item) | → `itemCategoryName` |
| `warehouse` (stock-opname-order) | → `warehouseName` |
| `paymentTerm` | → `paymentTermName` |
| `tax` | resolve `salesTaxGlAccountId`/`purchaseTaxGlAccountId` → `salesTaxGlAccountNo`/`purchaseTaxGlAccountNo` via `/api/glaccount/detail.do` |
| `journal-voucher` | hapus `transactionType` |
| `purchase-invoice` detailItem | `receiveItem` → `receiveItemNumber` via mapping |

**Save satu-per-satu** (`/save.do`, bukan bulk-save): `warehouse`, `price-category`, `work-order`, `bill-of-material`.
**Chunking**: 100 item per request bulk-save; respons dipetakan per indeks.

---

## 8. Ketergantungan & Urutan Migrasi

Urutan default dalam *Migration Run* (AOL-to-AOL), juga menjadi panduan Modul-to-Modul:

1. **Konfigurasi & Master**: `glaccount` → `currency` → `tax` → `unit` → `fob` → `branch` → `department` → `employee` → `warehouse` → `project`.
2. **Kategori**: `customer-category` → `vendor-category` → `item-category` → `price-category` → `data-classification`.
3. **Master utama**: `customer` → `vendor` → `item` → `bill-of-material` → `vendor-price`.
4. **Penjualan**: `sales-quotation` → `sales-order` → `delivery-order` → `sales-invoice` (+DP) → `sales-receipt` → `sales-return` → `shipment`.
5. **Pembelian**: `purchase-requisition` → `purchase-order` → `receive-item` → `purchase-invoice` (+DP) → `purchase-payment` → `purchase-return` → `vendor-claim`.
6. **Keuangan**: `journal-voucher` (WAJIB didahulukan sebelum faktur/pembayaran) → `other-deposit` → `other-payment` → `bank-transfer` → `exchange-invoice` → `expense-accrual` → `roll-over`.
7. **Inventori & Produksi**: `item-transfer` → `item-adjustment` → `stock-opname-order` → `stock-opname-result` → `work-order` → `job-order` → `material-adjustment` → `material-slip` → `finished-good-slip` → `customer-claim`.

> ⚠️ **Penting**: `journal-voucher` mendahului faktur/pembayaran. UI menampilkan peringatan saat pengguna memilih `purchase-invoice`, `sales-invoice`, `sales-receipt`, `purchase-payment` jika JV belum dimigrasi.

---

## 9. Mode Simulasi (Dry-Run) & Validasi

**Keputusan desain**: setiap plan/run memiliki tombol **Simulasikan (Dry-Run)** yang menjalankan seluruh pipeline **tanpa menulis apa pun ke target**.

Apa yang dilakukan dry-run:
1. Ambil transaksi sesuai scope (semua modul / 1 modul / terpilih).
2. Jalankan transformasi + penomoran persis seperti migrate (murni komputasi lokal + lookup mapping).
3. **Validasi referensi**: cek apakah tiap referensi lintas-modul ter-resolve — `invoiceNo`, `purchaseOrderNumber`, `salesOrderNumber`, `receiveItemNumber`, `vendorNo`, `customerNo`, `itemNo`, `bankNo` — ada di mapping/nomor target.
4. Output laporan: jumlah OK, jumlah **warning** (referensi jatuh ke nomor lama / tidak ketemu mapping), jumlah **error** (field wajib kosong, format salah), contoh payload yang bermasalah.
5. Tampilkan juga estimasi waktu & jumlah request bulk-save.

**Mapping confidence indicator**: di preview modal & laporan dry-run, setiap `invoiceNo`/referensi diberi badge:
- 🟢 **Resolved** — nomor target ditemukan via mapping.
- 🟡 **Unmapped** — resolve jatuh ke nomor lama (berisiko "sukses tapi data tak ada").
- 🔴 **Broken** — referensi kosong / tidak bisa di-resolve sama sekali.

Dry-run **wajib disediakan** untuk mencegah kasus migrasi menandai sukses padahal data tidak terbentuk di target (bug kelas yang pernah terjadi di sistem lama).

**Pre-flight checklist per modul** (ditampilkan di Planner sebelum Migrate):
- Akun GL / bank yang dirujuk (`bankNo`) sudah ada di target (modul kas: sales-receipt, purchase-payment, other-payment, other-deposit).
- Faktur sumber yang dirujuk sudah punya mapping (sales-receipt/purchase-payment).
- Modul dependensi (glaccount, customer, vendor, item) sudah ter-capture & siap.
- Rentang tanggal & jumlah data wajar (mis. tidak 0).

---

## 10. Penanganan Duplikat & Konflik

Saat migrasi ke target yang **sudah berisi data** (re-migrasi / target tidak kosong), sistem harus menangani duplikat secara eksplisit:

| Opsi | Perilaku |
|---|---|
| **Skip** (default) | Nomor yang sudah ada di target dilewati, ditandai `skipped` (bukan failed) |
| **Force Create** | Kirim ulang apa adanya (sesuai nomor baru yang dipilih) |
| **Perbarui (Update)** | Jika nomor sama & data sudah ada, kirim dengan `id` target untuk update (mekanisme inject id) |

Deteksi duplikat:
- Cek mapping `transaction_number_mappings` (nomor lama→baru sudah terpakai di target).
- Cek nomor baru hasil penomoran kustom vs nomor yang sudah ada di mapping target.

Keputusan per-opsi **harus konfirmasi eksplisit di modal** (jangan pernah diam-diam skip/overwrite). Statistik duplikat (ditemukan / di-skip / di-update) masuk ke laporan run.

---

## 11. Incremental / Delta Sync

Setelah AOL-to-AOL awal, tambahkan mode **Sync Ulang (Delta)**:
- Hanya menarik & memigrasi transaksi yang **baru atau berubah** sejak run terakhir, memakai filter `filter.lastUpdate` (BETWEEN/GREATER_EQUAL_THAN) dengan watermark waktu run terakhir.
- Per modul, simpan `last_sync_at` / `last_update_watermark`.
- Data lama yang sudah sukses tidak diproses ulang (hemat waktu & kuota API).
- Delta sync adalah fitur penting untuk lingkungan produksi yang terus berjalan.

---

## 12. Rollback & Pembatalan

- **Cancel (Force Stop)**: hentikan run yang sedang berjalan (per chunk & per modul); status `cancelled`; transaksi yang belum diproses tetap `pending`.
- **Batalkan per-modul**: dalam run AOL-to-AOL, pengguna bisa membatalkan modul yang belum mulai / masih berjalan tanpa mengganggu modul lain.
- **Bersihkan mapping**: setelah cancel, mapping nomor yang tersimpan bisa dihapus untuk target agar run ulang bersih (penting karena penomoran kustom melanjutkan dari mapping terakhir).
- Catatan: penghapusan data dari Accurate target tidak selalu didukung API; **rollback di sini adalah pembatalan proses & pembersihan state lokal**, bukan penghapusan paksa data target — tampilkan ini dengan jelas di UI.

---

## 13. Template & Import/Export Plan

- **Simpan Plan sebagai Template**: konfigurasi run (modul aktif, urutan, penomoran per modul, opsi duplikat, dll.) disimpan sebagai JSON preset.
- **Export / Import**: unduh template (JSON) & unggah kembali di workspace lain — esensial untuk konsultan yang menangani banyak client.
- Template bawaan: **"Full Migration"** (semua modul, urutan default), **"Sales Full"**, **"Purchase Full"**, **"Finance Only"**.
- Versi template & validasi saat import (modul tak dikenal → warning).

---

## 14. Scheduling & Notifikasi

- **Jadwalkan run**: mulai run otomatis pada waktu tertentu (migrasi malam / akhir pekan). Status `scheduled → running → done`.
- **Notifikasi**: saat run selesai / gagal → email &/atau webhook (Slack). Berisi ringkasan sukses/gagal + link ke report.
- Integrasi dengan queue scheduler (mis. Laravel `schedule` / cron).

---

## 15. Multi-Tenant & Roles

- **Workspace per client**: semua data (database AOL, transaksi, runs, templates) ter-isolasi per workspace.
- **Roles**: 
  - *Admin* — kelola workspace, user, semua runs.
  - *Operator* — capture & migrate, tidak bisa hapus/ubah konfigurasi global.
  - *Viewer* — lihat dashboard & report saja.
- Otentikasi pengguna app (email/password) terpisah dari OAuth Accurate (kredensial Accurate milik workspace).
- Audit log per user action (siapa menjalankan run, mengubah template, dsb.).

---

## 16. Laporan & Export

- **Report per run**: ringkasan (total, sukses, failed, skipped, warning) + breakdown per modul.
- **Export CSV/Excel**: daftar transaksi yang dimigrasi (nomor lama, nomor baru, tanggal, status, error).
- **Diff report** (untuk delta sync): yang baru / berubah / gagal sejak run terakhir.
- **Error report**: grup error paling umum + transaksi terdampak (untuk debugging cepat).

---

## 17. Keamanan

1. **JANGAN log payload mentah** — proyek lama menulis full data transaksi ke log (`SAVING_TO_ACCURATE`). Di sistem baru, log API hanya berisi metadata ter-masking (module, id, nomor, status, HTTP status, durasi) — tanpa isi detail/angka transaksi.
2. **Enkripsi token at rest** — `access_token`/`refresh_token` dienkripsi (mis. Laravel `Crypt` / envelope encryption); jangan pernah dikirim ke frontend setelah login.
3. **Rate limiting & backoff** — hormati batas API Accurate: concurrency terbatas, retry eksponensial (1s→2s→4s→…→cap), jitter, max retries, circuit breaker. Token invalid → hentikan run + notifikasi login ulang.
4. **RBAC** — akses berdasar role (§15).
5. **Audit trail** — log aksi pengguna & perubahan konfigurasi.
6. **Data minimization** — data transaksi hanya disimpan selama masa migrasi (sesuai `data_retention_days`); penyimpanan sementara yang bisa dibersihkan.

---

## 18. Konfigurasi (Settings)

| Key | Nilai | Default | Fungsi |
|---|---|---|---|
| `sales_invoice_number_source` | `mapping_table` \| `transaction_number_mappings` | `mapping_table` | Sumber `invoiceNo` sales-receipt & sales-return |
| `purchase_invoice_number_source` | `mapping_table` \| `transaction_number_mappings` | `mapping_table` | Referensi faktur pembelian |
| `down_payment_purchase_invoice_number_source` | `mapping_table` \| `transaction_number_mappings` | `mapping_table` | Nomor DP purchase invoice |
| `receive_item_number_source` | `mapping_table` \| `transaction_number_mappings` | `mapping_table` | Nomor receive-item |
| `data_retention_days` | integer | - | Retensi data transaksi |
| `capture_concurrency` | integer | 5–10 | Paralel request detail saat capture |
| `bulk_chunk_size` | integer | 100 | Ukuran chunk bulk-save |
| `retry_max_attempts` | integer | - | Maks. retry per request API |

---

## 19. Tabel Pemetaan Nomor

| Tabel | Isi |
|---|---|
| `transaction_number_mappings` | `accurate_database_id`, `module_slug`, `old_number`, `new_number` (mekanisme utama) |
| `sales_invoice_mapping_number` | mapping sales invoice lama→baru |
| `purchase_invoice_mapping_number` | mapping purchase invoice lama→baru |
| `down_payment_purchase_invoice_mapping_number` | mapping DP purchase invoice |
| `receive_item_mapping_number` | mapping receive-item |

---

## 20. Detail API Accurate

**List** (`GET /api/{module}/list.do`): pagination `sp.page`, `sp.pageSize`; filter `transDate`/`lastUpdate` (lihat §3); `filter.invoiceDp`.
**Detail** (`GET /api/{module}/detail.do`): param `id`.
**Bulk Save** (`POST /api/{module}/bulk-save.do`):
```json
{ "data": [ { "...payload..." } ] }
```
Respons:
```json
{ "s": true, "d": [ { "s": true, "d": { "id": 123, "number": "..." } } ], "e": [] }
```
Status per transaksi: `pending → success | failed | skipped | cancelled`. Token kedaluwarsa → job `failed` + pesan "Sesi Accurate habis / token tidak valid". Ada auto-resume & retry.

---

## 21. Halaman Aplikasi — Dashboard & Navigasi

### 21.1 App Shell (layout umum)

Sidebar kiri (collapse-able di layar kecil):
- **Dashboard**
- **Migration Runs** (daftar semua run + status)
- **Module Library** (grid 53 modul, capture)
- **Migrasi** (halaman transaksi-to-transaksi & modul-to-modul)
- **Log / Monitoring**
- **Template** (preset plan)
- **Pengaturan** (settings, workspace, users)

Header atas:
- Workspace switcher (multi-tenant) + indikator **database sumber aktif** (nama DB + status token).
- Tombol **Migrate Baru** (mulai run cepat) — dropdown 3 flagship.
- Toggle dark mode, tombol notifikasi, menu user.

Navigasi utama dibuat **berbasis role** (Admin/Operator/Viewer) — lihat §15.

### 21.2 Dashboard — Layout & Komponen

Dashboard adalah pusat kendali & ringkasan. Komposisi:

1. **Row KPI** (5 kartu, klik → navigasi ke halaman terkait):
   - **Database Terhubung** — jumlah AOL source+target aktif.
   - **Run Aktif / Berjalan** — run yang `running`/`scheduled`; kartu menampilkan progress ringkas.
   - **Total Transaksi Ter-capture** — dengan breakdown per modul (tooltip).
   - **Migrasi Sukses / Gagal / Skipped** — akumulasi status dari semua run.
   - **Modul Siap Migrasi** — `x / 53` modul yang sudah ter-capture.

2. **Quick Actions — 3 flagship terpisah** (kartu/button). Setiap flagship membuka **halaman & alur masing-masing** (bukan wizard bersama):
   - **Buat Migration Run** (AOL-to-AOL) → halaman Migration Run, scope seluruh database.
   - **Migrate Modul** (Modul-to-Modul) → halaman Module, scope 1 modul × semua transaksi.
   - **Migrate Transaksi** (Transaksi-to-Transaksi) → halaman Migrasi, scope transaksi terpilih.

3. **Chart row**:
   - **Tren Migrasi** — bar/line sukses vs gagal per periode (minggu/bulan; filter rentang tanggal).
   - **Status Transaksi** — donut pending/success/failed/skipped.
   - **Top 10 Modul** — horizontal bar berdasarkan jumlah transaksi ter-capture.

4. **Recent Migration Runs** (tabel):
   - Kolom: nama run, tipe flagship, source → target, status badge, progress bar, sukses/gagal/skipped, tanggal mulai/selesai, jadwal, aksi (lihat report, cancel, retry).
   - Run `running` di-update real-time (polling/SSE) & muncul di atas.

5. **Upcoming Scheduled Runs** — daftar run terjadwal (waktu, modul, target) + tombol cancel jadwal.

6. **Notifikasi** — feed: run selesai/gagal, token Accurate kedaluwarsa (minta login ulang), peringatan JV belum dimigrasi, hasil dry-run.

7. **Empty state** — saat belum ada data: ilustrasi + CTA "Hubungkan database pertama" / "Jalankan simulasi".

### 21.3 Interaksi & Sumber Data

- Klik KPI / chart segment → navigasi ke halaman yang relevan dengan filter terisi.
- Filter rentang tanggal di dashboard → semua chart & tabel mengikuti.
- Auto-poll saat ada run aktif (interval 2–5s, berhenti saat idle).
- Data KPI/chart di-agregasi dari: `accurate_databases`, `migration_runs`, `transactions`, `system_logs` — semua **per-workspace**.
- Dashboard **read-only** untuk role Viewer (tanpa tombol aksi).

### 21.4 Tiga Alur Terpisah (halaman per flagship)

Setiap flagship punya halaman sendiri dan masuk dari Dashboard (Quick Actions). Tidak ada stepping lintas flagship.

**Alur AOL-to-AOL — Halaman Migration Run**
1. **Buat Run**: pilih source & target AOL.
2. **Konfigurasi**: semua 53 modul terurut default (dependensi, §8), toggle aktif/nonaktif, penomoran per modul (Asli/Kustom/Auto), opsi duplikat, preview jumlah data per modul, tombol **Simulasikan**.
3. **Simulasi (Dry-Run)**: transformasi + validasi referensi tanpa menulis; output OK/warning/error + badge confidence 🟢/🟡/🔴 + estimasi waktu & jumlah request.
4. **Jalankan**: via queue, progress real-time per modul, cancel per-modul / force stop.
5. **Report**: ringkasan sukses/gagal/skipped, breakdown per modul, export CSV/Excel, retry, diff report.
6. **Template & Jadwal**: simpan/import/export plan; jadwalkan + notifikasi.

**Alur Modul-to-Modul — Halaman Module**
1. Pilih 1 modul (dari Module Library).
2. Capture modul (detail-only) bila belum ada datanya.
3. Tombol **Migrate Modul** → preview semua transaksi modul tsb + opsi penomoran + dry-run + opsi duplikat.
4. Modal konfirmasi (nomor lama→baru editable, badge confidence, mapping `invoiceNo` untuk sales-receipt/purchase-payment, suffix `-JU` untuk JV).
5. Jalankan via queue → report ringkas per modul.

**Alur Transaksi-to-Transaksi — Halaman Migrasi**
1. Pilih 1 modul → filter & pilih transaksi (checkbox).
2. Tombol **Migrate** → modal konfirmasi (preview nomor lama→baru, opsi penomoran, mapping `invoiceNo`, opsi duplikat, suffix `-JU`).
3. Warning JV untuk purchase-invoice/sales-invoice/sales-receipt/purchase-payment.
4. Jalankan via queue → report per transaksi.

---

## 22. Desain & UX

**Design system (modern, konsisten):**
- UI kit: Tailwind + komponen modern (shadcn/ui-style) — Cards, Modals, Steppers, Toasts, Skeleton, Empty states, Progress, Tabs, Command palette.
- Visual: warna aksen gradient **blue → indigo**, background netral (slate/zinc), glassmorphism pada header & modal, shadow lembut.
- Tipografi: Inter (UI) + font mono untuk nomor dokumen (`font-mono`).
- Mode gelap (dark mode) default + toggle.
- Responsive (mobile→desktop), tabel dengan virtualisasi/pagination.
- Mikro-interaksi: transisi modal, animasi progress, skeleton saat loading, optimistic update.
- Accessibility: kontras memadai, keyboard navigasi, label jelas.

**UX principles:**
- **Tiga alur flagship terpisah, bukan stepping** — tiap fitur punya halaman sendiri & dijalankan dari Dashboard sesuai scope-nya (§21.4). Tidak ada wizard yang menyambungkan ketiga fitur.
- **Stepper internal hanya pada alur AOL-to-AOL** (buat run → konfigurasi → jalankan → report). Alur Modul-to-Modul & Transaksi-to-Transaksi ringkas: pilih → preview → jalankan.
- **Preview sebelum aksi**: modal konfirmasi selalu menampilkan jumlah data, nomor lama→baru, badge mapping confidence (§9), dan warning sebelum kirim.
- **Dry-run satu klik** — tombol "Simulasikan" di setiap plan.
- **Progress real-time**: per-run, per-modul, per-transaksi (sukses/gagal/total) dengan polling; bisa cancel (force stop).
- **Error yang actionable**: tiap transaksi gagal menyimpan pesan error; ada tombol *Retry* untuk transaksi/modul gagal (Journal Voucher gagal → opsi suffix `-JU`).
- **Konsistensi ikon & terminologi** (Bahasa Indonesia untuk UI, istilah teknis tetap Inggris).
- Konfirmasi untuk aksi destruktif (hapus transaksi, clear all, force create, hapus mapping) — jangan pernah langsung eksekusi.

---

## 23. Landing Page

Landing page modern dengan section berikut (urutan):

1. **Navbar** — logo, nav (Fitur, Cara Kerja, Modul, Harga, FAQ), CTA "Mulai".
2. **Hero** — headline kuat, sub-headline, 2 CTA (mulai gratis / lihat demo), screenshot mockup dashboard.
3. **Social proof strip** — logo/angka (mis. 53 modul, ribuan transaksi, database aman).
4. **3 Fitur Flagship** — kartu AOL-to-AOL, Modul-to-Modul, Transaksi-to-Transaksi (ikon + deskripsi + "pelajari lebih lanjut").
5. **Cara Kerja** — 3 langkah umum produk: Hubungkan database → Pilih scope & atur penomoran → Jalankan & pantau (berlaku untuk ketiga alur terpisah).
6. **Coverage Modul** — grid 53 modul dikelompokkan per kategori dengan count (Master, Penjualan, Pembelian, Keuangan, Inventori).
7. **Keunggulan / Customization** — penomoran kustom, mapping faktur manual, **dry-run simulasi**, retry, monitoring.
8. **Keamanan & Kepercayaan** — OAuth resmi, data hanya untuk migrasi, tidak menyimpan kredensial, log aman.
9. **Use Case / Untuk Siapa** — tim akuntansi, konsultan migrasi, perusahaan multi-entitas.
10. **Testimoni** (opsional) — 2–3 kutipan.
11. **FAQ** — accordion (keamanan, biaya, durasi, modul didukung, dry-run).
12. **Pricing** — 2–3 tier (Starter / Professional / Enterprise) dengan fitur per tier; CTA.
13. **Interactive Demo** — mode simulasi/dry-run sebagai demo tanpa data asli.
14. **CTA banner** — ajakan mulai migrate.
15. **Footer** — links, copyright.

---

## 24. Fitur yang Wajib Ada di Builder

1. **Module Registry** deklaratif (53 modul) — lihat §5.
2. **3 flagship sebagai alur terpisah**: Migration Run (AOL-to-AOL), Modul-to-Modul, Transaksi-to-Transaksi — masing-masing halaman sendiri, bukan stepping (§21.4).
3. **Capture detail-only** (tanpa pilihan mode) + filter tanggal / lastUpdate / invoiceDp.
4. **Preview penomoran** (Asli / Kustom / Auto) + prefix per modul + sequence per tanggal.
5. **Mapping manual `invoiceNo`** untuk sales-receipt & purchase-payment.
6. **Filter list transaksi**: tanggal, customer_name, vendor_name, bank_name, new_number, detail_field_search, hanya duplikat.
7. **Dry-run / simulasi + mapping confidence badge + pre-flight checklist per modul** (§9).
8. **Penanganan duplikat** (skip / force-create / update) dengan konfirmasi (§10).
9. **Incremental / delta sync** memakai `lastUpdate` (§11).
10. **Rollback / cancel** run & per-modul + pembersihan mapping (§12).
11. **Template plan** + import/export JSON (§13).
12. **Scheduling & notifikasi** (email/webhook) (§14).
13. **Multi-tenant & RBAC** (Admin/Operator/Viewer) (§15).
14. **Report & export** CSV/Excel + diff report (§16).
15. **Keamanan**: log masking, enkripsi token, rate limiting & backoff (§17).
16. **Dashboard** lengkap sesuai §21 (KPI, chart, quick actions 3 flagship, recent runs, scheduled, notifikasi, empty states).
17. **Queue** (`capture`, `migrate`, `default`) + progress & cancel.
18. **Mapping nomor lama→baru** + setting sumber invoice number.
19. **Error per transaksi** + retry (dan suffix `-JU` untuk JV).
20. **Landing page** multi-section (§23) + design system modern (§22).
