<?php
/**
 * Contoh setup — salin menjadi config.php TIDAK perlu jika struktur default dipakai.
 *
 * Saat ini config.php hanya memuat:
 *   includes/constants.php  → APP_BASE, APP_ROOT
 *   includes/helpers.php    → url(), e()
 *   session.php             → login & redirect
 *   database.php            → koneksi MySQL
 *
 * Untuk mengubah database, edit file database.php langsung
 * (atau salin database.php → database.local.php dan sesuaikan config.php).
 */

// Contoh override base URL jika tidak di /bbu-tryout:
// define('APP_BASE', '/nama-folder-anda');
