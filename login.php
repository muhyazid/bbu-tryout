<?php
/**
 * Kompatibilitas URL lama. Halaman login utama sekarang adalah index.php.
 */
require_once 'config.php';

if (isset($_SESSION['admin_id'])) redirect('/admin/dashboard.php');
if (isset($_SESSION['peserta_id'])) redirect('/peserta/dashboard.php');

redirect('/index.php');
