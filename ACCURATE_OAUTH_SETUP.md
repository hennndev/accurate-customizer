# Setup Accurate OAuth

Panduan lengkap untuk menghubungkan aplikasi ini dengan Accurate Online melalui OAuth 2.0.

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Mendaftarkan Aplikasi di Accurate Developer Console](#2-mendaftarkan-aplikasi-di-accurate-developer-console)
3. [Konfigurasi Environment (.env)](#3-konfigurasi-environment-env)
4. [Alur OAuth (Flow Setelah Setup)](#4-alur-oauth-flow-setelah-setup)
5. [Scope yang Digunakan](#5-scope-yang-digunakan)
6. [Troubleshooting](#6-troubleshooting)

---

## 1. Prasyarat

- Akun Accurate Online aktif (bisa Trial atau berlangganan)
- Akses ke [Accurate Developer Portal](https://developer.accurate.id)
- Aplikasi sudah berjalan dan bisa diakses dari URL publik (untuk Callback URL)
- File `.env` sudah ter-copy dari `.env.example`

---

## 2. Mendaftarkan Aplikasi di Accurate Developer Console

### Langkah-langkah:

**1. Login ke Developer Portal**
- Buka [https://developer.accurate.id](https://developer.accurate.id)
- Login menggunakan akun Accurate Online Anda

**2. Buat Aplikasi Baru**
- Masuk ke menu **My Apps** → klik **Create New App** (atau **+ Add Application**)
- Isi detail berikut:

  | Field | Value |
  |-------|-------|
  | App Name | Nama aplikasi bebas, misal: `Accurate Customizer` |
  | App Type | **Web Application** |
  | Redirect URI | `https://yourdomain.com/accurate/callback` |

  > **Penting:** Redirect URI harus **persis sama** dengan `APP_URL` di `.env` ditambah `/accurate/callback`.
  > Contoh: jika `APP_URL=https://app.example.com` maka Redirect URI = `https://app.example.com/accurate/callback`

**3. Simpan Client ID dan Client Secret**
- Setelah aplikasi dibuat, Developer Portal akan menampilkan:
  - **Client ID** → salin, ini untuk `ACCURATE_CLIENT_ID`
  - **Client Secret** → salin sekarang, biasanya hanya tampil sekali → ini untuk `ACCURATE_CLIENT_SECRET`

**4. Catat API URL**
- API URL Accurate Online adalah: `https://account.accurate.id`

---

## 3. Konfigurasi Environment (.env)

Buka file `.env` di root project, lalu isi tiga variabel berikut:

```dotenv
# ACCURATE
ACCURATE_API_URL=https://account.accurate.id
ACCURATE_CLIENT_ID=isi_dengan_client_id_dari_developer_portal
ACCURATE_CLIENT_SECRET=isi_dengan_client_secret_dari_developer_portal
```

Pastikan juga `APP_URL` sudah sesuai dengan domain yang didaftarkan sebagai Redirect URI:

```dotenv
APP_URL=https://yourdomain.com
```

Setelah mengubah `.env`, jalankan:

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 4. Alur OAuth (Flow Setelah Setup)

Berikut alur koneksi setelah konfigurasi selesai:

```
1. User masuk ke halaman Settings → Accurate
        ↓
2. Klik tombol "Hubungkan ke Accurate"
        ↓
3. Aplikasi redirect ke:
   {ACCURATE_API_URL}/api/authorize.do
   ?client_id={ACCURATE_CLIENT_ID}
   &response_type=code
   &redirect_uri={APP_URL}/accurate/callback
   &scope=...
   &state={random_string}
        ↓
4. User login/authorize di halaman Accurate Online
        ↓
5. Accurate redirect kembali ke:
   {APP_URL}/accurate/callback?code=AUTH_CODE&state=...
        ↓
6. Aplikasi tukar AUTH_CODE → Access Token + Refresh Token
   via POST {ACCURATE_API_URL}/oauth/token
   (Authorization: Basic base64(client_id:client_secret))
        ↓
7. Token disimpan di session Laravel:
   - accurate_access_token
   - accurate_refresh_token
        ↓
8. User diarahkan ke halaman pilih database Accurate
```

### Route yang Terlibat

| Route | URL | Keterangan |
|-------|-----|------------|
| `accurate.auth` | `GET /accurate/auth` | Inisiasi OAuth, redirect ke Accurate |
| `accurate.callback` | `GET /accurate/callback` | Terima auth_code, tukar ke token |
| `accurate.disconnect` | `POST /accurate/disconnect` | Hapus session token |
| `database.selection` | `GET /select-database` | Pilih database Accurate |

---

## 5. Scope yang Digunakan

Scope dikonfigurasi di `AccurateController@redirectToAccurate`. Scope yang diperlukan harus dimintakan akses saat membuat aplikasi di Developer Portal.

Scope yang digunakan saat ini:

```
purchase_order_view
delivery_order_view
vendor_view
```

> Jika ada modul tambahan yang perlu diakses (misalnya sales invoice, item, dll), tambahkan scope yang sesuai di dua tempat:
> 1. `app/Http/Controllers/AccurateController.php` pada method `redirectToAccurate()` (parameter `scope`)
> 2. Halaman aplikasi di Developer Portal — centang scope yang sama

Daftar scope lengkap tersedia di dokumentasi resmi Accurate:
[https://developer.accurate.id/docs/scope](https://developer.accurate.id/docs/scope)

---

## 6. Troubleshooting

### ❌ `Invalid state` saat callback

**Penyebab:** Session expired atau cookie tidak terbaca saat redirect kembali dari Accurate.

**Solusi:**
- Pastikan `SESSION_DRIVER` di `.env` bukan `array` (gunakan `file` atau `database`)
- Pastikan `APP_URL` di `.env` dan Redirect URI di Developer Portal **persis sama** (termasuk `https://` vs `http://`), tidak ada trailing slash berbeda

---

### ❌ `Gagal mendapatkan token` / `401 Unauthorized`

**Penyebab:** `ACCURATE_CLIENT_ID` atau `ACCURATE_CLIENT_SECRET` salah.

**Solusi:**
- Verifikasi ulang nilai di `.env` dengan yang ada di Developer Portal
- Jalankan `php artisan config:clear` setelah mengubah `.env`

---

### ❌ `Redirect URI mismatch`

**Penyebab:** Redirect URI yang dikirim aplikasi tidak cocok dengan yang terdaftar di Developer Portal.

**Solusi:**
- Buka Developer Portal → edit aplikasi → pastikan Redirect URI adalah:
  `{APP_URL}/accurate/callback`
- Cek `APP_URL` di `.env` tidak ada trailing slash: `https://example.com` ✅ bukan `https://example.com/` ❌

---

### ❌ `Tidak dapat terhubung ke server Accurate`

**Penyebab:** Server tidak bisa reach `ACCURATE_API_URL`, atau sedang maintenance.

**Solusi:**
- Cek koneksi server ke internet: `curl https://account.accurate.id`
- Pastikan `ACCURATE_API_URL` tidak ada trailing slash: `https://account.accurate.id` ✅
- Cek log di `storage/logs/laravel.log` untuk error `ACCURATE_OAUTH_CONNECTION_ERROR`

---

### ❌ Token valid tapi database tidak muncul

**Penyebab:** Akun Accurate tidak memiliki database aktif, atau scope tidak mencukupi.

**Solusi:**
- Login langsung ke Accurate Online, pastikan minimal ada 1 database yang aktif
- Pastikan scope di `AccurateController` sudah sesuai dengan yang dicentang di Developer Portal
