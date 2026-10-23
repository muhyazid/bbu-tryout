<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

/* ---------- DATA ---------- */
$stat = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM peserta)                                         AS total_peserta,
        (SELECT COUNT(*) FROM soal)                                            AS total_soal,
        (SELECT COUNT(*) FROM sesi_ujian WHERE status='aktif')                 AS sesi_aktif,
        (SELECT COUNT(*) FROM hasil_ujian WHERE DATE(submitted_at)=CURDATE())  AS ujian_hari_ini
")->fetch_assoc();

$total_peserta  = (int) $stat['total_peserta'];
$total_soal     = (int) $stat['total_soal'];
$sesi_aktif     = (int) $stat['sesi_aktif'];
$ujian_hari_ini = (int) $stat['ujian_hari_ini'];

// Peserta yang sedang mengerjakan atau dijeda (data live)
$sedang_ujian = (int) $conn->query("
    SELECT COUNT(*) AS c FROM hasil_ujian
    WHERE status_pengerjaan IN ('sedang_dikerjakan','dijeda')
")->fetch_assoc()['c'];

// Kelompok yang sedang dijeda pengawas
$kelompok_dijeda = (int) $conn->query("
    SELECT COUNT(*) AS c FROM kelompok_angkatan WHERE status='dijeda'
")->fetch_assoc()['c'];

$sesi_list = $conn->query('
    SELECT s.*, pp.nama AS paket_nama
    FROM sesi_ujian s
    JOIN paket_program pp ON s.paket_id = pp.id
    ORDER BY s.created_at DESC LIMIT 5
');

/* ---------- HELPER TAMPILAN ---------- */
$hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli',
          'Agustus','September','Oktober','November','Desember'];
$bulan_singkat = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$tanggal_str = $hari[(int)$now->format('w')] . ', ' . $now->format('j') . ' '
             . $bulan[(int)$now->format('n')] . ' ' . $now->format('Y');
$jam_str = $now->format('H:i');

$jam_num = (int) $now->format('G');
if ($jam_num < 11)      $salam = 'Selamat pagi';
elseif ($jam_num < 15)  $salam = 'Selamat siang';
elseif ($jam_num < 18)  $salam = 'Selamat sore';
else                    $salam = 'Selamat malam';

$fmt_tgl = function (string $tgl) use ($bulan_singkat) {
    $t = strtotime($tgl);
    return date('j', $t) . ' ' . $bulan_singkat[(int)date('n', $t)] . ' ' . date('Y', $t);
};

$nama_admin = $_SESSION['admin_nama'] ?? 'Admin';

$admin_page        = 'dashboard';
$page_title        = 'Dashboard Admin';
$topbar_breadcrumb = 'Dashboard';
$topbar_title      = 'Selamat datang, ' . $nama_admin;

include __DIR__ . '/partials/layout-start.php';
?>

<style>
  .dash { --d-navy:#1e3a5f; --d-navy-2:#2c5282; --d-bg:#f5f7fb; --d-line:#e6eaf2;
          --d-text:#1a202c; --d-muted:#718096; --d-green:#2f855a; --d-amber:#b7791f;
          --d-red:#c53030; --d-radius:14px; --d-shadow:0 1px 2px rgba(16,24,40,.04), 0 4px 14px rgba(16,24,40,.06);
          font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif; color:var(--d-text); }
  .dash *, .dash *::before, .dash *::after { box-sizing:border-box; }

  /* Hero */
  .dash-hero { display:flex; justify-content:space-between; align-items:flex-end; gap:16px;
               background:linear-gradient(135deg,var(--d-navy) 0%,var(--d-navy-2) 100%);
               color:#fff; border-radius:var(--d-radius); padding:24px 28px; margin-bottom:20px;
               flex-wrap:wrap; }
  .dash-eyebrow { font-size:12px; letter-spacing:.06em; text-transform:uppercase; opacity:.75; margin:0 0 6px; }
  .dash-title { font-size:24px; font-weight:700; margin:0 0 4px; }
  .dash-sub { margin:0; opacity:.8; font-size:14px; }
  .dash-hero-meta { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18);
                    border-radius:12px; padding:12px 18px; text-align:right; min-width:140px; }
  .dash-hero-meta span { display:block; font-size:12px; opacity:.8; }
  .dash-hero-meta strong { font-size:26px; font-weight:700; }

  /* Alert */
  .dash-alert { display:flex; align-items:center; gap:12px; background:#fffaf0; border:1px solid #fbd38d;
                color:#744210; border-radius:12px; padding:12px 16px; margin-bottom:20px;
                text-decoration:none; font-size:14px; }
  .dash-alert:hover { background:#fef5e4; }
  .dash-alert strong { font-weight:600; }
  .dash-alert .dash-alert-go { margin-left:auto; font-weight:600; white-space:nowrap; }

  /* Stats */
  .dash-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:20px; }
  .dash-stat { background:#fff; border:1px solid var(--d-line); border-radius:var(--d-radius);
               box-shadow:var(--d-shadow); padding:18px; display:flex; align-items:center; gap:14px;
               position:relative; overflow:hidden; }
  .dash-stat::before { content:""; position:absolute; left:0; top:0; bottom:0; width:4px; background:var(--accent,var(--d-navy)); }
  .dash-stat-icon { width:46px; height:46px; border-radius:12px; display:grid; place-items:center;
                    background:color-mix(in srgb,var(--accent,var(--d-navy)) 12%,#fff); color:var(--accent,var(--d-navy)); flex-shrink:0; }
  .dash-stat-icon svg { width:22px; height:22px; }
  .dash-stat-val { font-size:26px; font-weight:700; line-height:1.1; margin:0; }
  .dash-stat-lbl { font-size:13px; color:var(--d-muted); margin:2px 0 0; }
  .dash-live { display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--d-red);
               margin-left:6px; vertical-align:middle; animation:dash-pulse 1.4s infinite; }
  @keyframes dash-pulse { 0%{opacity:1} 50%{opacity:.25} 100%{opacity:1} }

  /* Layout kolom */
  .dash-cols { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); gap:20px; }
  @media (max-width:900px){ .dash-cols { grid-template-columns:1fr; } }

  .dash-panel { background:#fff; border:1px solid var(--d-line); border-radius:var(--d-radius);
                box-shadow:var(--d-shadow); overflow:hidden; }
  .dash-panel-head { display:flex; justify-content:space-between; align-items:center; gap:12px;
                     padding:16px 20px; border-bottom:1px solid var(--d-line); }
  .dash-panel-head h3 { margin:0; font-size:15px; font-weight:600; }
  .dash-panel-head a { font-size:13px; color:var(--d-navy-2); text-decoration:none; font-weight:500; }
  .dash-panel-head a:hover { text-decoration:underline; }
  .dash-panel-body { padding:16px 20px 20px; }

  /* Aksi cepat */
  .dash-actions { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  @media (max-width:520px){ .dash-actions { grid-template-columns:1fr; } }
  .dash-action { display:flex; flex-direction:column; gap:6px; padding:16px; border:1px solid var(--d-line);
                 border-radius:12px; text-decoration:none; color:var(--d-text); background:#fbfcfe;
                 transition:transform .15s, box-shadow .15s, border-color .15s; }
  .dash-action:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,24,40,.08); border-color:#c3cfe2; }
  .dash-action .dash-action-ico { width:36px; height:36px; border-radius:10px; display:grid; place-items:center;
                                  background:color-mix(in srgb,var(--accent,var(--d-navy)) 12%,#fff); color:var(--accent,var(--d-navy)); }
  .dash-action .dash-action-ico svg { width:18px; height:18px; }
  .dash-action strong { font-size:14px; font-weight:600; }
  .dash-action small { font-size:12px; color:var(--d-muted); }

  /* Daftar sesi */
  .dash-session { display:flex; justify-content:space-between; align-items:center; gap:12px;
                  padding:14px 0; border-bottom:1px solid var(--d-line); }
  .dash-session:last-child { border-bottom:none; padding-bottom:0; }
  .dash-session:first-child { padding-top:0; }
  .dash-session-name { font-weight:600; font-size:14px; margin:0 0 4px; }
  .dash-session-meta { font-size:12px; color:var(--d-muted); display:flex; gap:10px; flex-wrap:wrap; }
  .dash-tag { display:inline-block; padding:2px 8px; border-radius:999px; background:#edf2f7; color:#2d3748;
              font-size:11px; font-weight:500; }
  .dash-pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
  .dash-pill-aktif { background:#e6f4ea; color:var(--d-green); }
  .dash-pill-nonaktif { background:#edf2f7; color:#4a5568; }

  .dash-empty { text-align:center; padding:28px 12px; color:var(--d-muted); }
  .dash-empty a { display:inline-block; margin-top:10px; color:var(--d-navy-2); font-weight:600; text-decoration:none; }
</style>

<div class="dash">

  <header class="dash-hero">
    <div>
      <p class="dash-eyebrow"><?= e($tanggal_str) ?> &middot; <?= e($jam_str) ?> WIB</p>
      <h1 class="dash-title"><?= e($salam) ?>, <?= e($nama_admin) ?></h1>
      <p class="dash-sub">Ringkasan aktivitas tryout hari ini.</p>
    </div>
    <div class="dash-hero-meta">
      <span>Ujian hari ini</span>
      <strong><?= $ujian_hari_ini ?></strong>
    </div>
  </header>

  <?php if ($kelompok_dijeda > 0): ?>
  <a class="dash-alert" href="kelompok.php">
    <strong><?= $kelompok_dijeda ?> kelompok sedang dijeda.</strong>
    <span>Peserta di kelompok tersebut tidak dapat melanjutkan ujian.</span>
    <span class="dash-alert-go">Kelola kelompok &rarr;</span>
  </a>
  <?php endif; ?>

  <section class="dash-stats">
    <div class="dash-stat" style="--accent:#2c5282">
      <div class="dash-stat-icon"><?= admin_icon('peserta') ?></div>
      <div>
        <p class="dash-stat-val"><?= $total_peserta ?></p>
        <p class="dash-stat-lbl">Total peserta</p>
      </div>
    </div>

    <div class="dash-stat" style="--accent:#6b46c1">
      <div class="dash-stat-icon"><?= admin_icon('soal') ?></div>
      <div>
        <p class="dash-stat-val"><?= $total_soal ?></p>
        <p class="dash-stat-lbl">Bank soal</p>
      </div>
    </div>

    <div class="dash-stat" style="--accent:#2f855a">
      <div class="dash-stat-icon"><?= admin_icon('sesi') ?></div>
      <div>
        <p class="dash-stat-val"><?= $sesi_aktif ?></p>
        <p class="dash-stat-lbl">Sesi aktif</p>
      </div>
    </div>

    <div class="dash-stat" style="--accent:#c05621">
      <div class="dash-stat-icon"><?= admin_icon('hasil') ?></div>
      <div>
        <p class="dash-stat-val"><?= $sedang_ujian ?></p>
        <p class="dash-stat-lbl">Sedang ujian <?php if ($sedang_ujian > 0): ?><span class="dash-live" title="Live"></span><?php endif; ?></p>
      </div>
    </div>
  </section>

  <div class="dash-cols">

    <section class="dash-panel">
      <div class="dash-panel-head">
        <h3>Aksi cepat</h3>
      </div>
      <div class="dash-panel-body">
        <div class="dash-actions">
          <a class="dash-action" href="peserta.php?action=tambah" style="--accent:#2c5282">
            <span class="dash-action-ico"><?= admin_icon('peserta') ?></span>
            <strong>Tambah peserta</strong>
            <small>Data login peserta</small>
          </a>
          <a class="dash-action" href="soal.php?action=tambah" style="--accent:#6b46c1">
            <span class="dash-action-ico"><?= admin_icon('soal') ?></span>
            <strong>Input soal</strong>
            <small>Bank soal per kategori</small>
          </a>
          <a class="dash-action" href="sesi.php?action=tambah" style="--accent:#2f855a">
            <span class="dash-action-ico"><?= admin_icon('sesi') ?></span>
            <strong>Buat sesi ujian</strong>
            <small>Jadwal &amp; durasi</small>
          </a>
          <a class="dash-action" href="hasil.php" style="--accent:#c05621">
            <span class="dash-action-ico"><?= admin_icon('hasil') ?></span>
            <strong>Rekap nilai</strong>
            <small>Hasil semua peserta</small>
          </a>
        </div>
      </div>
    </section>

    <section class="dash-panel">
      <div class="dash-panel-head">
        <h3>Sesi ujian terbaru</h3>
        <a href="sesi.php">Lihat semua &rarr;</a>
      </div>
      <div class="dash-panel-body">
        <?php if ($sesi_list->num_rows > 0): ?>
          <?php while ($s = $sesi_list->fetch_assoc()): ?>
          <div class="dash-session">
            <div>
              <p class="dash-session-name"><?= e($s['nama_sesi']) ?></p>
              <div class="dash-session-meta">
                <span class="dash-tag"><?= e($s['paket_nama']) ?></span>
                <span><?= e($fmt_tgl($s['tanggal'])) ?></span>
                <span><?= e(substr($s['jam_mulai'], 0, 5)) ?>&ndash;<?= e(substr($s['jam_selesai'], 0, 5)) ?></span>
                <span><?= (int)$s['durasi_menit'] ?> menit</span>
                <span><?= $s['mode'] === 'latihan' ? 'Latihan' : 'Ujian' ?></span>
              </div>
            </div>
            <span class="dash-pill dash-pill-<?= e($s['status']) ?>">
              <?= $s['status'] === 'aktif' ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="dash-empty">
            <p>Belum ada sesi ujian.</p>
            <a href="sesi.php?action=tambah">Buat sesi pertama &rarr;</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </div>
</div>

<?php include __DIR__ . '/partials/layout-end.php'; ?>