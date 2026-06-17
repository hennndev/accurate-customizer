# Flow Mekanisme Capture dan Migrate Data

Dokumen ini menjelaskan alur (flow) lengkap dari integrasi data aplikasi ini, mulai dari proses penarikan (Capture) data dari API Accurate ke database lokal, hingga proses pengiriman kembali (Migrate) data tersebut ke database Accurate yang berbeda.

Proses ini terbagi menjadi dua fase besar:
1. **Fase Capture**: Penarikan data (List & Detail) dari sumber Accurate.
2. **Fase Migrate**: Pengiriman data hasil capture ke target Accurate.

---

## FASE 1: CAPTURE DATA

### 1. ModulesController (`captureData`)
File: `app/Http/Controllers/ModulesController.php`

Ini adalah titik awal ketika pengguna menekan tombol "Capture" di UI.

**Alur Kerja:**
1. **Penerimaan Parameter**: Menerima request dengan parameter `$module` (contoh: `sales-invoice`, `purchase-order`).
2. **Validasi & Mapping Module**: 
   - Sistem akan mengecek `$moduleMapping` untuk mendapatkan informasi esensial seperti:
     - `name` (Nama Modul)
     - `list_endpoint` (URL untuk list, misal: `/api/sales-invoice/list.do`)
     - `detail_endpoint` (URL untuk detail, misal: `/api/sales-invoice/detail.do`)
3. **Persiapan Filter**: 
   - Mempersiapkan filter-filter seperti filter tanggal (`start_date`, `end_date`) dan menyusunnya dalam bentuk operasi filter yang dikenali API Accurate (misal: `filter.lastUpdate.op = BETWEEN`).
4. **Pembuatan Tracker Log**: 
   - Sistem membuat satu baris (row) di tabel `system_logs` dengan status `queued` sebagai tracker. Tabel ini akan digunakan UI untuk mengecek progress secara realtime (via Long-polling/AJAX).
5. **Dispatch Queue**: 
   - Mengirim proses aktual ke Background Job (`CaptureModuleJob`) menggunakan Queue worker (diarahkan ke queue `capture`).
6. **Response to Client**: 
   - Mengembalikan respon JSON berisi `monitor_id` (ID dari tracker log) sehingga frontend bisa langsung mulai melakukan *polling* progress.

### 2. CaptureModuleJob
File: `app/Jobs/CaptureModuleJob.php`

Karena proses tarik data (terutama beserta detailnya) memakan waktu sangat lama, proses ini dijalankan secara asynchronous melalui Laravel Queue.

**Alur Kerja:**
1. **Phase 1: Pengumpulan List ID (List Capture)**
   - Sistem melakukan *looping* paginasi, menarik data per halaman menggunakan `list_endpoint`.
   - Mengirim param `fields=id,number` ke Accurate agar response lebih ringan.
   - Dari setiap baris data, ID transaksi akan dimasukkan ke dalam array sementara (`$detailCandidates`).
2. **Phase 2: Pengambilan Data Detail (Detail Capture)**
   - **Deduplikasi**: Transaksi yang sudah ada di database (`transactions`) akan di-skip untuk menghemat hit API.
   - **Fetch Detail**: Melakukan iterasi memanggil `detail_endpoint` untuk setiap ID yang terkumpul.
   - **Transformasi (Hooks)**: Melewati class `ModuleManager::forSlug($module)->transformDetail()` untuk melakukan manipulasi data jika diperlukan.
   - **Batching & Simpan ke DB**: Data detail di-encode ke JSON dan dimasukkan ke dalam batch insert. Apabila mencapai 20 data, sistem mengeksekusi bulk insert menggunakan `DB::table()->insertOrIgnore()`.
3. **Phase 3: Finalisasi**
   - Menjalankan flush insert terakhir untuk sisa data.
   - Memperbarui tabel `system_logs` tracker menjadi `success` atau `warning` dengan progress 100%.

---

## FASE 2: MIGRATE DATA

### 1. DataMigrateController (`migrateToAccurate`)
File: `app/Http/Controllers/DataMigrateController.php`

Titik awal ketika pengguna telah memilih beberapa transaksi dari UI lalu menekan tombol "Migrate ke Accurate".

**Alur Kerja:**
1. **Penerimaan Parameter**: Menerima request berupa list of `transaction_ids`, target `database_id`, dan opsi `force_create`.
2. **Validasi Koneksi Target DB**:
   - Membuka sesi ke database target dengan memanggil `AccurateService::openDatabaseById()`.
   - Hal ini memastikan bahwa kita dapat berinteraksi dengan database Accurate tujuan serta mengambil informasi tentang database target tersebut.
3. **Pembuatan Tracker Log**:
   - Membuat tracker log di tabel `system_logs` dengan tipe `migrate_queue` berstatus `queued`. Tracker ini memuat ID database tujuan serta total transaksi yang akan dikirim.
4. **Dispatch Queue**:
   - Mengirim perintah ke Background Job (`MigrateTransactionsJob`) lewat antrian `migrate`, sembari passing token Accurate saat ini, sesi DB target, dan ID dari tracker log.
5. **Response to Client**:
   - Mengembalikan respon JSON berisi `monitor_id` agar UI frontend dapat menampilkan progress bar secara otomatis.

### 2. MigrateTransactionsJob
File: `app/Jobs/MigrateTransactionsJob.php`

Job ini bertugas memproses dan mengirimkan bulk JSON transaksi ke target Accurate API.

**Alur Kerja:**
1. **Inisialisasi**:
   - Job mengatur ulang `accurate_access_token` dan `accurate_database` di *session* lokal, sebab job berjalan secara *background* terpisah dari proses web HTTP.
   - Mengubah status tracker menjadi `running`.
2. **Pengambilan & Pengelompokkan Data**:
   - Mengambil data asli transaksi dari DB dan di-filter berdasarkan `transaction_ids`.
   - Data-data ini dikelompokkan berdasarkan **Modul** (`module.slug`) sebab tiap modul memerlukan *endpoint* yang berbeda-beda.
3. **Looping Eksekusi per Modul**:
   - **Persiapan Payload**: Meng-extract data JSON. Sistem juga melakukan pengecekan `SalesInvoiceMapping` (khusus Sales Invoice) untuk men-skip invoice yang pernah bermigrasi sebelumnya.
   - **Bulk Save & Batching**: Data JSON yang sudah rapi dimasukkan ke dalam chunk berisi maksimal 100 data. Chunk data ini lalu dikirim sekaligus (bulk insert) memanggil `AccurateService::bulkSaveToAccurate()` (ke URL `bulk-save.do`).
4. **Pemrosesan Hasil Response Accurate**:
   - **Bila Berhasil**: 
     - Mapping nomor lama dengan nomor baru direkam via `NumberMappingManager::storeNumberMappings()` (sangat esensial untuk menjaga kaitan transaksi turunan seperti *Return* atau *Payment*).
     - Data transaksi di database lokal (tabel `transactions`) diperbarui dengan status `success` dan `migrated_at`.
   - **Bila Ada Error Detail**:
     - Bila sebagian item gagal, job mengekstrak pesan error dari JSON response Accurate. Pesan error ditaruh pada kolom `error_message` dan status record diubah ke `failed`.
5. **Finalisasi Tracker**:
   - Progress bar tracker log di-*update* sejalan dengan bertambahnya chunk yang selesai diproses.
   - Di akhir proses job, status final (apakah *success*, *partial*, *failed*, atau *warning* jika *token invalid*) di-*commit* ke tabel `system_logs` beserta detail modul mana saja yang berhasil maupun gagal.
