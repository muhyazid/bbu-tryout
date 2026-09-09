# BBU Tryout — Computer Assisted Test (CAT) System

Sistem ujian tryout berbasis web untuk **Bharuna Bhakti Utama**, mendukung program **SOU** (Ship's Officer Unlimited) dan **COC GMDSS**. Dibangun untuk menggantikan sistem tryout berbasis Google Form yang sebelumnya digunakan, karena keterbatasan performa, kontrol, dan fitur.

---

## Daftar Isi

1. [Latar Belakang](#latar-belakang)
2. [Fitur Utama](#fitur-utama)
3. [Teknologi](#teknologi)
4. [Struktur Program (Paket)](#struktur-program-paket)
5. [Instalasi & Setup](#instalasi--setup)
6. [Konfigurasi Jaringan LAN](#konfigurasi-jaringan-lan)
7. [Alur Penggunaan Singkat](#alur-penggunaan-singkat)
8. [Keamanan](#keamanan)
9. [Dokumen Terkait](#dokumen-terkait)
10. [Kontak & Maintenance](#kontak--maintenance)

---

## Latar Belakang

Sebelumnya, tryout SOU dan GMDSS di BBU dilakukan menggunakan **Google Form**, dengan kendala:

- Sering **lag/freeze** saat diakses 20-30 peserta secara bersamaan (bergantung penuh pada internet)
- Tidak ada kontrol timer per soal atau per sesi yang fleksibel
- Shuffle soal terbatas, tidak bisa dikelompokkan per kategori/materi
- Tidak ada dashboard monitoring real-time untuk pengawas
- Data tersimpan di cloud pihak ketiga (Google), bukan milik internal BBU

Sistem ini dibangun sebagai solusi **LAN lokal** (tanpa ketergantungan internet), berjalan di atas **PHP native + MySQL** via XAMPP, dengan kontrol penuh oleh admin/kurpel BBU.

---

## Fitur Utama

### Untuk Peserta
- Login menggunakan username & password custom (dari nama + nomor absen)
- Ujian dengan tampilan satu-soal-per-layar, navigasi via grid nomor soal
- Timer otomatis dengan sinkronisasi waktu server (anti-manipulasi waktu di sisi klien)
- Auto-save jawaban secara real-time (tidak hilang meski koneksi terputus)
- Deteksi kecurangan dasar (keluar fullscreen, pindah tab)
- Halaman review jawaban pasca-ujian (mirip Google Form Quiz), menampilkan jawaban benar/salah beserta kunci
- Dukungan jeda ujian per kelompok (untuk kasus keterbatasan tempat/laptop) — waktu otomatis freeze dan bisa dilanjutkan dari perangkat manapun

### Untuk Admin / Kurpel
- Dashboard ringkasan (jumlah peserta, soal, sesi aktif)
- Manajemen Bank Soal per materi, dengan pencarian
- Manajemen Paket Program — mapping fleksibel materi ke tiap jenis program (SOU Mualim, SOU Reguler, COC GMDSS, 1 Paket)
- Manajemen Peserta, dikelompokkan per Angkatan/Periode
- Manajemen Sesi Ujian (jadwal, durasi, mode latihan/ujian)
- Manajemen Kelompok — untuk membagi peserta yang ujian bergantian karena keterbatasan resource, dengan kontrol jeda/lanjutkan per kelompok
- Rekap Nilai dengan filter, export CSV, dan akses ke review jawaban tiap peserta
- Nilai otomatis dihitung per-materi (untuk SOU) atau per-sesi (untuk COC GMDSS), dengan bobot soal yang dapat disesuaikan
- Log aktivitas peserta selama ujian

---

## Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | PHP Native (tanpa framework) |
| Database | MySQL / MariaDB (via XAMPP) |
| Frontend | HTML, CSS custom (tema navy korporat), Vanilla JavaScript |
| Server | Apache (bundled XAMPP) |
| Font | Inter |
| Icon | Font Awesome 6 |

Tidak menggunakan framework (Laravel, dsb) — dipertahankan sebagai PHP native mengingat skala proyek (LAN internal, 1 pengembang) tidak membutuhkan overhead framework.

---

## Struktur Program (Paket)

Sistem menggunakan konsep **Paket Program**, bukan sekadar SOU/GMDSS biner. Satu materi (kategori soal) bisa dipakai bersama oleh beberapa paket sekaligus, diatur lewat menu **Kelola Paket**.

| Paket | Cakupan Materi |
|---|---|
| **SOU Mualim** | Peraturan Radio, Perjanjian Internasional, Telephony Radio, GMDSS, Service Document, Teknik Radio, B. Inggris |
| **SOU Reguler** | Semua materi SOU Mualim + Teknik Listrik, Pancasila, IBT |
| **COC GMDSS** | GMDSS saja |
| **1 Paket** | Semua materi (gabungan SOU Reguler + GMDSS, tanpa duplikasi) |

Nilai SOU (Mualim/Reguler) dan 1 Paket ditampilkan **per-materi**. Nilai COC GMDSS ditampilkan **per-sesi** (karena hanya 1 materi).

Detail lengkap struktur database ada di `STRUKTUR.md`.

---

## Instalasi & Setup

### Prasyarat
- XAMPP (Apache + MySQL + PHP 8.2.x)
- Browser modern (Chrome/Edge/Firefox) di seluruh laptop server & klien

### Langkah Instalasi

1. **Copy folder project** ke direktori XAMPP:
   ```
   C:\xampp\htdocs\bbu-tryout\
   ```

2. **Import database** via phpMyAdmin:
   - Buka `http://localhost/phpmyadmin`
   - Buat database baru bernama `bbu_tryout`
   - Import file `.sql` terbaru (lihat folder `docs/database/`)

3. **Konfigurasi koneksi database** — cek isi `database.php`:
   ```php
   $host     = '127.0.0.1';
   $dbname   = 'bbu_tryout';
   $username = 'root';
   $password = '';
   ```

4. **Sesuaikan path aplikasi** (jika folder dipindah dari `bbu-tryout`) — edit `includes/constants.php`:
   ```php
   define('APP_BASE', '/bbu-tryout');
   ```

5. **Nyalakan Apache & MySQL** dari XAMPP Control Panel.

6. **Akses aplikasi**:
   - Dari server sendiri: `http://localhost/bbu-tryout`
   - Dari laptop klien (LAN): `http://[IP-statis-server]/bbu-tryout`

Login admin default (ganti password setelah instalasi pertama):
```
Username: admin
```
*(password disimpan ter-hash di database; reset lewat phpMyAdmin dengan `password_hash()` jika lupa — lihat catatan di STRUKTUR.md)*

---

## Konfigurasi Jaringan LAN

Sistem ini didesain untuk jaringan **lokal tertutup**, tanpa akses internet saat ujian berlangsung. Poin penting:

- Laptop **server** harus menggunakan **IP statis** agar tidak berubah setiap restart jaringan
- Semua laptop **klien** (peserta) wajib berada di jaringan/subnet yang **sama** dengan server
- Firewall Windows di laptop server harus mengizinkan koneksi masuk untuk Apache (port 80)
- Konfigurasi Apache (`httpd.conf`) harus mengizinkan akses dari IP manapun di jaringan (`Require all granted`), bukan hanya localhost

Detail troubleshooting jaringan ada di `SOP_OPERASIONAL.md`.

---

## Alur Penggunaan Singkat

```
1. Admin login → buat/kelola Bank Soal per materi
2. Admin assign materi ke Paket Program yang sesuai (menu Kelola Paket)
3. Admin tambah data Peserta (dikelompokkan per Angkatan)
4. [Opsional] Admin buat Kelompok jika peserta perlu ujian bergantian
5. Admin buat Sesi Ujian, tentukan paket, jadwal, durasi, jumlah soal
6. Admin aktifkan sesi pada hari-H
7. Peserta login dengan kredensial masing-masing → kerjakan ujian
8. [Jika perlu] Admin jeda/lanjutkan kelompok tertentu sesuai kondisi lapangan
9. Peserta submit → nilai otomatis terhitung, bisa dilihat di Riwayat Nilai
10. Peserta/Admin bisa membuka halaman Review Jawaban untuk evaluasi
11. Admin export Rekap Nilai (CSV) untuk arsip/laporan
```

---

## Keamanan

Sistem telah melalui beberapa iterasi audit dan perbaikan keamanan:

- Password disimpan menggunakan `password_hash()` (bcrypt), bukan plaintext atau MD5
- Seluruh query database menggunakan **prepared statements**
- Validasi **kepemilikan data** di sisi server (mencegah IDOR) — soal, sesi, dan hasil ujian divalidasi terhadap `paket_id` dan `peserta_id` yang login, bukan sekadar mempercayai parameter dari klien
- Pencegahan replay/double-submit berdasarkan status pengerjaan ujian
- Proteksi terhadap penghapusan data penting (sesi/angkatan dengan riwayat nilai tidak bisa dihapus langsung, perlu konfirmasi eksplisit)

**Catatan:** sistem ini dirancang untuk deployment **LAN tertutup tanpa akses internet**. Implementasi CSRF token penuh belum diterapkan karena risiko dinilai rendah pada konteks ini — jika sistem dikembangkan untuk akses internet/WAN di masa depan, ini perlu ditambahkan.

---

## Dokumen Terkait

| Dokumen | Isi |
|---|---|
| `STRUKTUR.md` | Struktur folder, file, dan skema database lengkap |
| `SOP_OPERASIONAL.md` | Panduan operasional harian untuk admin/pengawas |
| `diagram_alur_sistem` | Diagram visual alur sistem (admin & peserta) |

---

## Kontak & Maintenance

Sistem ini dikembangkan secara internal. Untuk pertanyaan teknis, perubahan fitur, atau maintenance lanjutan, hubungi penanggung jawab IT BBU.

**Rekomendasi maintenance rutin:**
- Backup database (export SQL) sebelum dan sesudah setiap sesi ujian besar
- Cek ruang penyimpanan server secara berkala
- Update dokumentasi ini setiap ada perubahan fitur signifikan