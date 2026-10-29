<?php
/**
 * Awal layout admin. Set sebelum include:
 *   $admin_page        — kunci menu aktif (dashboard|soal|paket|peserta|kelompok|sesi|hasil|log)
 *   $page_title        — judul tab browser
 *   $topbar_breadcrumb — (opsional) teks breadcrumb kecil di topbar
 *   $topbar_title      — (opsional) judul besar di topbar
 *   $extra_head        — (opsional) HTML tambahan di <head>
 */
$admin_page = $admin_page ?? '';
$page_title = $page_title ?? 'Panel Admin';
require_once __DIR__ . '/icons.php';

$nama_admin_tb = $_SESSION['admin_nama'] ?? 'Admin';
$inisial_tb    = strtoupper(mb_substr($nama_admin_tb, 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/head.php'; ?>
    <?= $extra_head ?? '' ?>
    <style>
      /* ===== Topbar ===== */
      .tb {
        position:sticky; top:0; z-index:40;
        display:flex; justify-content:space-between; align-items:center; gap:16px;
        padding:14px 28px; background:rgba(255,255,255,.88);
        backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px);
        border-bottom:1px solid #e6eaf2;
        font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
      }
      .tb-left { display:flex; align-items:center; gap:14px; min-width:0; }
      .tb-titles { display:flex; flex-direction:column; min-width:0; }
      .tb-crumb { font-size:12px; color:#718096; }
      .tb-title {
        font-size:16px; font-weight:600; color:#1a202c;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
      }
      .tb-right { display:flex; align-items:center; gap:14px; }
      .tb-date { font-size:13px; color:#718096; white-space:nowrap; }
      .tb-sep { width:1px; height:24px; background:#e2e8f0; }
      .tb-user { display:flex; align-items:center; gap:10px; }
      .tb-avatar {
        width:34px; height:34px; border-radius:50%; flex-shrink:0;
        display:grid; place-items:center; color:#fff; font-size:12px; font-weight:700;
        background:linear-gradient(135deg,#1e3a5f,#2c5282);
      }
      .tb-name { font-size:14px; font-weight:600; color:#1a202c; white-space:nowrap; }

      .tb-toggle {
        display:none; place-items:center; width:38px; height:38px; flex-shrink:0;
        border:1px solid #e2e8f0; border-radius:10px; background:#fff; color:#1e3a5f; cursor:pointer;
      }
      .tb-toggle:hover { background:#f5f7fb; }
      .tb-toggle svg { width:20px; height:20px; }

      /* Backdrop untuk sidebar mobile (default tersembunyi) */
      .sb-backdrop { display:none; }

      /* ===== Responsif ===== */
      @media (max-width:900px) {
        /* Sidebar jadi overlay yang bisa dibuka-tutup, bukan mode ikon */
        body .sidebar {
          position:fixed; top:0; left:0; bottom:0;
          width:260px; height:100vh; z-index:60;
          transform:translateX(-100%);
          transition:transform .25s ease;
          box-shadow:none;
        }
        body.sb-open .sidebar {
          transform:none;
          box-shadow:8px 0 30px rgba(15,23,42,.25);
        }
        body .sidebar .sb-brand-text,
        body .sidebar .sb-group,
        body .sidebar .sb-text { display:revert; }
        body .sidebar .sb-brand { justify-content:flex-start; padding:4px 8px 20px; }
        body .sidebar .sb-link,
        body .sidebar .sb-logout { justify-content:flex-start; padding:10px 12px; }
        body .sidebar .sb-link.active::before { left:-14px; }

        body.sb-open .sb-backdrop {
          display:block; position:fixed; inset:0; z-index:55;
          background:rgba(15,23,42,.45);
        }
        .tb-toggle { display:grid; }
        .tb { padding:12px 16px; }
      }
      @media (max-width:700px) {
        .tb-date, .tb-sep, .tb-name { display:none; }
      }
    </style>
</head>
<body>
<div class="admin-wrapper">
<div class="sb-backdrop" id="sb-backdrop"></div>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
  <header class="tb">
    <div class="tb-left">
      <button type="button" class="tb-toggle" id="tb-toggle" aria-label="Buka menu" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div class="tb-titles">
        <span class="tb-crumb"><?= $topbar_breadcrumb ?? '' ?></span>
        <span class="tb-title"><?= $topbar_title ?? '' ?></span>
      </div>
    </div>

    <div class="tb-right">
      <span class="tb-date" id="tb-date"></span>
      <div class="tb-sep"></div>
      <div class="tb-user">
        <div class="tb-avatar"><?= e($inisial_tb) ?></div>
        <span class="tb-name"><?= e($nama_admin_tb) ?></span>
      </div>
    </div>
  </header>

  <script>
    (function () {
      // Tanggal di topbar
      var d = new Date();
      var hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
      var bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli',
                   'Agustus','September','Oktober','November','Desember'];
      document.getElementById('tb-date').textContent =
        hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();

      // Buka-tutup sidebar di layar kecil
      var body     = document.body;
      var toggle   = document.getElementById('tb-toggle');
      var backdrop = document.getElementById('sb-backdrop');

      function setOpen(open) {
        body.classList.toggle('sb-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      }

      toggle.addEventListener('click', function () {
        setOpen(!body.classList.contains('sb-open'));
      });
      backdrop.addEventListener('click', function () { setOpen(false); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
      });
      // Tutup otomatis setelah memilih menu di layar kecil
      document.querySelectorAll('.sidebar a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
      });
    })();
  </script>

  <div class="main-inner">