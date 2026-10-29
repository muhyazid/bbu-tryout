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

// ── AMBIL DAFTAR SESI SESUAI MODE ─────────────────────
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

        $where  = "WHERE h.sesi_id = ?";
    $params = [$sesi_dipilih];
    $types  = "i";

    if ($fna) {
        $where   .= " AND p.nama LIKE ?";
        $params[] = '%' . $fna . '%';
        $types   .= "s";
    }

    // Jumlah total untuk pagination
    $stc = $conn->prepare("
        SELECT COUNT(*) AS n
        FROM hasil_ujian h
        JOIN peserta p ON h.peserta_id = p.id
        $where
    ");
    $stc->bind_param($types, ...$params);
    $stc->execute();
    $total_rows = (int) $stc->get_result()->fetch_assoc()['n'];

    $per_halaman = 20;
    $pages       = max(1, (int) ceil($total_rows / $per_halaman));
    $halaman     = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    $offset      = ($halaman - 1) * $per_halaman;

    $sql = "
        SELECT h.*, p.nama, p.nomor_peserta, s.nama_sesi, s.mode, pp.nama AS paket_nama
        FROM hasil_ujian h
        JOIN peserta p        ON h.peserta_id = p.id
        JOIN sesi_ujian s     ON h.sesi_id    = s.id
        JOIN paket_program pp ON s.paket_id   = pp.id
        $where
        ORDER BY p.nama ASC
        LIMIT ? OFFSET ?
    ";
    $params[] = $per_halaman;
    $params[] = $offset;
    $types   .= "ii";

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
          <p><?= e($sesi_aktif_info['paket_nama']) ?> · <?= date('d M Y', strtotime($tanggal_aktif)) ?> · <?= $total_rows ?> peserta</p>
        </div>
      </header>

      <?php if ($hasil->num_rows > 0): ?>
      <?php
      $rows = [];
      while ($r = $hasil->fetch_assoc()) $rows[] = $r;

      // Rincian per materi untuk peserta di halaman ini saja
      $rincian  = [];
      $ids_hasil = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
      $res_mat  = $conn->query("
          SELECT hpm.hasil_ujian_id, k.nama, hpm.total_bobot_didapat, hpm.total_bobot_maksimal
          FROM hasil_per_materi hpm
          JOIN kategori k ON k.id = hpm.kategori_id
          WHERE hpm.hasil_ujian_id IN ($ids_hasil)
          ORDER BY k.nama
      ");
      while ($x = $res_mat->fetch_assoc()) {
          $rincian[(int) $x['hasil_ujian_id']][] = $x;
      }

      $nomor = $offset;
      ?>

      <div class="rn-tabel-wrap">
        <table class="rn-tabel">
          <thead>
            <tr>
              <th class="rn-no">No</th>
              <th>Peserta</th>
              <th class="rn-num">Nilai</th>
              <th class="rn-num">Benar</th>
              <th class="rn-num">Durasi</th>
              <th class="rn-waktu">Dikirim</th>
              <th class="rn-aksi"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
            $lulus  = $r['skor'] >= 70;
            $materi = $rincian[(int) $r['id']] ?? [];
            $nomor++;
            $bulan = [
                'Jan','Feb','Mar','Apr','Mei','Jun',
                'Jul','Agu','Sep','Okt','Nov','Des'
            ];
            $t_kirim = strtotime($r['submitted_at']);
            $kirim = date('j', $t_kirim) . ' '
                . $bulan[(int)date('n', $t_kirim) - 1]
                . ' · ' . date('H:i', $t_kirim);
            $durasi = sprintf(
                '%02d:%02d',
                floor((int)$r['durasi_detik'] / 60),
                (int)$r['durasi_detik'] % 60
            );
          ?>
            <tr>
                <td class="rn-no"><?= $nomor ?></td>
                <td class="rn-peserta">
                  <div class="rn-nama"><?= e($r['nama']) ?></div>
                  <div class="rn-nomor"><?= e($r['nomor_peserta']) ?></div>
                </td>
                <td class="rn-num">
                  <span class="rn-nilai">
                    <?= number_format((float)$r['skor'], 0) ?>
                  </span>
                  <span class="rn-status <?= $lulus ? 'rn-lulus' : 'rn-remedial' ?>">
                    <?= $lulus ? 'Lulus' : 'Belum lulus' ?>
                  </span>
                </td>
                <td class="rn-num">
                    <?= (int)$r['total_benar'] ?>
                    <span class="rn-skala">/ <?= (int)$r['total_soal'] ?></span>
                </td>
                <td class="rn-num rn-mono"><?= $durasi ?></td>
                <td class="rn-waktu"><?= e($kirim) ?></td>
                <td class="rn-aksi">
                    <a
                        class="rn-link"
                        href="<?= url('/review.php?hasil_id=' . (int)$r['id']) ?>"
                        target="_blank"
                        rel="noopener"
                    >Review ↗</a>
                </td>
            </tr>

            <?php if (count($materi) > 1): ?>
              <tr class="rn-detail">
                  <td colspan="7">
                      <details>
                          <summary>Rincian per materi (<?= count($materi) ?>)</summary>
                          <div class="rn-materi">
                              <?php foreach ($materi as $x):
                                  $persen = (float)$x['total_bobot_maksimal'] > 0
                                      ? round(
                                          $x['total_bobot_didapat']
                                          / $x['total_bobot_maksimal'] * 100
                                      )
                                      : 0;
                              ?>
                              <div class="rn-materi-item">
                                  <span class="rn-materi-nama">
                                      <?= e($x['nama']) ?>
                                  </span>
                                  <span class="rn-bar">
                                      <span
                                          class="<?= $persen >= 70 ? 'lulus' : 'kurang' ?>"
                                          style="width: <?= min(100, max(0, $persen)) ?>%"
                                      ></span>
                                  </span>
                                  <span class="rn-materi-skor">
                                      <?= (int)$x['total_bobot_didapat'] ?>/<?= (int)$x['total_bobot_maksimal'] ?>
                                  </span>
                              </div>
                              <?php endforeach; ?>
                          </div>
                      </details>
                  </td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1):
          $url_halaman = fn(int $p) => url('/admin/hasil.php?' . http_build_query(array_merge($_GET, ['page' => $p])));
          $awal  = max(1, $halaman - 2);
          $akhir = min($pages, $halaman + 2);
      ?>
      <nav class="rn-halaman" aria-label="Halaman">
        <span class="rn-info">
          <?= $offset + 1 ?>–<?= min($offset + $per_halaman, $total_rows) ?> dari <?= $total_rows ?>
        </span>
        <div class="rn-halaman-tombol">
          <?php if ($halaman > 1): ?>
            <a href="<?= $url_halaman($halaman - 1) ?>" aria-label="Halaman sebelumnya">‹</a>
          <?php else: ?>
            <span class="mati">‹</span>
          <?php endif; ?>

          <?php for ($p = $awal; $p <= $akhir; $p++): ?>
            <?php if ($p === $halaman): ?>
              <span class="aktif" aria-current="page"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= $url_halaman($p) ?>"><?= $p ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($halaman < $pages): ?>
            <a href="<?= $url_halaman($halaman + 1) ?>" aria-label="Halaman berikutnya">›</a>
          <?php else: ?>
            <span class="mati">›</span>
          <?php endif; ?>
        </div>
      </nav>
      <?php endif; ?>

      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada peserta yang menyelesaikan sesi ini</strong></p>
        <p>Data akan muncul setelah peserta submit jawaban.</p>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>