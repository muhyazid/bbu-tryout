# BBU Tryout — Struktur Proyek

Sistem ujian tryout (CAT/CBT) lokal untuk **Bharuna Bhakti Utama**, mendukung program **SOU** dan **COC GMDSS** melalui sistem **Paket Program**.

> Dokumen ini adalah versi terbaru, mencakup seluruh perubahan struktural sejak versi awal (login berbasis SOU/GMDSS biner) hingga sistem saat ini (paket program fleksibel, kelompok, nilai per-materi).

---

## Diagram alur singkat

```
index.php (login)
    ├── Admin  → admin/*.php
    └── Peserta → peserta/*.php
              └── ujian.php → peserta/simpan_jawaban.php (AJAX, auto-save)
                            → peserta/cek_status.php (AJAX, polling jeda kelompok)
review.php (diakses bersama oleh admin & peserta, dengan validasi kepemilikan)
```

---

## Folder & file

### Akar proyek (`bbu-tryout/`)

| File | Fungsi |
|------|--------|
| **index.php** | Halaman login. Cek username/nomor peserta + password, arahkan ke dashboard admin atau peserta sesuai sesi. |
| **config.php** | Memuat semua inti aplikasi (konstanta, helper, session, database). **Wajib** di-`require` di setiap halaman. |
| **session.php** | Memulai session PHP, fungsi `redirect()`, `cekAdmin()`, `cekPeserta()`. |
| **database.php** | Koneksi MySQL (`mysqli`), charset `utf8mb4`. Ubah host/user/password/database di sini. |
| **logout.php** | Hapus session dan kembali ke login. |
| **review.php** | Halaman review jawaban pasca-ujian. Diakses bersama oleh peserta (lihat hasil miliknya sendiri, hanya jika status `selesai`) dan admin (lihat hasil siapa saja). |
| **config.example.php** | Petunjuk setup (bukan file aktif). |
| **check_schema.php** | Utilitas dev: cetak struktur tabel ke terminal/browser (debug). |

### `includes/` — Kode inti bersama

| File | Fungsi |
|------|--------|
| **constants.php** | `APP_BASE` (URL folder, default `/bbu-tryout`), `APP_ROOT` (path disk). |
| **helpers.php** | `url($path)`, `e($text)`, `generate_password_acak()`, `hitung_sisa_waktu_detik()`. |
| **sesi_helper.php** | `cari_sesi_aktif_peserta($conn, $paket_id)`, `validasi_sesi_peserta($conn, $sesi_id, $paket_id)` — kini berbasis `paket_id` (int), bukan `jenis` (string). |

### `assets/css/` — Tampilan

| File | Dipakai oleh |
|------|----------------|
| **style.css** | Variabel warna (tema navy korporat), reset, komponen bersama (badge, alert, modal, form). |
| **admin.css** | Panel admin: sidebar, topbar, stat card, materi list, checklist paket. |
| **peserta.css** | Halaman ujian peserta: topbar ujian, soal card, grid navigasi, mode satu-soal-per-layar, halaman hasil. |
| **review.css** | Halaman review jawaban: ringkasan skor, nilai per materi, highlight jawaban benar/salah. |

### `admin/` — Panel administrator

| File | Fungsi |
|------|--------|
| **dashboard.php** | Ringkasan: jumlah peserta, soal, sesi aktif, ujian hari ini + aksi cepat. |
| **soal.php** | Bank soal per **materi** (kategori) — tampilan list (bukan grid), dengan pencarian. Materi tidak lagi terikat `jenis`; keterkaitan ke program diatur lewat `paket.php`. |
| **paket.php** | **[BARU]** Kelola pemetaan materi ke Paket Program (SOU Mualim, SOU Reguler, COC GMDSS, 1 Paket) via checklist. |
| **peserta.php** | CRUD peserta, dikelompokkan 2 level: daftar Angkatan → daftar peserta dalam angkatan. Termasuk fitur buat/hapus Angkatan. |
| **kelompok.php** | **[BARU]** Kelola kelompok peserta per angkatan (untuk kasus ujian bergantian karena keterbatasan resource). Kontrol jeda/lanjutkan kelompok, dengan perhitungan freeze waktu otomatis. |
| **sesi.php** | CRUD jadwal ujian (tanggal, jam, durasi, mode latihan/ujian, aktif/nonaktif), kini menggunakan `paket_id`. |
| **hasil.php** | Rekap nilai, filter (termasuk by paket), statistik, export CSV, link ke review jawaban per peserta. |
| **log.php** | Log aktivitas peserta (pindah tab, keluar fullscreen, dll.) — kolom `jenis` di sini adalah jenis aktivitas, **bukan** terkait Paket Program. |

#### `admin/partials/` — Potongan HTML admin (jangan buka langsung di browser)

| File | Fungsi |
|------|--------|
| **layout-start.php** | Awal halaman: `<html>`, head, sidebar, `<main>`. Butuh `$admin_page`, `$page_title`. |
| **layout-end.php** | Penutup: `</main></div></body></html>`. |
| **head.php** | Meta, title, link CSS (`style.css` + `admin.css`). |
| **sidebar.php** | Menu kiri (Dashboard, Bank Soal, Kelola Paket, Peserta, Kelola Kelompok, Sesi Ujian, Rekap Nilai, Log Aktivitas) + logo BBU + tombol keluar. |
| **icons.php** | Fungsi `admin_icon()` — SVG ikon menu. |

### `peserta/` — Area peserta ujian

| File | Fungsi |
|------|--------|
| **dashboard.php** | Beranda: status ujian (belum ada/tersedia/sedang dikerjakan/dijeda/selesai), tombol mulai/lanjutkan, riwayat nilai dengan link ke review. |
| **ujian.php** | Halaman ujian. `hasil_ujian` dibuat **saat mulai** (bukan saat submit), timer berbasis waktu server (mendukung freeze saat dijeda), mode satu-soal-per-layar dengan grid navigasi per mapel. |
| **simpan_jawaban.php** | **[BARU]** Endpoint AJAX untuk auto-save jawaban secara real-time setiap soal dijawab. Divalidasi ketat terhadap kepemilikan paket/sesi peserta (anti-IDOR). |
| **cek_status.php** | **[BARU]** Endpoint AJAX polling (setiap 15 detik) untuk mendeteksi apakah kelompok peserta sedang dijeda pengawas, memicu logout otomatis di sisi klien. |
| **log.php** | API JSON: mencatat aktivitas (dipanggil JavaScript saat ujian) — kolom `jenis` di sini adalah jenis aktivitas peserta, tidak terkait Paket Program. |

#### `peserta/partials/`

| File | Fungsi |
|------|--------|
| **layout-start.php** | Awal halaman peserta + header. Butuh `$page_title`, opsional `$body_class`. |
| **layout-end.php** | Penutup layout. |
| **head.php** | Meta + CSS peserta. |
| **header.php** | Bar atas: nama peserta, nama paket, keluar. |

---

## Database (MySQL)

Nama database default: **`bbu_tryout`**

### Tabel utama

| Tabel | Isi | Catatan Perubahan |
|-------|-----|--------|
| `admin` | Akun pengawas/admin. | Tidak berubah. |
| `peserta` | Data peserta, kredensial login, `angkatan`, `paket_id`. | Kolom `jenis` **dihapus**, diganti `paket_id` (FK ke `paket_program`). |
| `angkatan` | **[BARU]** Daftar nama angkatan/periode diklat, independen dari ada/tidaknya peserta. | Memungkinkan admin membuat angkatan kosong sebelum ada peserta terdaftar. |
| `paket_program` | **[BARU]** Daftar paket: SOU Mualim, SOU Reguler, COC GMDSS, 1 Paket. | — |
| `paket_kategori` | **[BARU]** Tabel penghubung: materi (kategori) mana saja masuk paket mana. Satu materi bisa dipakai banyak paket. | — |
| `kategori` | Kelompok/materi soal. | Kolom `jenis` **dihapus** — materi tidak lagi terikat langsung ke satu program, keterkaitan diatur via `paket_kategori`. |
| `soal` | Pertanyaan + pilihan A–D + kunci jawaban. | Tambah kolom `bobot` (default 2) untuk mendukung skor berbobot. |
| `sesi_ujian` | Jadwal & pengaturan satu sesi ujian. | Kolom `jenis` **dihapus**, diganti `paket_id` (FK ke `paket_program`). |
| `hasil_ujian` | Skor akhir per peserta per sesi. | Tambah kolom `status_pengerjaan`, `waktu_mulai`, `waktu_pause_mulai`, `total_durasi_pause` untuk mendukung timer server-side dan fitur jeda per kelompok. Baris dibuat **saat mulai ujian**, diupdate saat submit (bukan lagi insert-only). |
| `hasil_per_materi` | **[BARU]** Rincian nilai per kategori/materi per hasil ujian (`total_bobot_didapat`, `total_bobot_maksimal`). | Dipakai untuk tampilan nilai per-materi (SOU) dan halaman review. |
| `jawaban` | Detail jawaban per soal. | Kini di-upsert secara real-time lewat `simpan_jawaban.php`, bukan hanya saat submit akhir. |
| `kelompok_angkatan` | **[BARU]** Kelompok peserta dalam satu angkatan (misal "Kelompok A"), dengan status `berjalan`/`dijeda`. | — |
| `kelompok_anggota` | **[BARU]** Peserta mana masuk kelompok mana. Satu peserta hanya boleh masuk 1 kelompok pada satu waktu (`UNIQUE` pada `peserta_id`). | — |
| `log_aktivitas` | Jejak aktivitas saat ujian (pindah tab, submit, dll). | Tidak berubah — kolom `jenis` di sini adalah jenis aktivitas, bukan program. |

### Skema Ringkas Tabel Baru/Berubah

```sql
-- Angkatan (independen, bisa dibuat kosong)
CREATE TABLE angkatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Paket Program
CREATE TABLE paket_program (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(50) NOT NULL,
  urutan INT DEFAULT 0
);

-- Penghubung Paket <-> Materi
CREATE TABLE paket_kategori (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paket_id INT NOT NULL,
  kategori_id INT NOT NULL,
  FOREIGN KEY (paket_id) REFERENCES paket_program(id) ON DELETE CASCADE,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE CASCADE,
  UNIQUE KEY (paket_id, kategori_id)
);

-- Nilai per materi
CREATE TABLE hasil_per_materi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hasil_ujian_id INT NOT NULL,
  kategori_id INT NOT NULL,
  total_bobot_didapat INT NOT NULL DEFAULT 0,
  total_bobot_maksimal INT NOT NULL DEFAULT 0,
  FOREIGN KEY (hasil_ujian_id) REFERENCES hasil_ujian(id) ON DELETE CASCADE,
  FOREIGN KEY (kategori_id) REFERENCES kategori(id)
);

-- Kelompok per angkatan
CREATE TABLE kelompok_angkatan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  angkatan VARCHAR(50) NOT NULL,
  nama_kelompok VARCHAR(50) NOT NULL,
  status ENUM('berjalan','dijeda') DEFAULT 'berjalan'
);

CREATE TABLE kelompok_anggota (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kelompok_id INT NOT NULL,
  peserta_id INT NOT NULL,
  FOREIGN KEY (kelompok_id) REFERENCES kelompok_angkatan(id) ON DELETE CASCADE,
  FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE CASCADE,
  UNIQUE KEY (peserta_id)
);

-- Kolom tambahan hasil_ujian (timer server-side)
ALTER TABLE hasil_ujian
  ADD COLUMN status_pengerjaan ENUM('belum_mulai','sedang_dikerjakan','dijeda','selesai') DEFAULT 'belum_mulai',
  ADD COLUMN waktu_mulai TIMESTAMP NULL,
  ADD COLUMN waktu_pause_mulai TIMESTAMP NULL,
  ADD COLUMN total_durasi_pause INT DEFAULT 0;

-- Kolom tambahan soal (bobot)
ALTER TABLE soal ADD COLUMN bobot INT DEFAULT 2;
```

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
- **Semua query database wajib prepared statement.** Hindari `$conn->query()` dengan variabel yang di-*interpolate* langsung ke string SQL, kecuali variabel tersebut sudah dipastikan `(int)`.
- **Pola flash message**: untuk aksi hapus/toggle/simpan yang melakukan redirect, gunakan `$_SESSION['flash_msg']` lalu `unset()` setelah dibaca — mencegah pesan sukses muncul berulang saat halaman di-refresh (masalah form resubmission).
- **Validasi kepemilikan di server, bukan di klien.** Setiap endpoint yang menerima ID dari request (soal_id, sesi_id, hasil_id) wajib memvalidasi ulang bahwa data itu benar milik/terkait dengan peserta yang sedang login — jangan percaya begitu saja parameter yang dikirim dari browser.

---

## Catatan Penting Database

### Password
Semua password disimpan dalam format **bcrypt** (`password_hash()` PHP). Jangan ubah manual via phpMyAdmin dengan teks biasa — gunakan `password_hash()` melalui halaman admin, atau script generate hash sementara jika diperlukan reset darurat.

### Hapus Data — Prinsip Umum
Beberapa entitas **tidak bisa dihapus langsung** jika masih memiliki data terkait, untuk mencegah kehilangan data akademik secara tidak sengaja:

| Entitas | Syarat agar bisa dihapus |
|---|---|
| Sesi Ujian | Jika sudah ada `hasil_ujian` terkait, perlu konfirmasi "hapus paksa" (ketik ulang nama sesi) — akan ikut menghapus jawaban & hasil terkait. |
| Angkatan | Hanya bisa dihapus jika **tidak ada peserta** di dalamnya (0 peserta). |
| Peserta | Akan gagal dihapus jika masih memiliki `hasil_ujian` terkait (constraint foreign key). |

### Auto-Save Jawaban
Sejak versi ini, jawaban peserta disimpan **secara real-time** setiap kali soal dijawab (via `simpan_jawaban.php`), bukan hanya sekali di akhir saat submit. Ini penting untuk mendukung fitur jeda per kelompok — peserta yang di-logout paksa karena kelompoknya dijeda tidak akan kehilangan progress jawaban yang sudah dipilih.

### Timer Ujian — Server-Side, Bukan Client-Side
Waktu ujian dihitung berdasarkan `waktu_mulai` dan `total_durasi_pause` yang tersimpan di database (lihat `hitung_sisa_waktu_detik()` di `helpers.php`), bukan murni timer JavaScript di browser. Ini mencegah manipulasi waktu dan mendukung freeze waktu saat kelompok dijeda oleh pengawas.

### Backup Rutin
Lakukan backup setiap hari sebelum ujian, terutama sebelum dan sesudah sesi ujian besar.
Lokasi rekomendasi: `D:\Backup\BBU\bbu_tryout_YYYY_MM_DD.sql`

---

## Riwayat Perubahan Besar

| Tahap | Perubahan |
|---|---|
| Awal | Sistem dasar: login SOU/GMDSS biner, bank soal, sesi ujian, hasil ujian sederhana. |
| Keamanan | Migrasi password MD5 → bcrypt, prepared statement menyeluruh, perbaikan charset `utf8mb4`. |
| Tema & UX | Redesain tampilan korporat (navy theme), mode satu-soal-per-layar dengan grid navigasi per mapel. |
| Login Custom | Username/password otomatis dari nama peserta + nomor absen. |
| Paket Program | Migrasi total dari `jenis` (SOU/GMDSS) ke sistem `paket_program` yang fleksibel (SOU Mualim, SOU Reguler, COC GMDSS, 1 Paket). |
| Nilai per Materi | Penambahan bobot soal dan tabel `hasil_per_materi` untuk nilai granular per kategori. |
| Review Jawaban | Halaman `review.php` mirip Google Form Quiz, dengan validasi status `selesai` sebelum bisa diakses peserta. |
| Kelompok & Jeda Sesi | Sistem `kelompok_angkatan`/`kelompok_anggota` untuk ujian bergantian, timer server-side dengan dukungan freeze saat dijeda. |
| Hardening Keamanan | Perbaikan celah IDOR pada endpoint auto-save jawaban, validasi status ujian untuk mencegah replay/double-submit. |