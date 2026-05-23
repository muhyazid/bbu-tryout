<?php
/**
 * Awal layout admin. Set sebelum include:
 *   $admin_page  — kunci menu aktif (dashboard|soal|peserta|sesi|hasil|log)
 *   $page_title  — judul tab browser
 *   $extra_head  — (opsional) HTML tambahan di <head>
 */
$admin_page = $admin_page ?? '';
$page_title = $page_title ?? 'Panel Admin — BBU Tryout';
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/head.php'; ?>
    <?= $extra_head ?? '' ?>
</head>
<body>
<div class="admin-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <span class="topbar-breadcrumb"><?= $topbar_breadcrumb ?? '' ?></span>
      <span class="topbar-title"><?= $topbar_title ?? '' ?></span>
    </div>
    <div class="topbar-right">
      <span class="topbar-date" id="topbar-date"></span>
      <div class="topbar-sep"></div>
      <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['admin_nama'] ?? 'A', 0, 2)) ?></div>
      <span class="topbar-name"><?= e($_SESSION['admin_nama'] ?? 'Admin') ?></span>
    </div>
  </div>
  <script>
    const d = new Date();
    const hari   = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const bulan  = ['Januari','Februari','Maret','April','Mei','Juni','Juli',
                    'Agustus','September','Oktober','November','Desember'];
    document.getElementById('topbar-date').textContent =
      hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
  </script>
  <div class="main-inner">