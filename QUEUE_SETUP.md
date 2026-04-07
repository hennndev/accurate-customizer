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
