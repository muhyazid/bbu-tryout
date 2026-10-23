<?php
require_once '../config.php';
cekAdmin();

// Filter
$fp  = (int)($_GET['peserta_id'] ?? 0);
$fj  = $_GET['jenis'] ?? '';
$fs  = (int)($_GET['sesi_id'] ?? 0);

$where = "WHERE 1=1";
if ($fp) $where .= " AND l.peserta_id=$fp";
if ($fj) $where .= " AND l.jenis='$fj'";
if ($fs) $where .= " AND l.sesi_id=$fs";

$logs = $conn->query("
    SELECT l.*, p.nama, p.nomor_peserta, s.nama_sesi
    FROM log_aktivitas l
    LEFT JOIN peserta    p ON l.peserta_id = p.id
    LEFT JOIN sesi_ujian s ON l.sesi_id    = s.id
    $where
    ORDER BY l.waktu DESC
    LIMIT 300
");

// Ringkasan per peserta (pindah tab & keluar fullscreen)
$ringkasan = $conn->query("
    SELECT p.nama, p.nomor_peserta,
           SUM(l.jenis='pindah_tab')         as tab,
           SUM(l.jenis='keluar_fullscreen')   as fs,
           s.nama_sesi
    FROM log_aktivitas l
    JOIN peserta    p ON l.peserta_id = p.id
    JOIN sesi_ujian s ON l.sesi_id    = s.id
    WHERE l.jenis IN ('pindah_tab','keluar_fullscreen')
    GROUP BY l.peserta_id, l.sesi_id
    ORDER BY SUM(l.jenis='pindah_tab') + SUM(l.jenis='keluar_fullscreen') DESC
    LIMIT 20
");

$peserta_opt = $conn->query("SELECT id, nama, nomor_peserta FROM peserta ORDER BY nama");
$sesi_opt    = $conn->query("SELECT id, nama_sesi FROM sesi_ujian ORDER BY tanggal DESC");

$admin_page = 'log';
$page_title = 'Log Aktivitas';

$log_labels = [
    'pindah_tab'        => 'Pindah tab',
    'keluar_fullscreen' => 'Keluar layar penuh',
    'submit'            => 'Submit ujian',
    'login'             => 'Login',
    'logout'            => 'Logout',
];

$topbar_breadcrumb = 'Log Aktivitas';
$topbar_title      = 'Log Aktivitas';

include __DIR__ . '/partials/layout-start.php';
?>

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2>Log Aktivitas</h2>
        <p>Catatan aktivitas peserta selama ujian. Gunakan untuk memantau, lalu tindak lanjuti sesuai kebijakan.</p>
      </div>
      <a href="<?= url('/admin/log.php') ?>" class="ps-tambah">
        <i class="fas fa-rotate-right"></i> Muat ulang
      </a>
    </div>

    <?php if ($ringkasan->num_rows > 0): ?>
    <section class="pk-panel lg-perhatian">
      <header class="pk-panel-head">
        <div class="pk-panel-judul">
          <h2>Perlu perhatian</h2>
          <p>Peserta dengan aktivitas keluar halaman atau layar penuh terbanyak, diurutkan dari yang tertinggi.</p>
        </div>
      </header>

      <ul class="ps-list lg-list">
        <?php
        $warna_daftar = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'];
        while ($r = $ringkasan->fetch_assoc()):
            $total_sus = (int)$r['tab'] + (int)$r['fs'];
            $tingkat   = $total_sus >= 5 ? 'tinggi' : ($total_sus >= 2 ? 'sedang' : 'rendah');
            $inisial   = mb_strtoupper(mb_substr(trim($r['nama']), 0, 2));
            $warna     = $warna_daftar[crc32($r['nomor_peserta']) % 6];
        ?>
        <li class="ps-row">
          <div class="ps-row-link ps-row-statis lg-row-perhatian">
            <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= htmlspecialchars($inisial) ?></span>
            <span class="ps-info">
              <span class="ps-nama"><?= htmlspecialchars($r['nama']) ?></span>
              <span class="ps-chips">
                <span class="ps-chip ps-chip-nomor"><?= htmlspecialchars($r['nomor_peserta']) ?></span>
                <span class="lg-sesi-teks"><?= htmlspecialchars($r['nama_sesi']) ?></span>
              </span>
            </span>
            <span class="lg-stat">
              <span class="lg-stat-angka <?= (int)$r['tab'] > 0 ? 'ada' : '' ?>"><?= (int)$r['tab'] ?></span>
              <span class="lg-stat-label">Pindah tab</span>
            </span>
            <span class="lg-stat">
              <span class="lg-stat-angka <?= (int)$r['fs'] > 0 ? 'ada' : '' ?>"><?= (int)$r['fs'] ?></span>
              <span class="lg-stat-label">Keluar layar penuh</span>
            </span>
            <span class="lg-total lg-total-<?= $tingkat ?>"><?= $total_sus ?>x</span>
          </div>
        </li>
        <?php endwhile; ?>
      </ul>
    </section>
    <?php endif; ?>

    <form method="GET" class="lg-filter" role="search">
      <div class="lg-field">
        <label for="lg-peserta">Peserta</label>
        <select id="lg-peserta" name="peserta_id">
          <option value="">Semua peserta</option>
          <?php while ($p = $peserta_opt->fetch_assoc()): ?>
          <option value="<?= $p['id'] ?>" <?= $fp==$p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nama']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="lg-field">
        <label for="lg-jenis">Jenis aktivitas</label>
        <select id="lg-jenis" name="jenis">
          <option value="">Semua</option>
          <option value="pindah_tab"         <?= $fj==='pindah_tab'         ? 'selected' : '' ?>>Pindah tab</option>
          <option value="keluar_fullscreen"  <?= $fj==='keluar_fullscreen'  ? 'selected' : '' ?>>Keluar layar penuh</option>
          <option value="submit"             <?= $fj==='submit'             ? 'selected' : '' ?>>Submit ujian</option>
          <option value="login"              <?= $fj==='login'              ? 'selected' : '' ?>>Login</option>
        </select>
      </div>
      <div class="lg-field">
        <label for="lg-sesi">Sesi</label>
        <select id="lg-sesi" name="sesi_id">
          <option value="">Semua sesi</option>
          <?php while ($so = $sesi_opt->fetch_assoc()): ?>
          <option value="<?= $so['id'] ?>" <?= $fs==$so['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($so['nama_sesi']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="lg-aksi">
        <button type="submit" class="btn-admin-primary pk-btn">Terapkan</button>
        <a href="<?= url('/admin/log.php') ?>" class="lg-reset">Reset</a>
      </div>
    </form>

    <section class="pk-panel">
      <header class="pk-panel-head">
        <div class="pk-panel-judul">
          <h2>Detail log</h2>
          <p>Menampilkan maksimal 300 entri terbaru sesuai filter.</p>
        </div>
        <span class="lg-jumlah"><?= $logs->num_rows ?> entri</span>
      </header>

      <?php if ($logs->num_rows > 0): ?>
      <div class="lg-tabel-head" aria-hidden="true">
        <span>Waktu</span><span>Peserta</span><span>Aktivitas</span><span>Sesi</span>
      </div>
      <ul class="lg-log">
        <?php while ($l = $logs->fetch_assoc()):
            $cls = match($l['jenis']) {
                'pindah_tab'        => 'tab',
                'keluar_fullscreen' => 'fs',
                'submit'            => 'submit',
                'login'             => 'login',
                default             => 'logout'
            };
            $label = $log_labels[$l['jenis']] ?? ucfirst(str_replace('_', ' ', $l['jenis']));
        ?>
        <li class="lg-item">
          <span class="lg-waktu">
            <span class="lg-tgl"><?= date('d M Y', strtotime($l['waktu'])) ?></span>
            <span class="lg-jam"><?= date('H:i:s', strtotime($l['waktu'])) ?></span>
          </span>
          <span class="lg-peserta">
            <span class="lg-peserta-nama"><?= htmlspecialchars($l['nama'] ?? '—') ?></span>
            <span class="ps-chip ps-chip-nomor"><?= htmlspecialchars($l['nomor_peserta'] ?? '') ?></span>
          </span>
          <span><span class="lg-pill lg-<?= $cls ?>"><?= htmlspecialchars($label) ?></span></span>
          <span class="lg-sesi-teks"><?= htmlspecialchars($l['nama_sesi'] ?? '—') ?></span>
        </li>
        <?php endwhile; ?>
      </ul>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada log aktivitas</strong></p>
        <p>Log akan muncul saat peserta mulai mengerjakan ujian.</p>
      </div>
      <?php endif; ?>
    </section>


<?php include __DIR__ . '/partials/layout-end.php'; ?>