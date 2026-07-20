# Proyek: Accurate Customizer
**Tanggal Handoff:** 18 Juli 2026

Dokumen ini adalah ringkasan teknis menyeluruh (**Hulu ke Hilir**) mengenai arsitektur, alur data, dependensi, dan seluruh perbaikan yang dilakukan pada proyek `accurate-customizer`. Proyek ini berfungsi sebagai aplikasi perantara berbasis **Laravel 10** untuk menyelaraskan, menyesuaikan, dan memindahkan (migrasi) data transaksi antar-database di platform **Accurate Online** melalui API resmi.

---

## 1. Arsitektur Alur Sistem (Hulu ke Hilir)

Sistem bekerja dalam 3 tahapan utama dari pengambilalihan data hingga transmisi akhir:

```
[ HULU: Ingestion ] ──────► [ PROSES TENGAH: Processing ] ──────► [ HILIR: Migration ]
Accurate OAuth &            Filter, Edit Raw JSON,                 Chunking & Bulk-Save API
Capture Data Job            Preview Custom Numbering               MigrateTransactionsJob
  (CaptureModuleJob)          (DataMigrateController)                (Accurate /bulk-save.do)
```

### A. Tahap Hulu (Ingestion & Capture)
1. **Autentikasi OAuth (`AccurateController.php`):** Memproses alur OAuth2 untuk mendapatkan `access_token` dan `refresh_token` dari Accurate API.
2. **Koneksi Database (`DatabaseSelectionController.php`):** Membuka sesi koneksi ke salah satu database Accurate pengguna.
3. **Capture Data Transaksi (`ModulesController.php` & `CaptureModuleJob.php`):**
   - Menarik daftar transaksi dari endpoint Accurate (contoh: `/api/sales-invoice/list.do`).
   - Menyimpan *raw JSON* transaksi ke dalam tabel lokal `transactions` lengkap dengan `accurate_database_id` dan `module_id`.
4. **Capture Pemetaan Nomor (`CaptureTransactionNumberMappingJob.php`):** Menarik riwayat pemetaan nomor transaksi yang ada di Accurate ke tabel `transaction_number_mappings`.

### B. Tahap Proses Tengah (Data Management & Customization)
1. **Penyaringan & Pengelolaan (`DataMigrateController.php`):**
   - Mendukung pencarian ekstensif berdasarkan nomor transaksi, nama pelanggan, tanggal transaksi, jenis transaksi, dan nama modul.
   - Memungkinkan pengubahan (*editing*) *raw JSON* langsung dari UI jika terdapat kesalahan format sebelum migrasi.
2. **Pratinjau Penomoran Kustom (`previewCustomNumbering`):**
   - Mengurutkan dan menghasilkan format penomoran baru secara otomatis sesuai pola (contoh: `SI.2023.10.12.001`) berdasarkan tanggal transaksi dan prefix modul.

### C. Tahap Hilir (Migration & Delivery)
1. **Antrean Migrasi (`MigrateTransactionsJob.php`):**
   - Menerima batch transaksi pilihan dari antarmuka.
   - Membuka koneksi ke *database tujuan* Accurate.
   - Mengelompokkan transaksi per modul dan memotong pengiriman menjadi *chunks* per 100 data.
   - Menyesuaikan payload khusus (seperti penambahan sufiks `-JU` untuk Journal Voucher gagal, flag `invoiceDp` untuk uang muka).
2. **Pengiriman API (`AccurateService.php`):**
   - Mengirimkan data massal via endpoint `/bulk-save.do`.
3. **Pencatatan Hasil & Mapping:**
   - Memperbarui status transaksi (`success` / `failed`) dan mencatat pesan kesalahan di `error_message`.
   - Menyimpan hasil pemetaan nomor baru ke `transaction_number_mappings`.
   - Mengirimkan pembaruan status secara *real-time* ke pelacak `SystemLog`.

---

## 2. Stack Teknologi & Environment
- **Framework:** Laravel Framework 10.x (PHP ^8.1)
- **Database:** MySQL
- **Frontend:** Laravel Blade + Alpine.js (`x-data`) + Tailwind CSS.
- **Konfigurasi (.env):** Membutuhkan kredensial Accurate (`ACCURATE_API_URL`, `ACCURATE_CLIENT_ID`, `ACCURATE_CLIENT_SECRET`).
- **Queue Worker:** Memerlukan Queue Worker aktif (`php artisan queue:work` atau script `start-queue-worker.sh` di root) untuk menangani job `capture` dan `migrate`.

---

## 3. Struktur Modul & Model Utama
- **`AccurateDatabase` & `Module`:** Registrasi DB Accurate dan daftar modul transaksi.
- **`Transaction`:** Penyimpanan utama transaksi lokal (*raw JSON*).
- **`TransactionNumberMapping`:** Tabel pemetaan nomor transaksi lama vs nomor transaksi baru.
- **`SystemLog`:** Log pelacak proses antrean migrasi & audit trail.
- **`Setting`:** Konfigurasi retensi log & pagination.

---

## 4. Log Perbaikan & Penyesuaian Terbaru
- **Lokalisasi Bahasa (UI Bahasa Indonesia):** Seluruh Blade view (`migrate`, `modules`, `system-logs`, `layouts.app`, dll) beserta dialog konfirmasi (SweetAlert, floating menu maintenance, modal pembatalan & penghapusan) telah sepenuhnya diterjemahkan ke Bahasa Indonesia.
- **Pembersihan Kolom Tabel:** Menghapus kolom *Description* dan *Accurate ID* dari tabel migrasi agar UI lebih bersih.
- **Bugfix Pembatalan (Force Stop):** Penambahan pengecekan `isCancelled()` pada perulangan *chunk* `MigrateTransactionsJob` agar proses benar-benar berhenti saat di-cancel dari UI.
- **Bugfix Nomor GL Account (Akun Perkiraan):** 
  - **Penyebab 1:** Konfigurasi modul `glaccount` di `ModulesController.php` awalnya menyetel `'identifier_field' => 'name'`, sehingga saat penarikan (*capture*), nomor transaksi diisi oleh Nama Akun (misal: "Kas Kecil") alih-alih Nomor Akun (`no`, misal: "1101.01").
  - **Penyebab 2:** Penomoran kustom (*custom auto-numbering*) sebelumnya diterapkan ke seluruh data secara acak tanpa membedakan data master dan data transaksi, yang menyebabkan Nomor Akun terubah menjadi format transaksi (`GL.2026.07.19.001`).
  - **Solusi:** `identifier_field` untuk `glaccount` telah diperbaiki menjadi `'no'`. Logika pada `MigrateTransactionsJob.php` dan `DataMigrateController.php` telah diproteksi agar data master (khususnya `glaccount`) selalu mempertahankan Nomor Akun aslinya (`no`) dan tidak bisa tertimpa oleh penomoran otomatis transaksi.
- **Bugfix Error Message pada Capture Ulang Data Duplikat:** 
  - **Penyebab:** Sebelumnya `CaptureModuleJob` menggunakan `insertOrIgnore()`. Jika suatu data/transaksi pernah gagal dalam migrasi terdahulu (sehingga memiliki `status = 'failed'` dan `error_message` tertentu), lalu dilakukan penarikan (*capture*) ulang dari Accurate, `insertOrIgnore` akan mengabaikan data duplikat tersebut. Akibatnya, `error_message` dan `status = 'failed'` lama dari migrasi sebelumnya tetap tertinggal di record hasil capture.
  - **Solusi:** `CaptureModuleJob.php` diubah menggunakan `upsert()`. Setiap kali data ditarik ulang dari Accurate, data JSON diperbarui dengan yang terbaru, status dikembalikan ke `'pending'`, dan `error_message` secara eksplisit dibersihkan (`null`), sehingga data hasil capture dari Accurate senantiasa bersih dari bekas error migrasi lama.
- **Penghapusan Fitur Force Create & Skip Approval:** 
  - **Penyebab:** Sebelumnya terdapat pengecekan `SalesInvoiceMapping` dan flag `forceCreate`. Jika suatu faktur penjualan pernah dimigrasikan sebelumnya dan `forceCreate` bernilai `false`, sistem langsung menandai status transaksi sebagai `success` tanpa mengirimkan request ke API Accurate. Hal ini menyebabkan kerancuan ketika data transaksi di Accurate target telah dihapus lalu pengguna ingin melakukan migrasi ulang.
  - **Solusi:** Seluruh logika penyaringan `SalesInvoiceMapping` dan opsi UI `Force Create` telah dihapus. Sekarang seluruh transaksi yang dipilih untuk migrasi akan selalu langsung dikirimkan ke API Accurate tanpa ada pengecekan/skipping tersembunyi.
- **Bugfix Pengecekan Duplikasi pada Capture Modul Vendor & Master Data:** 
  - **Penyebab 1:** Saat pengambilan data list (`fetchModuleDataPage`), field `vendorNo` dan `customerNo` tidak dieksplisitkan dalam `fieldsToRequest`, menyebabkan `$fallback_number` kandidat bernilai `null` sehingga pengecekan duplikat sebelum fetch detail dilewati.
  - **Penyebab 2:** Query pengecekan duplikat di `CaptureModuleJob.php` sebelumnya hanya menyaring berdasarkan `accurate_database_id` tanpa menyertakan `module_id`, menyebabkan potensi salah deteksi duplikat antar modul yang berbeda.
  - **Solusi:** `CaptureModuleJob.php` telah diperbarui: `fieldsToRequest` kini menyertakan `vendorNo`, `customerNo`, `no`, `number`; resolusi `fallback_number` ditingkatkan; dan query pengecekan duplikat kini wajib memfilter `module_id`.
- **Penambahan & Penyesuaian Kolom Nama pada Tabel Migrasi:** 
  - Menambahkan & menyempurnakan accessor `getEntityNameAttribute()` pada model `Transaction` agar mampu mengekstrak nama entitas/transaksi secara presisi di seluruh jenis modul (Vendor `name`/`vendorName`, Customer `name`/`customerName`, Item `name`/`itemName`, GL Account `name`, Journal Voucher `description`/`memo`, Cash `payTo`/`receivedFrom`, dsb.).
  - Menambahkan kolom `'data'` ke dalam kueri `->select([...])` pada `DataMigrateController.php` (method `index`) agar payload JSON dimuat oleh Eloquent, serta membersihkan cache view Blade.
  - Menampilkan kolom **Nama** di antara kolom *Nomor Lama* dan *Nomor Baru* pada tabel di halaman `resources/views/migrate/index.blade.php`.
- **Dokumentasi Teknis:** Dibuat berkas pendukung `technical_documentation.md`.

---

## 5. Aturan Kerja & Maintenance (Rules Workflow)
- **Update Handoff Otomatis:** Setiap kali dilakukan perubahan arsitektur, bugfix, atau penambahan fitur baru pada proyek ini, informasi perubahan **WAJIB** segera diperbarui ke dalam berkas `HANDOFF.md` ini.
- **Queue Worker Check:** Selalu pastikan `QUEUE_LIST=capture,migrate,default ./start-queue-worker.sh` berjalan saat melakukan migrasi skala besar.
