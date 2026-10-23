<?php
require_once '../config.php';
cekAdmin();

// Mode tab: 'hari_ini' (default) atau 'riwayat'
$mode = $_GET['mode'] ?? 'hari_ini';
if (!in_array($mode, ['hari_ini', 'riwayat'], true)) {
    $mode = 'hari_ini';
}

// Filter khusus mode riwayat
$tgl_pilih = $_GET['tanggal'] ?? date('Y-m-d');
$sesi_dipilih = (int) ($_GET['sesi_id'] ?? 0);
$fna = trim($_GET['nama'] ?? '');

// ── AMBIL DAFTAR SESI SESUAI MODE ────────────────────────────
if ($mode === 'hari_ini') {
    $tanggal_aktif = date('Y-m-d');
} else {
    $tanggal_aktif = $tgl_pilih;
}

$stmt_sesi = $conn->prepare("
    SELECT s.id, s.nama_sesi, s.mode AS sesi_mode, pp.nama AS paket_nama,
           (SELECT COUNT(*) FROM hasil_ujian h WHERE h.sesi_id = s.id AND h.status_pengerjaan = 'selesai') AS jumlah_selesai
    FROM sesi_ujian s
    JOIN paket_program pp ON s.paket_id = pp.id
    WHERE s.tanggal = ?
    ORDER BY s.jam_mulai
");
$stmt_sesi->bind_param("s", $tanggal_aktif);
$stmt_sesi->execute();
$daftar_sesi_tanggal = [];
$res_sesi = $stmt_sesi->get_result();
while ($row = $res_sesi->fetch_assoc()) {
    $daftar_sesi_tanggal[] = $row;
}

// Kalau belum pilih sesi spesifik, default ke sesi pertama di tanggal itu (kalau ada)
if ($sesi_dipilih === 0 && count($daftar_sesi_tanggal) > 0) {
    $sesi_dipilih = (int) $daftar_sesi_tanggal[0]['id'];
}

// ── AMBIL HASIL UNTUK SESI YANG DIPILIH ─────────────────────
$hasil = null;
$sesi_aktif_info = null;

if ($sesi_dipilih > 0) {
    foreach ($daftar_sesi_tanggal as $s) {
        if ((int)$s['id'] === $sesi_dipilih) {
            $sesi_aktif_info = $s;
            break;
        }
    }

    $where = "WHERE h.sesi_id = ?";
    $params = [$sesi_dipilih];
    $types = "i";

    if ($fna) {
        $where .= " AND p.nama LIKE ?";
        $params[] = '%' . $fna . '%';
        $types .= "s";
    }

    $sql = "
        SELECT h.*, p.nama, p.nomor_peserta,
               s.nama_sesi, s.mode, pp.nama AS paket_nama
        FROM hasil_ujian h
        JOIN peserta p    ON h.peserta_id = p.id
        JOIN sesi_ujian s ON h.sesi_id    = s.id
        JOIN paket_program pp ON s.paket_id = pp.id
        $where
        ORDER BY h.submitted_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $hasil = $stmt->get_result();
}

$admin_page = 'hasil';
$page_title = 'Rekap Nilai';
$topbar_breadcrumb = 'Rekap Nilai';
$topbar_title      = 'Rekap Nilai';

// ── EXPORT CSV ───────────────────────────────────────────────
if (isset($_GET['export']) && isset($_GET['sesi_id'])) {
    $export_sesi_id = (int) $_GET['sesi_id'];

    $stmt_export = $conn->prepare("
        SELECT p.nama, p.nomor_peserta, h.skor, h.total_benar, h.total_salah,
               h.durasi_detik, h.submitted_at, s.nama_sesi, pp.nama AS paket_nama
        FROM hasil_ujian h
        JOIN peserta p    ON h.peserta_id = p.id
        JOIN sesi_ujian s ON h.sesi_id    = s.id
        JOIN paket_program pp ON s.paket_id = pp.id
        WHERE h.sesi_id = ? AND h.status_pengerjaan = 'selesai'
        ORDER BY h.skor DESC
    ");
    $stmt_export->bind_param("i", $export_sesi_id);
    $stmt_export->execute();
    $res_export = $stmt_export->get_result();

    $nama_file = 'rekap_nilai_' . date('Y-m-d') . '_sesi' . $export_sesi_id . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nama_file . '"');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['Nama', 'Nomor Peserta', 'Program', 'Sesi', 'Nilai', 'Waktu Pengerjaan', 'Tanggal Submit']);

    while ($row = $res_export->fetch_assoc()) {
        $menit = floor($row['durasi_detik'] / 60);
        $detik = $row['durasi_detik'] % 60;
        fputcsv($output, [
            $row['nama'],
            $row['nomor_peserta'],
            $row['paket_nama'],
            $row['nama_sesi'],
            $row['skor'],
            "{$menit}m {$detik}d",
            date('d M Y H:i', strtotime($row['submitted_at'])),
        ]);
    }

    fclose($output);
    exit;
}

include __DIR__ . '/partials/layout-start.php';
?>

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2>Rekap Nilai</h2>
        <p>Pilih sesi untuk melihat hasil peserta. Ekspor CSV untuk dibagikan per sesi.</p>
      </div>
      <?php if ($sesi_aktif_info && $hasil->num_rows > 0): ?>
      <a href="<?= url('/admin/hasil.php?export=1&sesi_id=' . (int)$sesi_dipilih) ?>" class="ps-tambah">
        <i class="fas fa-download"></i> Ekspor CSV
      </a>
      <?php endif; ?>
    </div>

    <div class="rn-mode" role="tablist" aria-label="Pilih rentang tanggal">
      <a href="<?= url('/admin/hasil.php?mode=hari_ini') ?>"
         class="rn-mode-btn<?= $mode === 'hari_ini' ? ' aktif' : '' ?>"
         <?= $mode === 'hari_ini' ? 'aria-selected="true"' : '' ?>>Hari ini</a>
      <a href="<?= url('/admin/hasil.php?mode=riwayat') ?>"
         class="rn-mode-btn<?= $mode === 'riwayat' ? ' aktif' : '' ?>"
         <?= $mode === 'riwayat' ? 'aria-selected="true"' : '' ?>>Cari riwayat</a>
    </div>

    <?php if ($mode === 'riwayat'): ?>
    <form method="GET" class="rn-tanggal">
      <input type="hidden" name="mode" value="riwayat">
      <label for="rn-tgl">Tanggal</label>
      <input id="rn-tgl" type="date" name="tanggal" value="<?= e($tgl_pilih) ?>">
      <button type="submit" class="btn-admin-primary pk-btn">Tampilkan</button>
    </form>
    <?php endif; ?>

    <?php if (count($daftar_sesi_tanggal) > 0): ?>
    <div class="rn-sesi-grid">
      <?php
      $warna_daftar = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'];
      foreach ($daftar_sesi_tanggal as $s):
          $aktif   = $sesi_dipilih === (int)$s['id'];
          $inisial = mb_strtoupper(mb_substr(trim($s['paket_nama']), 0, 2));
          $warna   = $warna_daftar[(int)$s['id'] % 6];
      ?>
      <a href="<?= url('/admin/hasil.php?mode=' . $mode . '&tanggal=' . urlencode($tanggal_aktif) . '&sesi_id=' . (int)$s['id']) ?>"
         class="rn-sesi<?= $aktif ? ' aktif' : '' ?>"
         <?= $aktif ? 'aria-current="true"' : '' ?>>
        <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
        <span class="rn-sesi-teks">
          <span class="rn-sesi-nama"><?= e($s['nama_sesi']) ?></span>
          <span class="rn-sesi-info"><?= e($s['paket_nama']) ?> · <?= (int)$s['jumlah_selesai'] ?> selesai</span>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <p><strong><?= $mode === 'hari_ini' ? 'Belum ada sesi ujian hari ini' : 'Tidak ada sesi ujian di tanggal ini' ?></strong></p>
      <p><?= $mode === 'hari_ini' ? 'Sesi akan muncul otomatis di sini setelah dibuat untuk hari ini.' : 'Coba pilih tanggal lain.' ?></p>
    </div>
    <?php endif; ?>

    <?php if ($sesi_aktif_info): ?>

    <form method="GET" class="rn-cari" role="search">
      <input type="hidden" name="mode" value="<?= e($mode) ?>">
      <input type="hidden" name="tanggal" value="<?= e($tanggal_aktif) ?>">
      <input type="hidden" name="sesi_id" value="<?= (int)$sesi_dipilih ?>">
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" name="nama" value="<?= e($fna) ?>" placeholder="Cari nama peserta..." autocomplete="off">
    </form>

    <section class="pk-panel rn-panel">
      <header class="pk-panel-head">
        <div class="pk-panel-judul">
          <h2><?= e($sesi_aktif_info['nama_sesi']) ?></h2>
          <p><?= e($sesi_aktif_info['paket_nama']) ?> · <?= date('d M Y', strtotime($tanggal_aktif)) ?> · <?= $hasil->num_rows ?> peserta</p>
        </div>
      </header>

      <?php if ($hasil->num_rows > 0): ?>
      <?php
      $rows = [];
      while ($r = $hasil->fetch_assoc()) $rows[] = $r;
      ?>
      <ul class="ps-list rn-list">
        <?php
        $warna_daftar = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'];
        foreach ($rows as $r):
            $lulus   = $r['skor'] >= 70;
            $m       = floor($r['durasi_detik'] / 60);
            $d       = $r['durasi_detik'] % 60;
            $inisial = mb_strtoupper(mb_substr(trim($r['nama']), 0, 2));
            $warna   = $warna_daftar[(int)$r['peserta_id'] % 6];
        ?>
        <li class="ps-row">
          <div class="ps-row-link ps-row-statis rn-row">
            <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
            <span class="ps-info">
              <span class="ps-nama"><?= e($r['nama']) ?></span>
              <span class="ps-chips">
                <span class="ps-chip ps-chip-nomor"><?= e($r['nomor_peserta']) ?></span>
              </span>
            </span>
            <span class="rn-waktu"><?= $m ?> mnt <?= $d ?> dtk</span>
            <span class="rn-nilai <?= $lulus ? 'lulus' : 'belum' ?>"><?= (int)$r['skor'] ?></span>
            <span class="rn-status <?= $lulus ? 'lulus' : 'belum' ?>"><?= $lulus ? 'Lulus' : 'Belum' ?></span>
          </div>
          <div class="ps-aksi">
            <a class="ps-ikon" href="<?= url('/review.php?hasil_id=' . (int)$r['id']) ?>" target="_blank" title="Lihat review jawaban">
              <i class="fas fa-eye"></i>
            </a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada peserta yang menyelesaikan sesi ini</strong></p>
        <p>Data akan muncul setelah peserta submit jawaban.</p>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>


<?php include __DIR__ . '/partials/layout-end.php'; ?>