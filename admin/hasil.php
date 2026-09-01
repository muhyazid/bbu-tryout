<?php
require_once '../config.php';
cekAdmin();

// Filter
$fj  = (int)($_GET['paket_id'] ?? 0);
$fs  = (int)($_GET['sesi_id'] ?? 0);
$fna = trim($_GET['nama'] ?? '');

$where = "WHERE 1=1";
if ($fj)  $where .= " AND s.paket_id=" . $fj;
if ($fs)  $where .= " AND h.sesi_id="  . $fs;
if ($fna) $where .= " AND p.nama LIKE '%" . $conn->real_escape_string($fna) . "%'";

$hasil = $conn->query("
    SELECT h.*, p.nama, p.nomor_peserta, p.jenis as p_jenis,
           s.nama_sesi, s.jenis as s_jenis, s.mode
    FROM hasil_ujian h
    JOIN peserta p    ON h.peserta_id = p.id
    JOIN sesi_ujian s ON h.sesi_id    = s.id
    $where
    ORDER BY h.submitted_at DESC
");

// Dropdown sesi untuk filter
$sesi_opt = $conn->query("
    SELECT s.id, s.nama_sesi, pp.nama AS paket_nama
    FROM sesi_ujian s
    JOIN paket_program pp ON s.paket_id = pp.id
    ORDER BY s.tanggal DESC
");

// Stat ringkas
$stat = $conn->query("
    SELECT
        COUNT(*) as total,
        ROUND(AVG(skor),1) as rata,
        MAX(skor) as tertinggi,
        MIN(skor) as terendah,
        SUM(skor >= 70) as lulus
    FROM hasil_ujian h
    JOIN sesi_ujian s ON h.sesi_id = s.id
    $where
")->fetch_assoc();

$admin_page = 'hasil';
$page_title = 'Rekap Nilai';
$topbar_breadcrumb = 'Rekap Nilai';
$topbar_title      = 'Rekap Nilai';

include __DIR__ . '/partials/layout-start.php';
?>

    <!-- STAT RINGKAS -->
    <?php if ($stat['total'] > 0): ?>
    <div class="stat-grid" style="margin-bottom:20px;">
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('file') ?></div>
        <div class="stat-info">
          <h3><?= $stat['total'] ?></h3>
          <p>Total Ujian</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('chart') ?></div>
        <div class="stat-info">
          <h3><?= $stat['rata'] ?></h3>
          <p>Rata-rata Nilai</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('hasil') ?></div>
        <div class="stat-info">
          <h3><?= $stat['tertinggi'] ?></h3>
          <p>Nilai Tertinggi</p>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><?= admin_icon('users') ?></div>
        <div class="stat-info">
          <h3><?= $stat['lulus'] ?> / <?= $stat['total'] ?></h3>
          <p>Lulus (≥70)</p>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- FILTER -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Cari nama</div>
            <input type="text" name="nama" class="form-input"
                   placeholder="Ketik nama peserta..."
                   value="<?= htmlspecialchars($fna) ?>" style="width:190px;">
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Program</div>
            <select name="paket_id" class="form-input">
              <option value="">Semua</option>
              <?php
              $opt_paket = $conn->query("SELECT id, nama FROM paket_program ORDER BY urutan");
              while ($op = $opt_paket->fetch_assoc()):
              ?>
              <option value="<?= (int)$op['id'] ?>" <?= $fj === (int)$op['id'] ? 'selected' : '' ?>><?= e($op['nama']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Sesi Ujian</div>
            <select name="sesi_id" class="form-input" style="width:220px;">
              <option value="">Semua Sesi</option>
              <?php while ($so = $sesi_opt->fetch_assoc()): ?>
              <option value="<?= $so['id'] ?>" <?= $fs == $so['id'] ? 'selected' : '' ?>>
                [<?= e($so['paket_nama']) ?>] <?= e($so['nama_sesi']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <button type="submit" class="btn-primary" style="padding:9px 18px;">Filter</button>
          <a href="hasil.php"
             style="color:var(--text-muted);font-size:13px;text-decoration:none;
                    padding:9px 14px;border:1px solid var(--border);
                    border-radius:8px;background:#f8f9fa;">
            Reset
          </a>
        </form>
      </div>
    </div>

    <!-- TABEL HASIL -->
    <div class="card">
      <div class="card-header">
        <h3>Daftar hasil ujian <span class="count-pill"><?= $hasil->num_rows ?> data</span></h3>
        <?php if ($hasil->num_rows > 0): ?>
        <a href="hasil.php?export=1&<?= http_build_query($_GET) ?>" 
          class="btn-admin-primary" style="font-size:13px;padding:8px 14px;">
          <i class="fas fa-download" style="font-size:12px;"></i> Export CSV
        </a>
        <?php endif; ?>
      </div>

      <?php if ($hasil->num_rows > 0): ?>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Peserta</th>
            <th>Sesi Ujian</th>
            <th style="text-align:center;">Nilai</th>
            <th style="text-align:center;">Benar / Total</th>
            <th style="text-align:center;">Status</th>
            <th>Waktu</th>
            <th>Tanggal</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        $rows = [];
        while ($r = $hasil->fetch_assoc()) $rows[] = $r;
        foreach ($rows as $r):
            $lulus = $r['skor'] >= 70;
            $m = floor($r['durasi_detik'] / 60);
            $s = $r['durasi_detik'] % 60;
        ?>
          <tr>
            <td style="color:var(--text-muted);"><?= $no++ ?></td>
            <td>
              <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($r['nama']) ?></div>
              <code style="font-size:12px;background:#f0f2f5;padding:1px 6px;border-radius:4px;">
                <?= htmlspecialchars($r['nomor_peserta']) ?>
              </code>
            </td>
            <td>
              <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($r['nama_sesi']) ?></div>
              <span class="badge badge-<?= strtolower($r['s_jenis']) ?>"><?= $r['s_jenis'] ?></span>
              <span class="badge badge-<?= $r['mode'] ?>"><?= ucfirst($r['mode']) ?></span>
            </td>
            <td style="text-align:center;">
              <span style="font-size:24px;font-weight:800;
                           color:<?= $lulus ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= $r['skor'] ?>
              </span>
            </td>
            <td style="text-align:center;font-size:14px;">
              <span style="color:var(--success);font-weight:600;"><?= $r['total_benar'] ?></span>
              <span style="color:var(--text-muted);"> / <?= $r['total_soal'] ?></span>
            </td>
            <td style="text-align:center;">
              <?php if ($lulus): ?>
              <span class="badge badge-aktif">Lulus</span>
              <?php else: ?>
              <span class="badge badge-ujian">Belum</span>
              <?php endif; ?>
            </td>
            <td style="font-size:13px;color:var(--text-muted);">
              <?= $m ?>m <?= $s ?>d
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
              <?= date('d M Y', strtotime($r['submitted_at'])) ?><br>
              <?= date('H:i', strtotime($r['submitted_at'])) ?>
            </td>
            <th>Review</th>
            <td>
                <a href="<?= url('/review.php?hasil_id=' . $r['id']) ?>" target="_blank" class="btn-icon" data-tooltip="Lihat review jawaban">
                    <i class="fas fa-eye"></i>
                </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada hasil ujian</strong></p>
        <p>Data akan muncul setelah peserta selesai mengerjakan ujian.</p>
      </div>
      <?php endif; ?>
    </div>

<?php include __DIR__ . '/partials/layout-end.php'; ?>