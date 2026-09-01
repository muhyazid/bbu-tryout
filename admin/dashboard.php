<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

$stat = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM peserta)                                    AS total_peserta,
        (SELECT COUNT(*) FROM soal)                                       AS total_soal,
        (SELECT COUNT(*) FROM sesi_ujian WHERE status='aktif')            AS sesi_aktif,
        (SELECT COUNT(*) FROM hasil_ujian WHERE DATE(submitted_at)=CURDATE()) AS ujian_hari_ini
")->fetch_assoc();

$total_peserta  = (int) $stat['total_peserta'];
$total_soal     = (int) $stat['total_soal'];
$sesi_aktif     = (int) $stat['sesi_aktif'];
$ujian_hari_ini = (int) $stat['ujian_hari_ini'];
$sesi_list = $conn->query('
    SELECT s.*, pp.nama AS paket_nama
    FROM sesi_ujian s
    JOIN paket_program pp ON s.paket_id = pp.id
    ORDER BY s.created_at DESC LIMIT 5
');

$admin_page = 'dashboard';
$page_title = 'Dashboard Admin';
$nama_admin = e($_SESSION['admin_nama'] ?? 'Admin');
$topbar_breadcrumb = 'Dashboard';
$topbar_title      = 'Selamat datang, ' . $nama_admin;

include __DIR__ . '/partials/layout-start.php';
?>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('peserta') ?></div>
        <div class="stat-info">
          <h3><?= $total_peserta ?></h3>
          <p>Total peserta</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('soal') ?></div>
        <div class="stat-info">
          <h3><?= $total_soal ?></h3>
          <p>Bank soal</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('sesi') ?></div>
        <div class="stat-info">
          <h3><?= $sesi_aktif ?></h3>
          <p>Sesi aktif</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('hasil') ?></div>
        <div class="stat-info">
          <h3><?= $ujian_hari_ini ?></h3>
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
              <td style="font-weight:500;"><?= e($s['nama_sesi']) ?></td>
              <td><span class="badge badge-paket"><?= e($s['paket_nama']) ?></span></td>
              <td><span class="badge badge-<?= e($s['status']) ?>"><?= ucfirst($s['status']) ?></span></td>
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

<?php include __DIR__ . '/partials/layout-end.php'; ?>
