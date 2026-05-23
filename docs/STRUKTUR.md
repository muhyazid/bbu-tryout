# BBU Tryout — Struktur Proyek

Sistem ujian tryout (CAT/CBT) lokal untuk **Bharuna Bhakti Utama**, program **SOU** dan **GMDSS**.

---

## Diagram alur singkat

```
index.php (login)
    ├── Admin  → admin/*.php
    └── Peserta → peserta/*.php
              └── ujian.php → peserta/log.php (AJAX)
```

---

## Folder & file

### Akar proyek (`bbu-tryout/`)

| File | Fungsi |
|------|--------|
| **index.php** | Halaman login. Cek username/nomor peserta + password, arahkan ke dashboard admin atau peserta. |
| **config.php** | Memuat semua inti aplikasi (konstanta, helper, session, database). **Wajib** di-`require` di setiap halaman. |
| **session.php** | Memulai session PHP, fungsi `redirect()`, `cekAdmin()`, `cekPeserta()`. |
| **database.php** | Koneksi MySQL (`mysqli`). Ubah host/user/password/database di sini. |
| **logout.php** | Hapus session dan kembali ke login. |
| **config.example.php** | Petunjuk setup (bukan file aktif). |
| **check_schema.php** | Utilitas dev: cetak struktur tabel ke terminal/browser (debug). |

### `includes/` — Kode inti bersama

| File | Fungsi |
|------|--------|
| **constants.php** | `APP_BASE` (URL folder, default `/bbu-tryout`), `APP_ROOT` (path disk). |
| **helpers.php** | `url($path)` — bikin URL aplikasi; `e($text)` — escape HTML aman. |
| **sesi_helper.php** | `cari_sesi_aktif_peserta()` — sesi yang tampil ke peserta hari ini. |

### `assets/css/` — Tampilan

| File | Dipakai oleh |
|------|----------------|
| **style.css** | Dasar: tabel, badge, layout admin lama, variabel warna. |
| **admin.css** | Panel admin: sidebar, kartu statistik, aksi cepat, alert, tombol. |
| **peserta.css** | Area peserta: header, dashboard, halaman ujian, hasil. |

### `admin/` — Panel administrator

| File | Fungsi |
|------|--------|
| **dashboard.php** | Ringkasan: jumlah peserta, soal, sesi aktif, ujian hari ini + aksi cepat. |
| **peserta.php** | CRUD peserta (nama, nomor login, program, angkatan, password). |
| **soal.php** | Bank soal per **materi** (kategori): kartu materi → kelola soal per materi. |
| **sesi.php** | CRUD jadwal ujian (tanggal, jam, durasi, mode latihan/ujian, aktif/nonaktif). |
| **hasil.php** | Rekap nilai, filter, statistik, export CSV. |
| **log.php** | Log aktivitas peserta (pindah tab, keluar fullscreen, dll.). |

#### `admin/partials/` — Potongan HTML admin (jangan buka langsung di browser)

| File | Fungsi |
|------|--------|
| **layout-start.php** | Awal halaman: `<html>`, head, sidebar, `<main>`. Butuh `$admin_page`, `$page_title`. |
| **layout-end.php** | Penutup: `</main></div></body></html>`. |
| **head.php** | Meta, title, link CSS. |
| **sidebar.php** | Menu kiri + tombol keluar. |
| **icons.php** | Fungsi `admin_icon()` — SVG ikon menu. |

### `peserta/` — Area peserta ujian

| File | Fungsi |
|------|--------|
| **dashboard.php** | Beranda: status ujian aktif, tombol mulai, riwayat nilai. |
| **ujian.php** | Halaman ujian (soal, timer, submit), halaman hasil setelah submit. |
| **log.php** | API JSON: mencatat aktivitas (dipanggil JavaScript saat ujian). |

#### `peserta/partials/`

| File | Fungsi |
|------|--------|
| **layout-start.php** | Awal halaman peserta + header. Butuh `$page_title`, opsional `$body_class`. |
| **layout-end.php** | Penutup layout. |
| **head.php** | Meta + CSS peserta. |
| **header.php** | Bar atas: nama peserta, program, keluar. |

---

## Database (MySQL)

Nama database default: **`bbu_tryout`**

Tabel utama (ringkas):

| Tabel | Isi |
|-------|-----|
| `admin` | Akun pengawas/admin. |
| `peserta` | Data peserta + nomor untuk login. |
| `kategori` | Kelompok soal (per SOU/GMDSS). |
| `soal` | Pertanyaan + pilihan A–D + kunci jawaban. |
| `sesi_ujian` | Jadwal & pengaturan satu sesi ujian. |
| `hasil_ujian` | Skor akhir per peserta per sesi. |
| `jawaban` | Detail jawaban per soal. |
| `log_aktivitas` | Jejak aktivitas saat ujian. |

---

## Cara menambah halaman baru

**Admin**

```php
<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

$admin_page = 'dashboard'; // sesuaikan menu
$page_title = 'Judul — BBU Tryout';

include __DIR__ . '/partials/layout-start.php';
?>

    <!-- konten HTML di sini -->

<?php include __DIR__ . '/partials/layout-end.php'; ?>
```

**Peserta**

```php
<?php
require_once dirname(__DIR__) . '/config.php';
cekPeserta();

$page_title = 'Judul halaman';
include __DIR__ . '/partials/layout-start.php';
?>

    <!-- konten -->

<?php include __DIR__ . '/partials/layout-end.php'; ?>
```

---

## Konvensi kode

- Path URL: pakai `url('/admin/dashboard.php')`, jangan hardcode `/bbu-tryout/...`.
- Teks dari database ke HTML: pakai `e($variabel)`.
- Redirect: pakai `redirect('/peserta/dashboard.php')`.
- Jika aplikasi dipindah folder, cukup ubah **`APP_BASE`** di `includes/constants.php`.

---

## Yang tidak di-commit (`.gitignore`)

- `config.php` — boleh berisi rahasia lokal (saat ini hanya memuat include).
- File log/OS/editor.
