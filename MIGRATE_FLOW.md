# Alur Migrasi Data (Migration Flow)

Dokumen ini menjelaskan mekanisme bagaimana migrasi (pengiriman) data dari database lokal ke sistem target Accurate bekerja pada project Accurate Customizer.

## 1. Inisiasi dari Antarmuka (UI)
1. **Pilih Transaksi:** Pengguna memfilter dan mencentang (checkbox) satu atau beberapa data transaksi/master yang ingin dikirimkan pada halaman `/migrate`.
2. **Pilih Target Database:** Pengguna memilih Accurate Database yang akan menjadi tempat tujuan data tersebut dimigrasi.
3. **Eksekusi:** Ketika pengguna menekan tombol eksekusi ("Migrate to Database" / "Force Migrate"), array yang berisi kumpulan ID Transaksi dikirim ke endpoint Controller.

## 2. Penanganan Controller (`DataMigrateController@migrateToAccurate`)
Tugas utama Controller di sini bukanlah mengirimkan data langsung, melainkan mempersiapkan "Job" agar ditangani di belakang layar (Background Process):
- **Buka Sesi Target:** Melakukan ping/request ke Accurate (`AccurateService->openDatabaseById()`) untuk memastikan koneksi target DB berhasil, dan me-*refresh* session token.
- **Buat Tracker Log:** Membuat satu baris record di tabel `system_logs` (berstatus `queued`). ID log ini dilempar kembali ke Frontend (UI) sebagai *Monitor ID*.
- **Dispatch Queue:** Mengirim tugas (`MigrateTransactionsJob::dispatch`) ke sistem Queue Laravel (pada jalur queue `migrate`).
- **Respon Cepat:** Segera membalas request ke Frontend dengan JSON berisi `monitor_id` tanpa membiarkan browser menunggu (loading lambat).

## 3. Eksekusi Latar Belakang (`MigrateTransactionsJob`)
Proses berat dijalankan secara asinkron di dalam sistem Queue Worker:
1. **Pengambilan Data (Fetch):** Job ini akan melakukan query ke tabel `transactions` berdasarkan array ID yang diterimanya, namun hanya mengambil data dengan `status != success` (kecuali jika opsi *Force Migrate* dicentang).
2. **Pengelompokan (Grouping):** Data transaksi dikelompokkan berdasarkan modulnya (contoh: `sales-invoice`, `other-deposit`). Accurate API mensyaratkan setiap _bulk upload_ dilakukan per endpoint modul.
3. **Persiapan Payload:** 
   - Di dalam iterasi per modul, _raw_ JSON yang tersimpan pada atribut `data` di-decode.
   - Dilakukan *tweaking logic* (penyesuaian). Contoh: penambahan *suffix* `-JU` pada modul journal yang pernah gagal, penambahan flag `invoiceDp = true` pada faktur uang muka, atau me-map ulang nomor invoice lama ke invoice target jika diperlukan.
   - URL endpoint diubah menjadi format bulk. Misalnya `/api/other-deposit/list.do` diubah menjadi `/api/other-deposit/bulk-save.do`.
4. **Chunking (Batching):** Agar payload tidak terlampau besar yang bisa menyebabkan Accurate API memutus koneksi (Timeout/413 Payload Too Large), array data dipecah (chunk) menjadi maksimal 100 item per request.
5. **Proses API (`AccurateService->bulkSaveToAccurate`):** Data dikirim secara massal ke endpoint `bulk-save.do`.

## 4. Proses Akhir & Penanganan Error (`TransactionSaver` & Mapping)
- **Fallback Modul:** Jika modul yang dituju ternyata tidak mendukung API _bulk-save_ dari sananya (seperti `warehouse` atau `bill-of-material`), fungsi di `TransactionSaver` secara pintar akan mengakalinya dengan melakukan _looping_ dan mengirim ke endpoint `/save.do` secara tunggal satu per satu.
- **Number Mapping:** Jika transaksi sukses masuk (khususnya untuk modul krusial seperti Sales Invoice), *Nomor Lama* (di DB sumber) dan *Nomor Baru* (dari respon Accurate) disimpan ke dalam database internal. Hal ini vital saat memigrasi transaksi turunan (misal: Sales Return yang harus merujuk pada nomor Sales Invoice yang baru!).
- **Update Database Lokal:** Sistem mencocokkan mana indeks array yang berhasil (parameter respon `s = true`) dan gagal (`d` / `e` berisi pesan error). Setiap transaksi lokal di tabel `transactions` kemudian di-update atribut `status`-nya menjadi `success` atau `failed`, serta diisi atribut `error_message`.
- **Progress Tracker:** Setiap perulangan selesai, *job* ini memperbarui nilai kolom `payload->progress` di tabel `system_logs`. Inilah nilai yang secara rutin "ditanya" (polling) oleh script Javascript di Frontend, sehingga *progress bar* yang berwarna hijau dapat bergerak maju dari 0% ke 100%.
