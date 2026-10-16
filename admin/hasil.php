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

    <!-- TAB MODE -->
    <div class="filter-group" style="margin-bottom:20px;">
      <a href="hasil.php?mode=hari_ini" class="filter-btn <?= $mode === 'hari_ini' ? 'filter-btn-active' : '' ?>">
        Hari Ini
      </a>
      <a href="hasil.php?mode=riwayat" class="filter-btn <?= $mode === 'riwayat' ? 'filter-btn-active' : '' ?>">
        Cari Riwayat
      </a>
    </div>

    <?php if ($mode === 'riwayat'): ?>
    <!-- PILIH TANGGAL (khusus mode riwayat) -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <input type="hidden" name="mode" value="riwayat">
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Pilih tanggal</div>
            <input type="date" name="tanggal" class="form-input" value="<?= e($tgl_pilih) ?>">
          </div>
          <button type="submit" class="btn-primary" style="padding:9px 18px;">Tampilkan</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- DAFTAR SESI DI TANGGAL INI -->
    <?php if (count($daftar_sesi_tanggal) > 0): ?>
    <div class="filter-group" style="margin-bottom:16px;flex-wrap:wrap;">
      <?php foreach ($daftar_sesi_tanggal as $s): ?>
      <a href="hasil.php?mode=<?= $mode ?>&tanggal=<?= e($tanggal_aktif) ?>&sesi_id=<?= (int)$s['id'] ?>"
         class="filter-btn <?= $sesi_dipilih === (int)$s['id'] ? 'filter-btn-active' : '' ?>">
        <?= e($s['nama_sesi']) ?> <span style="opacity:0.7;">(<?= (int)$s['jumlah_selesai'] ?> selesai)</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="margin-bottom:16px;">
      <p><strong><?= $mode === 'hari_ini' ? 'Belum ada sesi ujian hari ini' : 'Tidak ada sesi ujian di tanggal ini' ?></strong></p>
      <p><?= $mode === 'hari_ini' ? 'Sesi akan muncul otomatis di sini setelah dibuat untuk hari ini.' : 'Coba pilih tanggal lain.' ?></p>
    </div>
    <?php endif; ?>

    <?php if ($sesi_aktif_info): ?>
    <!-- FILTER NAMA (dalam sesi yang dipilih) -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <input type="hidden" name="mode" value="<?= $mode ?>">
          <input type="hidden" name="tanggal" value="<?= e($tanggal_aktif) ?>">
          <input type="hidden" name="sesi_id" value="<?= $sesi_dipilih ?>">
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Cari nama peserta</div>
            <input type="text" name="nama" class="form-input"
                   placeholder="Ketik nama peserta..."
                   value="<?= e($fna) ?>" style="width:220px;">
          </div>
          <button type="submit" class="btn-primary" style="padding:9px 18px;">Cari</button>
        </form>
      </div>
    </div>

    <!-- TABEL HASIL -->
    <div class="card">
      <div class="card-header">
        <div>
          <h3><?= e($sesi_aktif_info['nama_sesi']) ?> <span class="count-pill"><?= $hasil->num_rows ?> data</span></h3>
          <span style="font-size:12px;color:var(--text-muted);"><?= e($sesi_aktif_info['paket_nama']) ?> · <?= date('d M Y', strtotime($tanggal_aktif)) ?></span>
        </div>
        <?php if ($hasil->num_rows > 0): ?>
        <a href="hasil.php?export=1&sesi_id=<?= $sesi_dipilih ?>"
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
            <th style="text-align:center;">Nilai</th>
            <th style="text-align:center;">Status</th>
            <th>Waktu</th>
            <th>Review</th>
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
              <div style="font-weight:600;font-size:14px;"><?= e($r['nama']) ?></div>
              <code style="font-size:12px;background:#f0f2f5;padding:1px 6px;border-radius:4px;">
                <?= e($r['nomor_peserta']) ?>
              </code>
            </td>
            <td style="text-align:center;">
              <span style="font-size:24px;font-weight:800;
                           color:<?= $lulus ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= $r['skor'] ?>
              </span>
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
        <p><strong>Belum ada peserta yang menyelesaikan sesi ini</strong></p>
        <p>Data akan muncul setelah peserta submit jawaban.</p>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>