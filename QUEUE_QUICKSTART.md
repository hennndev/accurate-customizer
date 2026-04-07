# Queue Worker Quickstart

## 1) Aktifkan queue database

Set di `.env`:

```env
QUEUE_CONNECTION=database
```

## 2) Buat tabel queue

```bash
php artisan migrate
```

Migration queue (`jobs`, `job_batches`, `failed_jobs`) sudah disiapkan.

## 3) Jalankan worker

```bash
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0
```

Untuk production gunakan Supervisor agar worker selalu hidup.

### Multiple worker (langsung)

```bash
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
wait
```

### Multiple worker (recommended)

Pakai Supervisor dengan `numprocs` (lihat contoh lengkap di [QUEUE_SETUP.md](QUEUE_SETUP.md)).

## 4) Monitoring persisten

- Pull/Capture akan membuat log `event_type = capture_queue`
- Migrate akan membuat log `event_type = migrate_queue`
- Detail progres dan hasil tersimpan di `system_logs.payload`
- UI dapat polling endpoint:

```text
GET /system-logs/{log}/status
```

## 5) Jika job gagal

- Cek tabel `failed_jobs`
- Cek halaman **System Logs** (filter `capture_queue` / `migrate_queue`)
- Retry manual (opsional):

```bash
php artisan queue:retry all
```

## 6) Script helper multi worker

```bash
chmod +x start-queue-worker.sh
WORKER_COUNT=4 QUEUE_LIST=capture,migrate,default ./start-queue-worker.sh
```
