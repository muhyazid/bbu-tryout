# BBU Tryout

Aplikasi ujian tryout berbasis web (PHP + MySQL) untuk pelatihan maritim Bharuna Bhakti Utama.

## Menjalankan di XAMPP

1. Pastikan **Apache** dan **MySQL** aktif.
2. Import database `bbu_tryout` ke phpMyAdmin.
3. Sesuaikan koneksi di `database.php` jika perlu.
4. Buka: `http://localhost/bbu-tryout/`

## Dokumentasi struktur

Penjelasan lengkap setiap file dan folder ada di:

**[docs/STRUKTUR.md](docs/STRUKTUR.md)**

## Struktur folder (ringkas)

```
bbu-tryout/
├── index.php          Login
├── config.php         Bootstrap aplikasi
├── session.php        Auth & redirect
├── database.php       MySQL
├── includes/          Konstanta & helper
├── assets/css/        Stylesheet
├── admin/             Panel admin
├── peserta/           Area peserta ujian
└── docs/              Dokumentasi
```
