# Prompt untuk Lovable — Web Builder Migration System (Accurate Online)

> Salin seluruh teks di bawah ini ke Lovable (atau AI builder lain). Dapat dipakai bersama file `implementation.md` (spesifikasi teknis lengkap).

---

Kamu adalah senior full-stack engineer + product designer. Bangunkan aku **web application** modern untuk **migrasi data Accurate Online (AOL)** antar database, dengan **3 fitur flagship**, desain modern, UX yang matang, dan landing page yang lengkap. Ini adalah internal tool tapi harus tampil & terasa seperti produk SaaS profesional.

## Fitur Flagship (3)

1. **AOL-to-AOL** — migrasi seluruh database (semua 53 modul, berurutan sesuai dependensi) dari satu AOL ke AOL lain dalam satu **Migration Run** yang terencana & terpantau. Cocok untuk setup/perpindahan database penuh.
2. **Modul-to-Modul** — migrasi satu modul penuh antar dua AOL (mis. seluruh Sales Invoice) dengan preview & penomoran.
3. **Transaksi-to-Transaksi** — migrasi transaksi terpilih (checkbox) dalam satu modul. Fitur granular.

Ketiganya memakai pipeline yang sama, beda scope seleksi saja. Landing page harus menyorot ketiga flagship ini.

## Teknologi

- Backend: Laravel 10+ atau Node (Express/Nest). Referensi arsitektur: Laravel + queue.
- Frontend: React/Next atau Blade + Alpine.js + Tailwind CSS + komponen modern (shadcn/ui-style).
- Database: MySQL. Queue untuk capture & migrate (Laravel queue / BullMQ) dengan progress real-time (polling/SSE).
- Multi-tenant (workspace per client) + RBAC.

## Autentikasi & Scope

Dua lapis otentikasi:
1. **User app** — email/password per workspace, role Admin/Operator/Viewer.
2. **OAuth Accurate** — login OAuth2 → pilih database source & target; `access_token`/`refresh_token` **dienkripsi saat disimpan**, tidak pernah dikirim ke frontend setelah login. Jika kedaluwarsa saat proses → job `failed` + pesan "Sesi Accurate habis / token tidak valid, silakan login ulang".

Scope OAuth yang diminta (semua modul, `{module}_view {module}_save`):
`bank_transfer_view bank_transfer_save bill_of_material_view bill_of_material_save branch_view branch_save currency_view currency_save customer_save customer_view customer_category_view customer_category_save customer_claim_view customer_claim_save data_classification_view data_classification_save delivery_order_view delivery_order_save department_view department_save employee_view employee_save exchange_invoice_view exchange_invoice_save expense_accrual_view expense_accrual_save fob_view fob_save glaccount_view glaccount_save item_view item_save item_adjustment_view item_adjustment_save item_category_view item_category_save item_transfer_view item_transfer_save job_order_view job_order_save journal_voucher_view journal_voucher_save material_adjustment_view material_adjustment_save price_category_view price_category_save project_view project_save purchase_invoice_view purchase_invoice_save purchase_order_save purchase_order_view purchase_payment_view purchase_payment_save purchase_requisition_view purchase_requisition_save purchase_return_view purchase_return_save receive_item_view receive_item_save roll_over_view roll_over_save sales_invoice_view sales_invoice_save sales_order_save sales_order_view sales_quotation_view sales_quotation_save sales_receipt_view sales_receipt_save sales_return_view sales_return_save shipment_view shipment_save stock_opname_order_view stock_opname_order_save stock_opname_result_view stock_opname_result_save tax_view tax_save unit_view unit_save vendor_view vendor_save vendor_category_view vendor_category_save vendor_claim_view vendor_claim_save vendor_price_view vendor_price_save warehouse_view warehouse_save work_order_view work_order_save material_slip_view material_slip_save finished_good_slip_view finished_good_slip_save other_payment_view other_payment_save other_deposit_view other_deposit_save`

## Data Model

- `workspaces` + `users` (role: admin/operator/viewer) — multi-tenant.
- `accurate_databases`: db_id, db_name, alias, token terenkripsi.
- `modules`: module per database (slug, name, endpoint, type=master|transaction, is_active).
- `transactions`: module_id, accurate_database_id, transaction_no, data(JSON raw), status(pending|success|failed|skipped|cancelled), error_message, migrated_at.
- `migration_runs`: source_db, target_db, status(scheduled|running|cancelled|done|failed), progress, plan JSON, per-modul `last_update_watermark`.
- `migration_templates`: preset plan (JSON), export/import.
- `transaction_number_mappings`: accurate_database_id, module_slug, old_number, new_number.
- `sales_invoice_mapping_number`, `purchase_invoice_mapping_number`, `down_payment_purchase_invoice_mapping_number`, `receive_item_mapping_number`.
- `system_logs`: event_type(capture|migrate|run), status, payload progress (ter-masking).
- `settings`: `sales_invoice_number_source`, `purchase_invoice_number_source`, `down_payment_purchase_invoice_number_source`, `receive_item_number_source` (mapping_table|transaction_number_mappings), `data_retention_days`, `capture_concurrency`, `bulk_chunk_size`, `retry_max_attempts`.

## Strategi Capture — DETAIL-ONLY (wajib)

TIDAK ADA pilihan mode `list_only`/`list_and_detail`/`detail_only`. Sistem **selalu capture via detail**:
1. `list.do` hanya untuk dapat daftar ID + field identitas ringan.
2. Untuk tiap ID → `detail.do` → simpan raw JSON lengkap.

Ini menghilangkan ambiguitas. Kinerja diatur concurrency (5–10 paralel) + pagination list.

Filter capture yang ditawarkan:
- Rentang tanggal `filter.transDate.op` (BETWEEN/EQUAL/GREATER_EQUAL_THAN/LESS_EQUAL_THAN) format `d/m/Y`.
- Atau `filter.lastUpdate` (format `d/m/Y H:i:s`).
- `filter.invoiceDp=true` untuk down-payment.

## Daftar Lengkap Modul (53)

Konvensi endpoint: list `/api/{slug}/list.do`, detail `/api/{slug}/detail.do`, bulk-save `/api/{slug}/bulk-save.do`. Pengecualian: `expense-accrual`→`/api/expense/...`; `down-payment-*` pakai endpoint induk + `filter.invoiceDp=true`.

**MASTER (20, capture detail-only, nomor asli dipertahankan):** branch, currency, customer, customer-category, data-classification, department, employee, fob, glaccount, item, item-category, bill-of-material, price-category, project, tax, unit, vendor, vendor-category, vendor-price, warehouse.

**PENJUALAN (8):** sales-quotation(SQ), sales-order(SO), delivery-order(DO), sales-invoice(SI), down-payment-sales-invoice(SI), sales-return(SRT), sales-receipt(SR), shipment.

**PEMBELIAN (8):** purchase-requisition(PRQ), purchase-order(PO), receive-item(RI), purchase-invoice(PI), down-payment-purchase-invoice(PI), purchase-return(PRT), purchase-payment(PP), vendor-claim.

**KEUANGAN (7):** journal-voucher(JV), other-deposit(OD), other-payment(OP), bank-transfer, exchange-invoice, expense-accrual, roll-over.

**INVENTORI & PRODUKSI (10):** item-transfer, item-adjustment(IA), stock-opname-order, stock-opname-result, work-order, job-order, material-adjustment, material-slip, finished-good-slip, customer-claim.

(Prefix fallback jika tak ada: `strtoupper(substr(slug,0,2))`.)

## Halaman Aplikasi

### 0. App Shell & Navigasi
Sidebar kiri: **Dashboard, Migration Runs, Module Library, Migrasi, Log/Monitoring, Template, Pengaturan**. Header: workspace switcher (multi-tenant), indikator database sumber aktif, tombol "Migrate Baru" (dropdown 3 flagship), toggle dark mode, notifikasi, menu user. Navigasi berbasis role (Admin/Operator/Viewer).

### 1. Dashboard (pusat kendali)
- **Row KPI (5 kartu, klik → navigasi)**: Database Terhubung · Run Aktif/Berjalan (dengan progress ringkas) · Total Transaksi Ter-capture · Migrasi Sukses/Gagal/Skipped · Modul Siap Migrasi (x/53).
- **Quick Actions — 3 flagship terpisah**: kartu tombol "Buat Migration Run", "Migrate Modul", "Migrate Transaksi". **Masing-masing membuka halaman & alur tersendiri (terpisah, bukan stepping)** sesuai scope-nya:
  - Buat Migration Run → alur AOL-to-AOL (scope seluruh database).
  - Migrate Modul → alur Modul-to-Modul (scope 1 modul, semua transaksi).
  - Migrate Transaksi → alur Transaksi-to-Transaksi (scope transaksi terpilih).
- **Chart row**: Tren migrasi (bar/line sukses vs gagal per periode, filter rentang tanggal) · Status transaksi (donut pending/success/failed/skipped) · Top 10 modul by transaksi (horizontal bar).
- **Recent Migration Runs** (tabel): nama, tipe flagship, source→target, status badge, progress bar, sukses/gagal/skipped, tanggal, jadwal, aksi (lihat report, cancel, retry). Run berjalan di-update real-time (polling/SSE).
- **Upcoming Scheduled Runs**: daftar run terjadwal + tombol cancel jadwal.
- **Notifikasi**: run selesai/gagal, token Accurate kedaluwarsa, peringatan JV belum dimigrasi, hasil dry-run.
- **Empty state**: saat belum ada data — ilustrasi + CTA "Hubungkan database pertama" / "Jalankan simulasi".
- Data per-workspace; dashboard read-only untuk role Viewer. Auto-poll hanya saat ada run aktif (2–5s).

### 2. Alur AOL-to-AOL — Halaman Migration Run (terpisah)
Halaman khusus untuk scope **seluruh database** (semua 53 modul). Bukan bagian dari wizard lintas flagship.
- **Buat Run**: pilih source & target AOL.
- **Konfigurasi**: semua modul terurut default (dependensi) dengan toggle aktif/nonaktif, setting penomoran per modul (Asli/Kustom/Auto), opsi duplikat (skip/force-create/update), preview jumlah data, tombol **Simulasikan**.
- **Simulasi (Dry-Run)**: transformasi + validasi referensi tanpa menulis ke target. Output: jumlah OK/warning/error, **badge mapping confidence** per referensi (🟢 Resolved / 🟡 Unmapped / 🔴 Broken), estimasi waktu & jumlah request, contoh payload bermasalah.
- **Jalankan**: via queue, progress real-time per modul & per transaksi, cancel per-modul / force stop.
- **Report**: ringkasan sukses/gagal/skipped, breakdown per modul, export CSV/Excel, retry, diff report (delta sync).
- **Template & Jadwal**: simpan plan sebagai template, export/import JSON, preset bawaan; atur jadwal + notifikasi email/webhook.

### 3. Alur Modul-to-Modul — Halaman Module (terpisah)
Halaman khusus scope **1 modul × seluruh transaksinya**.
- Pilih 1 modul dari Module Library → Capture (detail-only) bila belum ada data.
- Tombol **Migrate Modul** → preview semua transaksi modul tsb + opsi penomoran + dry-run + opsi duplikat.
- Modal konfirmasi: nomor lama→baru editable, badge mapping confidence, mapping `invoiceNo` (sales-receipt/purchase-payment), suffix `-JU` (JV), warning JV.
- Jalankan via queue → report ringkas per modul.

### 4. Alur Transaksi-to-Transaksi — Halaman Migrasi (terpisah)
Halaman khusus scope **transaksi terpilih** dalam satu modul (fitur granular).
- Tabel transaksi: checkbox, filter (modul, tanggal, customer_name, vendor_name, bank_name, new_number, detail_field_search, hanya duplikat).
- Tombol **Migrate** → **modal konfirmasi**:
  - **Preview**: Nomor Lama, Modul, Tanggal, **Nomor Baru (Target)** editable + **badge mapping confidence**.
  - **Opsi Penomoran** (radio): Nomor Asli / Nomor Kustom (`PREFIX.YYYY.MM.DD.SEQ`, contoh `SI.2026.07.12.001`) / Auto.
  - **Mapping Manual Faktur Terkait (Detail Invoice)** — khusus sales-receipt & purchase-payment: tiap `detailInvoice` (sumber→input target), prefill dari mapping.
  - **Opsi Duplikat** (skip / force-create / update) dengan konfirmasi eksplisit.
  - Checkbox **Suffix -JU** khusus Journal Voucher untuk retry transaksi gagal.
  - Warning modal jika memilih `purchase-invoice`, `sales-invoice`, `sales-receipt`, `purchase-payment`: "JANGAN LUPA DAHULUKAN MIGRATE JURNAL UMUM (Bila Ada)".
- Jalankan via queue → report per transaksi.

### 5. Halaman Log / Monitoring
- List job & run dengan filter status; detail progress; cancel (force stop); audit trail user action.

### 6. Halaman Pengaturan
- Sumber nomor invoice (mapping_table / transaction_number_mappings), data retention, concurrency, retry. Manajemen workspace & user (roles).

## Backend: Flow Migrate

1. Terima `ids[]`, `target_database_id`, `numbering_mode`, `target_numbers{txId:no}`, `custom_invoice_mappings{txId:{oldInvNo:newInvNo}}`, `duplicate_mode`, `add_ju_suffix`.
2. `MigrateJob`: group per modul, chunk 100 → `POST /api/{module}/bulk-save.do` body `{"data":[...]}`.
3. Injeksi nomor target: `data['number']=data['no']=no` + `_custom_number=true`; simpan `_sourceNumber`; master data jangan diubah.
4. Attach `_custom_invoice_mappings`.
5. Parsing respons `{s:bool, d:[{s,d}]}` per indeks → update status (success/failed/skipped), simpan mapping lama→baru, progress ke `system_logs` (ter-masking).
6. **⚠️ JANGAN biarkan flag `_custom_number` dan `_custom_invoice_mappings` ter-drop oleh transformasi handler** — ini bug yang pernah terjadi: mapping invoiceNo sales-receipt diabaikan sehingga payload memakai nomor faktur lama dan data tidak terbentuk di target.
7. **Rate limiting & backoff**: concurrency terbatas, retry eksponensial (1s→2s→4s→…→cap) + jitter, max retries, circuit breaker. Token invalid → hentikan run + notifikasi.
8. **Log aman**: JANGAN log payload mentah. Log hanya metadata ter-masking (module, id, nomor, status, HTTP status, durasi).

## Backend: Dry-Run & Validasi

- `DryRunService` menjalankan transformasi + penomoran + resolve mapping (tanpa request bulk-save).
- Validasi referensi lintas-modul: `invoiceNo`, `purchaseOrderNumber`, `salesOrderNumber`, `receiveItemNumber`, `vendorNo`, `customerNo`, `itemNo`, `bankNo` → resolve ya/tidak, klasifikasi 🟢/🟡/🔴.
- Pre-flight checklist per modul (akun bank ada di target, faktur punya mapping, modul dependensi siap, data tidak 0).
- Output laporan dry-run + estimasi waktu.

## Backend: Duplikat, Delta Sync, Rollback

- **Duplikat**: cek `transaction_number_mappings` (nomor lama sudah terpakai) & cek nomor baru hasil penomoran vs target; terapkan mode skip/force-create/update; statistik masuk report.
- **Delta Sync**: setelah run awal, hanya proses transaksi baru/berubah via filter `lastUpdate` dengan watermark `last_update_watermark` per modul.
- **Rollback**: cancel run (per chunk & per modul, status cancelled); pembersihan mapping lokal untuk run ulang bersih; jelas di UI bahwa penghapusan data target tidak dilakukan otomatis.

## Aturan Transformasi Payload (terapkan di cleaner)

- Hapus: `id` (create), `vendorType`, `_sourceNumber`, `_custom_number`, `_custom_invoice_mappings`, null/''/`*Id`=0, `optLock`/`branchId` sub-item.
- `customer`→`customerNo`; `vendor`→`vendorNo`; `bank`→`bankNo` (sales-receipt, purchase-payment, other-payment, other-deposit).
- `glaccount`: `parent`→`parentNo`, `currency`→`currencyCode`.
- `detailInvoice` (sales-receipt, purchase-payment): `invoice`+`invoiceId`→`invoiceNo`; prioritas mapping manual → fallback `reset` → resolve tabel mapping (`sales-invoice`, `purchase-invoice`, atau `down-payment-purchase-invoice` jika `invoice.invoiceDp=true`) → nomor lama.
- `invoice` (sales-return, purchase-return)→`invoiceNumber`; `detailDownPayment`→`invoiceNumber`.
- `detailItem`: `item.no`→`itemNo`, `warehouse.name`→`warehouseName`, `itemUnit.name`→`itemUnitName`, `purchaseOrder.number`→`purchaseOrderNumber`, `salesOrder.number`→`salesOrderNumber`, `salesQuotation.number`→`salesQuotationNumber`.
- `detailJournalVoucher`: `glAccount.no`→`accountNo`, `vendor.vendorNo`→`vendorNo`, `customer.customerNo`→`customerNo`; buang baris amount <1; hapus `transactionType`.
- `detailAccount` (expense/other-payment/other-deposit): `account.no`→`accountNo`, `department.name`→`departmentName`, `project.no`→`projectNo`.
- `fromBank/toBank`→`fromBankNo/toBankNo`; `fromItemTransfer`→`fromItemTransferNo`; `billOfMaterial`→`billOfMaterialNo`; `manufactureOrder`→`manufactureOrderNo`; `jobOrder`→`jobOrderNumber`; `order`→`orderNumber`; `expensePayable`→`expensePayableNo`.
- NPWP strip non-digit pad 16.
- Field `number` dibuang (auto-number) kecuali `_custom_number`, untuk: delivery-order, purchase-invoice, purchase-order, purchase-payment, purchase-requisition, purchase-return, sales-invoice, sales-order, sales-quotation, sales-receipt, sales-return, receive-item, job-order, item-transfer.
- Save satu-per-satu (`/save.do`): warehouse, price-category, work-order, bill-of-material.
- Tax: resolve `salesTaxGlAccountId`/`purchaseTaxGlAccountId`→`salesTaxGlAccountNo`/`purchaseTaxGlAccountNo` via `/api/glaccount/detail.do`.

## Desain & UX (wajib modern)

- UI kit modern (Tailwind + shadcn/ui-style): Cards, Modals, Steppers, Toasts, Skeletons, Empty states, Progress, Tabs, Command palette.
- Aksen warna gradient **blue→indigo**, background slate/zinc netral, glassmorphism header/modal, shadow lembut.
- Tipografi Inter; `font-mono` untuk nomor dokumen. Dark mode default + toggle. Responsive penuh.
- Mikro-interaksi: transisi modal, animasi progress, skeleton loading, optimistic update. Accessible (kontras, keyboard, label).
- UX: **tiga alur flagship terpisah, bukan stepping** (tiap fitur halaman sendiri, dijalankan dari Dashboard per scope). Stepper internal hanya pada alur AOL-to-AOL; Modul-to-Modul & Transaksi-to-Transaksi ringkas (pilih → preview → jalankan). **Preview sebelum aksi** (jumlah data, nomor lama→baru, badge mapping confidence, warning); **tombol Simulasikan** di setiap plan; progress real-time per run/modul/transaksi; error actionable + tombol Retry (dan suffix `-JU` untuk JV); konfirmasi untuk aksi destruktif.

## Landing Page (banyak section, urut)

1. **Navbar** — logo, nav (Fitur, Cara Kerja, Modul, Harga, FAQ), CTA.
2. **Hero** — headline kuat, sub-headline, 2 CTA (mulai / lihat demo), screenshot mockup dashboard.
3. **Social proof strip** — angka: 53 modul, ribuan transaksi, migrasi aman.
4. **3 Fitur Flagship** — kartu AOL-to-AOL, Modul-to-Modul, Transaksi-to-Transaksi.
5. **Cara Kerja** — 3 langkah umum produk: Hubungkan database → Pilih scope & atur penomoran → Jalankan & pantau (untuk ketiga alur terpisah).
6. **Coverage Modul** — grid 53 modul per kategori dengan count.
7. **Keunggulan** — penomoran kustom, mapping faktur manual, dry-run simulasi, retry, monitoring, delta sync.
8. **Keamanan** — OAuth resmi, token terenkripsi, data hanya untuk migrasi, log aman.
9. **Use Case** — tim akuntansi, konsultan migrasi, perusahaan multi-entitas.
10. **Testimoni** (opsional).
11. **FAQ** — accordion (keamanan, biaya, durasi, modul didukung, dry-run).
12. **Pricing** — 2–3 tier (Starter / Professional / Enterprise) dengan fitur per tier + CTA.
13. **Interactive Demo** — mode simulasi/dry-run sebagai demo tanpa data asli.
14. **CTA banner**.
15. **Footer**.

## Acceptance Criteria

1. 3 flagship berfungsi sebagai **alur terpisah** (bukan stepping): AOL-to-AOL (halaman Migration Run), Modul-to-Modul (halaman Module), Transaksi-to-Transaksi (halaman Migrasi) — masing-masing dijalankan dari Dashboard sesuai scope-nya.
2. Capture selalu detail-only (tanpa pilihan mode), dengan filter tanggal/lastUpdate/invoiceDp.
3. Preview + modal konfirmasi: opsi penomoran Asli/Kustom/Auto, mapping invoiceNo sales-receipt & purchase-payment, opsi duplikat, badge mapping confidence.
4. Nomor kustom berformat `PREFIX.YYYY.MM.DD.SEQ` berurutan & lanjut dari nomor terakhir target.
5. **Dry-run** tersedia di setiap plan, validasi referensi lintas-modul (🟢/🟡/🔴), tanpa menulis ke target.
6. bulk-save chunk 100, status per transaksi akurat (success/failed/skipped + error message).
7. Mapping lama→baru tersimpan & dipakai lintas modul.
8. Progress real-time + cancel per-run/per-modul; retry transaksi/modul gagal; suffix `-JU` untuk JV.
9. Custom invoiceNo sales-receipt & purchase-payment benar-benar terpakai di payload (tidak ter-drop).
10. Delta sync via `lastUpdate`; template plan + import/export; scheduling + notifikasi; multi-tenant + RBAC; report/export CSV.
11. Keamanan: token terenkripsi, log ter-masking, rate limiting & backoff.
12. **Dashboard** lengkap (KPI, chart, quick actions 3 flagship, recent runs, scheduled, notifikasi, empty states) — lihat "Halaman Aplikasi §1".
13. Desain modern (dark mode, komponen modern, mikro-interaksi) dan landing page multi-section seperti di atas (termasuk Pricing & Interactive Demo).

Mulai dengan landing page + halaman Connect, lalu App Shell + Dashboard, lalu **3 halaman alur terpisah** (Migration Run → Module → Migrasi), lalu backend transform & queue, lalu monitoring, report, dan fitur opsional (delta sync, template, scheduling, multi-tenant).
