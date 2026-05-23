<?php
require_once '../config.php';
cekAdmin();
require_once __DIR__ . '/partials/icons.php';

$total_peserta  = $conn->query("SELECT COUNT(*) as n FROM peserta")->fetch_assoc()['n'];
$total_soal     = $conn->query("SELECT COUNT(*) as n FROM soal")->fetch_assoc()['n'];
$sesi_aktif     = $conn->query("SELECT COUNT(*) as n FROM sesi_ujian WHERE status='aktif'")->fetch_assoc()['n'];
$ujian_hari_ini = $conn->query("SELECT COUNT(*) as n FROM hasil_ujian WHERE DATE(submitted_at)=CURDATE()")->fetch_assoc()['n'];
$sesi_list = $conn->query("SELECT * FROM sesi_ujian ORDER BY created_at DESC LIMIT 5");

$admin_page = 'dashboard';
$page_title = 'Dashboard — BBU Tryout';
$nama_admin = htmlspecialchars($_SESSION['admin_nama'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="admin-wrapper">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">

    <header class="page-header">
      <h1>Selamat datang, <?= $nama_admin ?></h1>
      <p>Ringkasan sistem — Bharuna Bhakti Utama</p>
    </header>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('peserta') ?></div>
        <div class="stat-info">
          <h3><?= (int)$total_peserta ?></h3>
          <p>Total peserta</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('soal') ?></div>
        <div class="stat-info">
          <h3><?= (int)$total_soal ?></h3>
          <p>Bank soal</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('sesi') ?></div>
        <div class="stat-info">
          <h3><?= (int)$sesi_aktif ?></h3>
          <p>Sesi aktif</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('hasil') ?></div>
        <div class="stat-info">
          <h3><?= (int)$ujian_hari_ini ?></h3>
          <p>Ujian hari ini</p>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <section class="card">
        <div class="card-header"><h3>Aksi cepat</h3></div>
        <div class="card-body" style="padding-top:8px;padding-bottom:8px;">
          <nav class="action-list">
            <a href="peserta.php?action=tambah" class="action-link">
              Tambah peserta <span>Data login peserta</span>
            </a>
            <a href="soal.php?action=tambah" class="action-link">
              Input soal <span>Bank soal per kategori</span>
            </a>
            <a href="sesi.php?action=tambah" class="action-link">
              Buat sesi ujian <span>Jadwal &amp; durasi</span>
            </a>
            <a href="hasil.php" class="action-link">
              Lihat rekap nilai <span>Hasil semua peserta</span>
            </a>
          </nav>
        </div>
      </section>

      <section class="card">
        <div class="card-header">
          <h3>Sesi ujian terbaru</h3>
          <a href="sesi.php" class="link-muted">Lihat semua</a>
        </div>
        <?php if ($sesi_list->num_rows > 0): ?>
        <table>
          <thead>
            <tr><th>Nama sesi</th><th>Jenis</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php while ($s = $sesi_list->fetch_assoc()): ?>
            <tr>
              <td style="font-weight:500;"><?= htmlspecialchars($s['nama_sesi']) ?></td>
              <td><span class="badge badge-<?= strtolower($s['jenis']) ?>"><?= htmlspecialchars($s['jenis']) ?></span></td>
              <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
          <p>Belum ada sesi ujian.</p>
          <a href="sesi.php?action=tambah" class="btn-link">Buat sesi pertama</a>
        </div>
        <?php endif; ?>
      </section>
    </div>

  </main>
</div>
</body>
</html>
