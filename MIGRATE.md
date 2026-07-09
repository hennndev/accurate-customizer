# Flow Mekanisme Migrate Data Accurate

Dokumen ini menjelaskan alur (flow) detail dari proses **Migrate** data transaksi dari database lokal (hasil capture) untuk dikirim (push/save) ke database Accurate tujuan (Target Database).

Proses migrasi merupakan proses asinkronus (*background process*) yang menitikberatkan pada keandalan pengiriman data secara massal (*bulk*) dan pencegahan duplikasi serta pemetaan (*mapping*) nomor urut.

---

## 1. Titik Awal: `DataMigrateController@migrateToAccurate`
**File:** `app/Http/Controllers/DataMigrateController.php`

Ini adalah titik awal (*entry point*) saat pengguna memilih sejumlah baris transaksi di halaman antarmuka (UI) Migrate dan menekan tombol **"Migrate ke Accurate"**.

### Alur Eksekusi:
1. **Validasi Request & Target Database**:
   - Menerima ID transaksi yang dipilih (`ids`).
   - Menerima ID Database Accurate target (`target_database_id`).
   - Melakukan koneksi awal menggunakan `AccurateService::openDatabaseById()` untuk memastikan target database siap menerima koneksi, sekaligus mengambil *Session/Host/DB Name*.
2. **Setup Log Pelacak (Tracker)**:
   - Sebuah baris baru ditambahkan ke tabel `system_logs` (dengan `event_type = migrate_queue` dan `status = queued`).
   - Tracker ini berfungsi agar pengguna di frontend dapat melihat indikator persentase loading secara real-time.
3. **Dispatch ke Queue**:
   - Memanggil `MigrateTransactionsJob::dispatch(...)` ke dalam queue bernama `migrate`.
   - Controller langsung mereturn *monitor_id* (ID tracker) ke *frontend* tanpa harus menunggu proses *push* API selesai.

---

## 2. Pemrosesan Latar Belakang: `MigrateTransactionsJob`
**File:** `app/Jobs/MigrateTransactionsJob.php`

Job ini memikul beban utama untuk mem-parsing data transaksi lokal menjadi *payload* yang disetujui API Accurate, lalu mem-_push_ data secara bulk (hingga ratusan baris sekaligus).

### Alur Eksekusi:
1. **Inisialisasi & Re-Koneksi Sesi**:
   - Mengisi kembali token sesi OAuth ke dalam fungsi lokal *job*, mengingat *queue worker* berjalan di *environment* CLI yang terpisah dari *session* web/HTTP pengguna.
   - Memperbarui status *tracker* di tabel log menjadi `running`.
2. **Pengelompokkan Berdasarkan Modul**:
   - Sistem menarik semua data transaksi berdasarkan ID yang dikirim.
   - Karena setiap modul di Accurate (contoh: *Sales Invoice*, *Purchase Order*) memiliki format *endpoint* API yang berbeda-beda, sistem mengelompokkan data (`groupBy('module.slug')`). Job akan memproses modul-modul ini satu persatu.
3. **Pra-Pemrosesan (Pre-processing) & Validasi Khusus**:
   - **Sales Invoice Deduplication**: Khusus modul `sales-invoice`, job akan mengecek tabel `sales_invoice_mappings` pada database target untuk melihat apakah nomor ini sudah pernah bermigrasi. Jika sudah, sistem langsung melewatinya (skip) dengan menandai status menjadi `success` otomatis tanpa membuang kuota hit API.
   - **Manipulasi Jurnal Umum (JU)**: Jika opsi penambahan akhiran dipilih (`add_ju_suffix` = true) untuk `journal-voucher`, sistem akan menempelkan teks "-JU" di belakang nomor urut agar tidak bentrok di Accurate.
   - **Down Payment**: Untuk jenis modul *Uang Muka* (DP), flag khusus `invoiceDp = true` diinjeksikan secara otomatis ke *payload*.
4. **Pembagian Menjadi Chunk (Batching)**:
   - Data JSON setiap transaksi dikumpulkan (array `bulkData`).
   - Kumpulan array ini lalu dipotong (`array_chunk`) menjadi paket per 100 baris. 
5. **Pengiriman ke API Accurate (Bulk Save)**:
   - Setiap paket (chunk) dilempar ke `AccurateService::bulkSaveToAccurate()`.
   - URL yang dipakai adalah versi massal (seperti `/api/sales-invoice/bulk-save.do`).
6. **Penanganan Error Bersarang (Nested Error Parsing)**:
   - Jika `bulkSaveToAccurate` gagal penuh, transaksi dalam satu *chunk* akan ikut gagal.
   - Jika sukses parsial, Accurate akan membalas dengan struktur array `itemResults`. Job ini akan melakukan iterasi memilah transaksi mana yang sukses (`s = true`) dan mana yang error. Pesan error ditarik dan dipipihkan (`implode`), kemudian di-update ke *field* `error_message` di DB lokal beserta dengan pergantian `status = failed`.

---

## 3. Eksekusi Jaringan & Modifikasi Payload: `TransactionSaver`
**File:** `app/Services/Accurate/TransactionSaver.php`

Class ini bertugas menangani koneksi POST ke server Accurate.

### Alur Eksekusi:
1. **Fallback ke One-by-One Save**:
   - Jika API modul tidak mendukung `bulk-save` (contoh: *Warehouse*, *Price Category*, *Work Order*), class ini akan mengoper tugas ke mode `saveOneByOne`. Mode ini mengirim *request* satu-per-satu menggunakan `save.do`.
2. **Penanganan Khusus Modul Pajak (Tax)**:
   - Sebelum kirim, modul pajak harus mengubah struktur *mapping GL Account ID* menjadi *GL Account No* dengan cara melakukan request detail GL (Buku Besar) tambahan secara *on-the-fly*.
3. **Pembersihan Data (Data Cleaner)**:
   - Data di-filter ulang menggunakan `DataCleaner::cleanDataItem()` agar membuang field-field asing (`id`, `created_at`, referensi virtual, dll) yang tidak boleh ada pada request API Accurate.
4. **Trigger & Lempar Error (Token Habis)**:
   - Class inilah yang menerima response gagal dari HTTP. Jika menemukan code `401`/`403`, ia akan melempar pesan *"ACCURATE_TOKEN_INVALID: Sesi Accurate habis..."* yang akan membuat *Capture/Migrate Job* memberhentikan seluruh sisa eksekusi.

---

## 4. Pemetaan Nomor Urut: `NumberMappingManager`
**File:** `app/Services/Accurate/NumberMappingManager.php`

Salah satu fase terpenting setelah data terikirim sukses.

### Alur Eksekusi:
- Ketika sebuah transaksi sukses terbuat di database target, Accurate mungkin meng-generate "Nomor Urut Baru" atau merubah formatnya.
- Class ini bertugas menerima hasil response sukses, membaca nomor yang lama (dari `originalData`), kemudian menyandingkannya dengan nomor yang baru.
- Hasil *mapping* ini disimpan ke dalam tabel lokal `number_mappings`.
- **Kegunaan**: Tabel relasi ini kelak sangat penting di masa depan bila sistem ini mem-push transaksi turunan seperti *Retur Penjualan* atau *Pembayaran Pembelian*, karena sistem harus tahu dokumen induk mana di database Target yang harus ditunjuk.
