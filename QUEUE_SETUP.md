# Queue Setup (Single & Multiple Worker)

## 1) `.env`

```env
QUEUE_CONNECTION=database
```

## 2) Migration queue table

```bash
php artisan migrate
```

## 3) Multiple worker (manual, quick test)

Jalankan 3 proses worker sekaligus di server:

```bash
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
php artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 &
wait
```

## 4) Multiple worker (recommended: Supervisor)

### A. 1 program, `numprocs` banyak

`/etc/supervisor/conf.d/accurate-worker.conf`

```ini
[program:accurate-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=capture,migrate,default --tries=3 --timeout=0 --sleep=1 --backoff=5
directory=/path/to/project
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### B. Split queue per karakteristik beban

Contoh: capture lebih berat dari migrate.

```ini
[program:accurate-capture]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=capture --tries=3 --timeout=0 --sleep=1 --backoff=5
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/queue-capture.log

[program:accurate-migrate]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=migrate,default --tries=3 --timeout=0 --sleep=1 --backoff=5
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/queue-migrate.log
```

Reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## 5) Pakai script helper (opsional)

Script `start-queue-worker.sh` sekarang support multi worker dari env:

```bash
chmod +x start-queue-worker.sh
WORKER_COUNT=4 QUEUE_LIST=capture,migrate,default ./start-queue-worker.sh
```

## 6) Monitoring progress persisten

- Capture queue tracker: `event_type = capture_queue`
- Migrate queue tracker: `event_type = migrate_queue`
- Endpoint polling status:

```text
GET /system-logs/{log}/status
```

## 7) Sizing awal yang aman

- VPS 2 vCPU: mulai `numprocs=2` atau `3`
- VPS 4 vCPU: mulai `numprocs=4` atau `6`
- Pantau CPU/RAM + durasi job; naikkan bertahap.

## 8) Konsep implementasi queue (Migrate ke Accurate)

Tujuan utama: proses migrate tidak berjalan di request HTTP user (non-blocking), tapi dipindah ke worker background.

### Arsitektur ringkas

1. User klik **Migrate Selected** di UI.
2. Controller validasi input, buat tracker log (`event_type = migrate_queue`), lalu `dispatch` job ke queue `migrate`.
3. HTTP response langsung balik cepat (`queued`, `monitor_id`) tanpa menunggu push selesai.
4. Worker memproses job `MigrateTransactionsJob` di background (batch/chunk).
5. Job update progress ke `system_logs.payload` (success/failed/progress).
6. UI polling `GET /system-logs/{id}/status` untuk render progress realtime.
7. Saat selesai/gagal, tracker status final diset (`success`/`warning`/`failed`).

### Kontrak payload tracker (disarankan)

Gunakan payload konsisten agar UI monitoring reusable lintas project:

- `progress` (0..100)
- `total_selected`
- `success_count`
- `failed_count`
- `module_results` (opsional, per modul)
- `error` (opsional, saat failed)

### Prinsip penting untuk project lain

- Endpoint HTTP **hanya enqueue**, jangan proses berat di controller.
- Job harus **idempotent** (aman jika retry).
- Update tracker dilakukan bertahap (mis. per chunk/page), bukan hanya di akhir.
- UI monitor baca tracker, bukan menebak progress random.

## 9) SOP setup VPS (production)

### Prasyarat

- Queue driver: `QUEUE_CONNECTION=database`
- Tabel queue sudah ada (`jobs`, `job_batches`, `failed_jobs`)
- Supervisor terpasang dan aktif

### Langkah sekali setup

1. Install supervisor:

```bash
sudo apt update
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

2. Pastikan Laravel queue aktif:

```bash
php artisan migrate
php artisan config:clear
php artisan cache:clear
```

3. Buat config supervisor (single program atau split queue; lihat section 4).

4. Apply config:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start accurate-worker:*
sudo supervisorctl status
```

## 10) Operasional harian (deploy)

Setelah deploy code baru:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

Kenapa `queue:restart` penting: worker lama tidak otomatis reload code terbaru.

## 11) Checklist reliability (wajib untuk high-volume)

- Gunakan `timeout` worker yang realistis untuk job panjang.
- `stopwaitsecs` supervisor harus > `timeout` worker.
- Pantau `failed_jobs` dan buat prosedur retry.
- Simpan log worker terpisah per queue untuk debugging cepat.
- Untuk beban besar, split worker `capture` vs `migrate`.

## 12) Template adaptasi untuk project lain

Saat meniru pattern ini ke project lain, minimal siapkan:

1. **Tracker table/log** untuk progress persisten.
2. **Status endpoint** (`/system-logs/{id}/status` atau setara).
3. **Queued job** per use case berat (import/sync/export/migrate).
4. **UI polling** yang membaca tracker real-time.
5. **Supervisor config** agar worker tetap hidup setelah reboot/crash.
