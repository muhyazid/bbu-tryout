<?php
require_once '../config.php';
cekPeserta();

$sesi_id          = (int)($_GET['sesi_id'] ?? 0);
$peserta_id       = $_SESSION['peserta_id'];
$peserta_paket_id = $_SESSION['peserta_paket_id'];
$peserta_nama     = $_SESSION['peserta_nama'];

$sesi = validasi_sesi_peserta($conn, $sesi_id, $peserta_paket_id);
if (!$sesi) redirect('/peserta/dashboard.php');

// Cek status pengerjaan yang sudah ada
$cek = $conn->prepare("SELECT * FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
$cek->bind_param("ii", $peserta_id, $sesi_id);
$cek->execute();
$hasil_existing = $cek->get_result()->fetch_assoc();

if ($hasil_existing && $hasil_existing['status_pengerjaan'] === 'selesai') {
    redirect('/peserta/dashboard.php');
}

// Cek apakah kelompok peserta ini sedang dijeda — kalau ya, tolak akses
$cek_kelompok = $conn->prepare("
    SELECT kg.status FROM kelompok_anggota ka
    JOIN kelompok_angkatan kg ON ka.kelompok_id = kg.id
    WHERE ka.peserta_id = ?
");
$cek_kelompok->bind_param("i", $peserta_id);
$cek_kelompok->execute();
$status_kelompok = $cek_kelompok->get_result()->fetch_assoc();

if ($status_kelompok && $status_kelompok['status'] === 'dijeda') {
    redirect('/peserta/dashboard.php');
}

// Kalau BELUM PERNAH mulai sama sekali, catat waktu_mulai SEKARANG
if (!$hasil_existing) {
    $ins_mulai = $conn->prepare("
        INSERT INTO hasil_ujian (peserta_id, sesi_id, status_pengerjaan, waktu_mulai, total_soal, total_benar, total_salah, skor, durasi_detik)
        VALUES (?, ?, 'sedang_dikerjakan', NOW(), 0, 0, 0, 0, 0)
    ");
    $ins_mulai->bind_param("ii", $peserta_id, $sesi_id);
    $ins_mulai->execute();

    // Ambil ulang row yang baru dibuat
    $cek->execute();
    $hasil_existing = $cek->get_result()->fetch_assoc();
}

$phase  = 'exam';
$result = null;

// ── LOAD SOAL (cache di session) ──────────────────────────
// Menyimpan soal beserta info mapel/kategori untuk pengelompokan grid
$skey = 'ujian_' . $sesi_id . '_' . $peserta_id;

if (!isset($_SESSION[$skey])) {
    // Ambil SELURUH bank soal dari semua materi yang terhubung ke paket peserta.
    // jumlah_soal pada sesi lama tetap disimpan untuk kompatibilitas database,
    // tetapi tidak lagi digunakan untuk memangkas jumlah soal.
    $stmt = $conn->prepare("
        SELECT s.id, k.id AS kat_id, k.nama AS kat_nama
        FROM soal s
        JOIN kategori k ON s.kategori_id = k.id
        JOIN paket_kategori pk ON pk.kategori_id = k.id
        WHERE pk.paket_id = ?
        ORDER BY k.id, s.id
    ");
    $stmt->bind_param("i", $peserta_paket_id);
    $stmt->execute();
    $res = $stmt->get_result();

    // Kelompokkan seluruh soal per materi. Urutan soal diacak di dalam materi,
    // tetapi tidak ada butir yang dibuang dari bank soal paket.
    $per_kat = [];
    while ($r = $res->fetch_assoc()) {
        $kid = (int)$r['kat_id'];
        if (!isset($per_kat[$kid])) {
            $per_kat[$kid] = [
                'id'    => $kid,
                'nama'  => $r['kat_nama'],
                'soals' => [],
            ];
        }
        $per_kat[$kid]['soals'][] = (int)$r['id'];
    }

    $soal_ids = [];
    $mapel_map = [];
    $mapel_ranges = [];
    $no_global = 1;

    foreach ($per_kat as $kat) {
        shuffle($kat['soals']);
        $jumlah_materi = count($kat['soals']);
        if ($jumlah_materi === 0) continue;

        $start = $no_global;
        foreach ($kat['soals'] as $no_dalam_kat => $sid) {
            $soal_ids[] = $sid;
            $mapel_map[$sid] = [
                'kat_id'          => (int)$kat['id'],
                'kat_nama'        => $kat['nama'],
                'no_dalam_kat'    => $no_dalam_kat + 1,
                'total_dalam_kat' => $jumlah_materi,
            ];
            $no_global++;
        }

        $mapel_ranges[] = [
            'id'     => (int)$kat['id'],
            'nama'   => $kat['nama'],
            'start'  => $start,
            'end'    => $no_global - 1,
            'jumlah' => $jumlah_materi,
        ];
    }

    $_SESSION[$skey] = [
        'ids'          => $soal_ids,
        'mapel_map'    => $mapel_map,
        'mapel_ranges' => $mapel_ranges,
    ];
}
$soal_ids     = $_SESSION[$skey]['ids'];
$mapel_map    = $_SESSION[$skey]['mapel_map'];
$mapel_ranges = $_SESSION[$skey]['mapel_ranges'];

// Normalisasi metadata cache lama tanpa mengganti daftar soal yang sedang dikerjakan.
// Dengan begitu, sesi yang sudah berlangsung tidak mendadak berubah dari 50 soal menjadi ratusan.
$butuh_metadata_baru = false;
foreach ($mapel_map as $meta_lama) {
    if (!isset($meta_lama['kat_id'])) {
        $butuh_metadata_baru = true;
        break;
    }
}
foreach ($mapel_ranges as $range_lama) {
    if (!isset($range_lama['id']) || !isset($range_lama['jumlah'])) {
        $butuh_metadata_baru = true;
        break;
    }
}

if ($butuh_metadata_baru && !empty($soal_ids)) {
    $id_str_meta = implode(',', array_map('intval', $soal_ids));
    $lookup_meta = [];
    $meta_res = $conn->query("
        SELECT s.id, k.id AS kat_id, k.nama AS kat_nama
        FROM soal s
        JOIN kategori k ON k.id = s.kategori_id
        WHERE s.id IN ($id_str_meta)
    ");
    while ($meta_row = $meta_res->fetch_assoc()) {
        $lookup_meta[(int)$meta_row['id']] = [
            'kat_id' => (int)$meta_row['kat_id'],
            'kat_nama' => $meta_row['kat_nama'],
        ];
    }

    foreach ($mapel_map as $sid_meta => &$meta_lama) {
        if (!isset($meta_lama['kat_id'])) {
            $meta_lama['kat_id'] = $lookup_meta[(int)$sid_meta]['kat_id'] ?? 0;
        }
    }
    unset($meta_lama);

    foreach ($mapel_ranges as &$range_lama) {
        $first_id = $soal_ids[((int)$range_lama['start']) - 1] ?? 0;
        $range_lama['id'] = (int)($range_lama['id'] ?? ($lookup_meta[(int)$first_id]['kat_id'] ?? 0));
        $range_lama['jumlah'] = (int)($range_lama['jumlah'] ?? max(0, (int)$range_lama['end'] - (int)$range_lama['start'] + 1));
    }
    unset($range_lama);

    $_SESSION[$skey]['mapel_map'] = $mapel_map;
    $_SESSION[$skey]['mapel_ranges'] = $mapel_ranges;
}


// ── HANDLE SUBMIT ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ujian'])) {
    // Sudah pernah dikirim: jangan proses ulang
    if ($hasil_existing && $hasil_existing['status_pengerjaan'] === 'selesai') {
        redirect('/peserta/dashboard.php');
    }

    // 1. Jawaban dari form hanya diterima jika ujian masih berjalan.
    //    Setiap jawaban divalidasi lewat fungsi yang sama dengan autosave.
    if (!waktu_habis($hasil_existing, (int) $sesi['durasi_menit'])) {
        foreach ($soal_ids as $sid) {
            $nilai = $_POST['jwb_' . $sid] ?? null;
            if ($nilai !== null && $nilai !== '') {
                simpan_jawaban_server($conn, $peserta_id, $sesi_id, (int) $sid, $nilai);
            }
        }
    }

    // 2. Skor dihitung dari tabel jawaban, bukan dari POST
    $h = hitung_hasil($conn, $peserta_id, $sesi_id, $soal_ids);

    // 3. Durasi dihitung di server
    $durasi_maks = (int) $sesi['durasi_menit'] * 60;
    $sisa        = hitung_sisa_waktu_detik($hasil_existing, (int) $sesi['durasi_menit']);
    $durasi      = max(0, min($durasi_maks, $durasi_maks - $sisa));

    $upd = $conn->prepare("
        UPDATE hasil_ujian
        SET total_soal=?, total_benar=?, total_salah=?, skor=?, durasi_detik=?,
            status_pengerjaan='selesai', submitted_at=NOW()
        WHERE peserta_id=? AND sesi_id=?
    ");
    $upd->bind_param("iiiiiii", $h['total'], $h['benar'], $h['salah'], $h['skor'], $durasi, $peserta_id, $sesi_id);
    $upd->execute();

    $hasil_ujian_id = (int) $hasil_existing['id'];
    $ins_materi = $conn->prepare("
        INSERT INTO hasil_per_materi (hasil_ujian_id, kategori_id, total_bobot_didapat, total_bobot_maksimal)
        VALUES (?,?,?,?)
    ");
    foreach ($h['per_kategori'] as $kat_id => $r) {
        $ins_materi->bind_param("iiii", $hasil_ujian_id, $kat_id, $r['didapat'], $r['maksimal']);
        if (!$ins_materi->execute()) {
            error_log("Gagal insert hasil_per_materi: kategori=$kat_id hasil=$hasil_ujian_id");
        }
    }

    $lg = $conn->prepare("INSERT INTO log_aktivitas (peserta_id, sesi_id, jenis) VALUES (?,?,'submit')");
    $lg->bind_param("ii", $peserta_id, $sesi_id);
    $lg->execute();

    unset($_SESSION[$skey]);
    $phase  = 'result';
    $result = [
        'skor'   => $h['skor'],
        'benar'  => $h['benar'],
        'salah'  => $h['salah'],
        'total'  => $h['total'],
        'durasi' => $durasi,
    ];
}

// ── MUAT SOAL UNTUK TAMPILAN ──────────────────────────────
$soal_arr = [];
if ($phase === 'exam' && !empty($soal_ids)) {
    $id_str   = implode(',', array_map('intval', $soal_ids));
    $soal_res = $conn->query("SELECT * FROM soal WHERE id IN ($id_str) ORDER BY FIELD(id,$id_str)");
    while ($s = $soal_res->fetch_assoc()) $soal_arr[] = $s;
}

$page_title    = 'Ujian — ' . e($sesi['nama_sesi']);
$sisa_detik_awal = hitung_sisa_waktu_detik($hasil_existing, (int)$sesi['durasi_menit']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="<?= $phase === 'result' ? 'peserta-page' : 'exam-page' ?>">

<?php if ($phase === 'result' && $result): ?>
<!-- ══════════════ HALAMAN HASIL ══════════════ -->
<?php $lulus = $result['skor'] >= 70; ?>
<div class="result-page">
  <div class="result-card">

    <div class="result-card-header">
      <p>Hasil Ujian</p>
      <div class="result-score"><?= (int)$result['skor'] ?></div>
      <p class="result-score-label">Nilai akhir (skala 0–100)</p>
    </div>

    <div class="result-badge <?= $lulus ? 'result-badge--pass' : 'result-badge--fail' ?>">
      <?= $lulus ? '✓ Lulus' : '✗ Belum memenuhi standar' ?>
      <small>
        <?= $lulus
          ? 'Nilai Anda memenuhi batas kelulusan (minimal 70).'
          : 'Nilai minimum kelulusan adalah 70. Tetap semangat!' ?>
      </small>
    </div>

    <div class="result-details">
      <div class="result-row">
        <span>Jawaban benar</span>
        <strong style="color:var(--success)"><?= (int)$result['benar'] ?> soal</strong>
      </div>
      <div class="result-row">
        <span>Jawaban salah / tidak dijawab</span>
        <strong style="color:var(--danger)"><?= (int)$result['salah'] ?> soal</strong>
      </div>
      <div class="result-row">
        <span>Total soal</span>
        <strong><?= (int)$result['total'] ?> soal</strong>
      </div>
      <div class="result-row">
        <span>Waktu pengerjaan</span>
        <strong>
          <?php
            $m = floor($result['durasi'] / 60);
            $s = $result['durasi'] % 60;
            echo "{$m} menit {$s} detik";
          ?>
        </strong>
      </div>
    </div>

    <a href="<?= url('/peserta/dashboard.php') ?>" class="btn-back-dashboard">
      Kembali ke beranda
    </a>
  </div>
</div>

<?php else: ?>
<!-- ══════════════ HALAMAN UJIAN ══════════════ -->

<div class="exam-body-wrap">

  <!-- Topbar -->
  <header class="exam-topbar">
    <div class="exam-title">
      <span>BBU Tryout</span>
      <span class="exam-subtitle"><?= e($sesi['nama_sesi']) ?></span>
    </div>
    <div class="exam-timer-wrap">
      <span class="exam-timer-label">Sisa waktu</span>
      <div id="timer" class="exam-timer"><?php
        if ($sisa_detik_awal >= 3600) {
            echo sprintf('%02d:%02d:%02d', floor($sisa_detik_awal / 3600), floor(($sisa_detik_awal % 3600) / 60), $sisa_detik_awal % 60);
        } else {
            echo sprintf('%02d:%02d', floor($sisa_detik_awal / 60), $sisa_detik_awal % 60);
        }
      ?></div>
    </div>
    <div class="exam-user">
      <span><?= e($peserta_nama) ?></span>
      <span><?= e($_SESSION['peserta_paket_nama']) ?></span>
    </div>
  </header>

  
  <!-- Form ujian -->
  <form method="POST" id="exam-form">
    <input type="hidden" name="submit_ujian" value="1">
    <input type="hidden" name="durasi_detik" id="durasi_detik" value="0">

    <div class="exam-content-area">
      <div class="exam-workspace">

      <!-- Area utama: soal -->
      <div class="exam-main">

        <?php if (!empty($soal_arr)): ?>
        <p class="exam-save-status" id="autosave-status" aria-live="polite">Jawaban tersimpan otomatis saat dipilih.</p>
        <?php endif; ?>

        <?php if (empty($soal_arr)): ?>
        <div class="exam-empty">
          <p><strong>Bank soal belum tersedia</strong></p>
          <p>Silakan hubungi pengawas atau admin untuk menambahkan soal.</p>
        </div>
        <?php else: ?>

        <?php
        // Ambil jawaban yang sudah tersimpan sebelumnya (untuk kasus resume setelah dijeda)
        $jawaban_tersimpan = [];
        if (!empty($soal_ids)) {
            $id_str_jwb = implode(',', array_map('intval', $soal_ids));
            $res_jwb = $conn->query("
                SELECT soal_id, jawaban_dipilih FROM jawaban
                WHERE peserta_id=$peserta_id AND sesi_id=$sesi_id AND soal_id IN ($id_str_jwb)
            ");
            while ($rj = $res_jwb->fetch_assoc()) {
                $jawaban_tersimpan[$rj['soal_id']] = $rj['jawaban_dipilih'];
            }
        }
        ?>

        <?php foreach ($soal_arr as $i => $soal):
            $no_global   = $i + 1;
            $meta_soal   = $mapel_map[$soal['id']] ?? null;
            $kat_nama    = $meta_soal['kat_nama']       ?? '';
            $no_kat      = $meta_soal['no_dalam_kat']   ?? $no_global;
            $total_kat   = $meta_soal['total_dalam_kat'] ?? count($soal_arr);

            // Tampilkan penanda mapel kalau soal pertama di kategori ini
            $prev_meta = $i > 0 ? ($mapel_map[$soal_arr[$i-1]['id']] ?? null) : null;
            $ganti_mapel = ($prev_meta === null || ($prev_meta['kat_nama'] ?? '') !== $kat_nama);
        ?>

        <?php if ($ganti_mapel && $kat_nama): ?>
        <!-- Penanda mapel — clean & subtle -->
        <div class="mapel-indicator" id="mapel-indicator-<?= $no_global ?>">
          <span class="mapel-indicator-label">Mapel</span>
          <span class="mapel-indicator-name"><?= e($kat_nama) ?></span>
          <div class="mapel-indicator-line"></div>
        </div>
        <?php endif; ?>

        <!-- Soal card -->
        <article class="soal-card" id="card-<?= (int)$soal['id'] ?>"
                 data-no="<?= (int)$no_global ?>"
                 data-category-id="<?= (int)($meta_soal['kat_id'] ?? 0) ?>">
          <div class="soal-meta">
            <span class="soal-num">Soal <strong><?= $no_kat ?></strong> dari <?= $total_kat ?></span>
          </div>
          <div class="soal-text"><?= nl2br(e($soal['pertanyaan'])) ?></div>
          <div class="jwb-grid" role="group" aria-label="Pilihan jawaban soal <?= $no_global ?>">
            <?php
            $jwb_sudah_dipilih = $jawaban_tersimpan[$soal['id']] ?? null;
            ?>
            <?php foreach (opsi_soal($soal) as $opt => $teks_pilihan):
                $sudah_selected = ($jwb_sudah_dipilih === $opt);
            ?>
            <button type="button" class="jwb-option<?= $sudah_selected ? ' selected' : '' ?>"
                    id="btn-<?= $soal['id'] ?>-<?= $opt ?>"
                    onclick="pilihJawaban(<?= $soal['id'] ?>,'<?= $opt ?>')">
              <span class="jwb-letter" <?= $sudah_selected ? 'style="background:var(--accent);color:#fff;"' : '' ?>><?= $opt ?></span>
              <span><?= e($teks_pilihan) ?></span>
              <input type="radio" name="jwb_<?= $soal['id'] ?>" value="<?= $opt ?>"
                    id="r-<?= $soal['id'] ?>-<?= $opt ?>" style="display:none;"
                    <?= $sudah_selected ? 'checked' : '' ?>>
            </button>
            <?php endforeach; ?>
          </div>
        </article>

        <?php endforeach; ?>

        <?php endif; ?>

        <?php if (!empty($soal_arr)): ?>
        <!-- Navigasi ditempatkan langsung di bawah kartu soal agar mudah dijangkau. -->
        <footer class="exam-footer" aria-label="Navigasi soal">
            <button type="button" id="btn-prev" onclick="prevSoal()" class="btn-secondary">
                ← Sebelumnya
            </button>
            <span class="exam-footer-count" id="exam-footer-count">Soal 1 dari <?= count($soal_arr) ?></span>
            <button type="button" id="btn-next" onclick="nextSoal()" class="btn-primary">
                Selanjutnya →
            </button>
        </footer>

        <?php endif; ?>

      </div><!-- /exam-main -->

      <?php if (!empty($soal_arr)): ?>
      <aside class="exam-sidebar" aria-label="Navigasi nomor soal">
        <section class="question-palette" id="question-palette">
          <div class="question-palette-heading">
            <div>
              <h2>Menu soal</h2>
              <p>Pilih nomor untuk berpindah soal</p>
            </div>
            <span class="palette-total" id="palette-total-count">0/<?= count($soal_arr) ?></span>
          </div>
          <div class="question-palette-legend" aria-label="Keterangan status soal">
            <span><i class="legend-dot legend-dot--current"></i> Aktif</span>
            <span><i class="legend-dot legend-dot--answered"></i> Dijawab</span>
            <span><i class="legend-dot legend-dot--unanswered"></i> Kosong</span>
          </div>
          <div class="question-number-grid">
            <?php foreach ($soal_arr as $nav_index => $nav_soal):
                $nav_no = $nav_index + 1;
                $nav_soal_id = (int)$nav_soal['id'];
            ?>
            <button type="button" class="question-number"
                    data-question-no="<?= $nav_no ?>"
                    data-question-id="<?= $nav_soal_id ?>"
                    data-question-label="Soal <?= $nav_no ?>"
                    aria-label="Buka soal <?= $nav_no ?>"
                    title="Soal <?= $nav_no ?>"><?= $nav_no ?></button>
            <?php endforeach; ?>
          </div>
        </section>
      </aside>
      <?php endif; ?>

      </div><!-- /exam-workspace -->
    </div><!-- /exam-content-area -->

  </form>

</div><!-- /exam-body-wrap -->

<!-- Modal submit -->
<div class="modal-overlay" id="overlay-submit" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-icon modal-icon--info" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
    </div>
    <h2 class="modal-title">Kirim jawaban sekarang?</h2>
    <p class="modal-text" id="submit-msg">
      Pastikan semua soal sudah dijawab. Setelah dikirim, jawaban tidak dapat diubah.
    </p>
    <div class="modal-actions">
      <button type="button" class="modal-btn modal-btn--primary" onclick="doSubmit()">Ya, kirim jawaban</button>
      <button type="button" class="modal-btn modal-btn--secondary" onclick="tutupOverlay('overlay-submit')">Periksa lagi</button>
    </div>
  </div>
</div>

<!-- Modal fullscreen -->
<div class="modal-overlay" id="overlay-fullscreen" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-icon modal-icon--warn" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <h2 class="modal-title">Mode layar penuh diperlukan</h2>
    <p class="modal-text">Ujian berjalan dalam layar penuh. Tekan tombol di bawah untuk melanjutkan.</p>
    <div class="modal-actions">
      <button type="button" class="modal-btn modal-btn--nav" onclick="kembaliFullscreen()">Aktifkan layar penuh</button>
    </div>
  </div>
</div>

<!-- Modal pindah tab -->
<div class="modal-overlay" id="overlay-tab" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-icon modal-icon--alert" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    </div>
    <h2 class="modal-title">Perhatian</h2>
    <p class="modal-text">
      Anda terdeteksi meninggalkan halaman ujian.<br>
      Kejadian ini <strong>dicatat oleh sistem</strong>.<br><br>
      Total peringatan: <strong id="tab-count" style="color:var(--danger);font-size:1.1rem;">0</strong> kali
    </p>
    <div class="modal-actions">
      <button type="button" class="modal-btn modal-btn--danger" onclick="tutupOverlay('overlay-tab')">Mengerti, lanjutkan ujian</button>
    </div>
  </div>
</div>

<script>
const SESI_ID       = <?= $sesi_id ?>;
const MODE          = '<?= e($sesi['mode']) ?>';
const TOTAL_SOAL    = <?= count($soal_arr) ?>;

// ── NAVIGASI UJIAN: SATU SOAL PER LAYAR + PALET NOMOR PER MATERI ──
const MATERI_RANGES = <?= json_encode(array_map(static function ($mr) {
    return [
        'id' => (int)$mr['id'],
        'nama' => (string)$mr['nama'],
        'start' => (int)$mr['start'],
        'end' => (int)$mr['end'],
        'jumlah' => (int)$mr['jumlah'],
    ];
}, $mapel_ranges), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

let currentNo = 1;
let autoAdvanceTimer = null;
const allCards = Array.from(document.querySelectorAll('.soal-card'));
const allIndicators = Array.from(document.querySelectorAll('.mapel-indicator'));
const answeredIds = new Set(
    allCards
        .filter(card => card.querySelector('input[type="radio"]:checked'))
        .map(card => card.id.replace('card-', ''))
);
let answered = answeredIds.size;

function materiUntukSoal(no) {
    return MATERI_RANGES.find(materi => no >= materi.start && no <= materi.end) || MATERI_RANGES[0] || null;
}

function tampilSoal(no) {
    if (!allCards.length) return;
    if (autoAdvanceTimer) {
        clearTimeout(autoAdvanceTimer);
        autoAdvanceTimer = null;
    }
    no = Math.max(1, Math.min(TOTAL_SOAL, Number(no) || 1));

    allCards.forEach((card, index) => card.classList.toggle('aktif', index === no - 1));
    allIndicators.forEach(indicator => indicator.classList.remove('aktif'));

    const materi = materiUntukSoal(no);
    if (materi) {
        const indicator = document.getElementById('mapel-indicator-' + materi.start);
        if (indicator) indicator.classList.add('aktif');
    }

    currentNo = no;
    const selectMateri = document.getElementById('select-materi');
    if (materi && selectMateri) selectMateri.value = String(materi.start);

    const footerCount = document.getElementById('exam-footer-count');
    if (footerCount) footerCount.textContent = 'Soal ' + no + ' dari ' + TOTAL_SOAL;

    updateNavBtn();
    updateProgress();
    updateQuestionPalette();

    const main = document.querySelector('.exam-main');
    if (main) main.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateQuestionPalette() {
    const buttons = Array.from(document.querySelectorAll('.question-number'));
    buttons.forEach(button => {
        const no = Number(button.dataset.questionNo);
        const soalId = String(button.dataset.questionId || '');
        const isCurrent = no === currentNo;
        const isAnswered = answeredIds.has(soalId);

        button.classList.toggle('is-current', isCurrent);
        button.classList.toggle('is-answered', isAnswered);
        if (isCurrent) {
            button.setAttribute('aria-current', 'step');
        } else {
            button.removeAttribute('aria-current');
        }
        const status = isCurrent ? 'sedang dibuka' : (isAnswered ? 'sudah dijawab' : 'belum dijawab');
        button.setAttribute('aria-label', 'Buka ' + button.dataset.questionLabel + ' (' + status + ')');
    });

    const paletteCount = document.getElementById('palette-total-count');
    if (paletteCount) paletteCount.textContent = answeredIds.size + '/' + TOTAL_SOAL;
}

function nextSoal() {
    if (currentNo < TOTAL_SOAL) tampilSoal(currentNo + 1);
    else konfirmasiSubmit();
}

function prevSoal() {
    if (currentNo > 1) tampilSoal(currentNo - 1);
}

function updateNavBtn() {
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    if (btnPrev) btnPrev.disabled = currentNo === 1;
    if (btnNext) btnNext.textContent = currentNo === TOTAL_SOAL
        ? 'Periksa & selesaikan →'
        : 'Selanjutnya →';
}

function lompatKeBelumDijawab() {
    if (!allCards.length) return;
    // Cari dari soal setelah posisi saat ini, lalu lanjutkan dari awal jika perlu.
    for (let offset = 1; offset <= TOTAL_SOAL; offset++) {
        const no = ((currentNo - 1 + offset) % TOTAL_SOAL) + 1;
        const soalId = allCards[no - 1].id.replace('card-', '');
        if (!answeredIds.has(soalId)) {
            tampilSoal(no);
            return;
        }
    }
    konfirmasiSubmit();
}

const selectMateri = document.getElementById('select-materi');
if (selectMateri) {
    selectMateri.addEventListener('change', function () {
        tampilSoal(parseInt(this.value, 10));
    });
}

document.querySelectorAll('.question-number').forEach(button => {
    button.addEventListener('click', function () {
        const no = Number(this.dataset.questionNo);
        if (no > 0) tampilSoal(no);
    });
});


let sisaDetik  = <?= $sisa_detik_awal ?>; // timer tetap mengikuti durasi yang diatur admin
let elapsedDtk = 0;
let tabCount   = 0;

// Progres menampilkan materi yang sedang dikerjakan agar peserta fokus pada satu bagian.
// Semua jawaban tetap dilacak secara global untuk fitur mencari soal yang belum dijawab.
function updateProgress() {
    answered = answeredIds.size;
    const paletteCount = document.getElementById('palette-total-count');
    if (paletteCount) paletteCount.textContent = answered + '/' + TOTAL_SOAL;
}

// Inisialisasi state tampilan untuk soal pertama.
tampilSoal(1);
updateProgress();

// ── TIMER ─────────────────────────────────────────────────
const timerEl = document.getElementById('timer');
const timerInterval = setInterval(() => {
    sisaDetik--;
    elapsedDtk++;
    document.getElementById('durasi_detik').value = elapsedDtk;

    const h = Math.floor(sisaDetik / 3600);
    const m = Math.floor((sisaDetik % 3600) / 60);
    const s = sisaDetik % 60;
    const pad = n => String(n).padStart(2, '0');

    if (h > 0) {
        timerEl.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
    } else {
        timerEl.textContent = pad(m) + ':' + pad(s);
    }

    timerEl.className = 'exam-timer';
    if (sisaDetik <= 300) timerEl.classList.add('warn');
    if (sisaDetik <= 60)  timerEl.classList.add('danger');
    if (sisaDetik <= 0)   { clearInterval(timerInterval); autoSubmit(); }
}, 1000);

function autoSubmit() {
    document.getElementById('exam-form').submit();
}

// ── PILIH JAWABAN ─────────────────────────────────────────
function pilihJawaban(soalId, opt) {
    const card = document.getElementById('card-' + soalId);
    if (!card) return;

    card.querySelectorAll('.jwb-option').forEach(btn => btn.classList.remove('selected'));
    card.querySelectorAll('input[type="radio"]').forEach(inp => { inp.checked = false; });

    const selBtn = document.getElementById('btn-' + soalId + '-' + opt);
    const selInp = document.getElementById('r-' + soalId + '-' + opt);
    if (!selBtn || !selInp) return;

    selBtn.classList.add('selected');
    selInp.checked = true;
    answeredIds.add(String(soalId));
    updateProgress();
    updateQuestionPalette();

    const saveStatus = document.getElementById('autosave-status');
    if (saveStatus) {
        saveStatus.textContent = 'Menyimpan jawaban…';
        saveStatus.className = 'exam-save-status is-saving';
    }
    simpanJawabanKeServer(soalId, opt);

    // Beri jeda singkat agar pilihan terlihat, lalu pindah otomatis.
    // Soal terakhir tidak mengirim ujian otomatis supaya peserta masih dapat meninjau jawaban.
    const answeredNo = Number(card.dataset.no);
    if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer);
    if (answeredNo < TOTAL_SOAL) {
        autoAdvanceTimer = setTimeout(() => {
            autoAdvanceTimer = null;
            // Jangan memindahkan posisi jika peserta sudah memilih navigasi lain.
            if (currentNo === answeredNo) tampilSoal(answeredNo + 1);
        }, 220);
    } else if (saveStatus) {
        saveStatus.textContent = 'Soal terakhir dipilih. Periksa kembali jawaban sebelum menyelesaikan ujian.';
        saveStatus.className = 'exam-save-status';
    }
}
function simpanJawabanKeServer(soalId, jawaban) {
    fetch('<?= url('/peserta/simpan_jawaban.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sesi_id: SESI_ID, soal_id: soalId, jawaban: jawaban })
    })
    .then(response => {
        if (!response.ok) throw new Error('Autosave gagal');
        return response.json();
    })
    .then(data => {
        const saveStatus = document.getElementById('autosave-status');
        if (!saveStatus) return;
        if (data && data.ok) {
            saveStatus.textContent = 'Jawaban tersimpan otomatis';
            saveStatus.className = 'exam-save-status is-saved';
        } else {
            saveStatus.textContent = 'Jawaban belum tersinkron. Jawaban tetap akan dikirim saat ujian diselesaikan.';
            saveStatus.className = 'exam-save-status is-error';
        }
    })
    .catch(() => {
        const saveStatus = document.getElementById('autosave-status');
        if (saveStatus) {
            saveStatus.textContent = 'Koneksi autosave terputus. Jawaban tetap tersedia di halaman ini.';
            saveStatus.className = 'exam-save-status is-error';
        }
    });
}

// ── SUBMIT ─────────────────────────────────────────────────
function konfirmasiSubmit() {
    const belum = TOTAL_SOAL - answered;
    let msg = 'Pastikan semua soal sudah dijawab. Setelah dikirim, jawaban tidak dapat diubah.';
    if (belum > 0) {
        msg = '<strong style="color:var(--danger)">Masih ada ' + belum + ' soal belum dijawab.</strong><br><br>' + msg;
    }
    document.getElementById('submit-msg').innerHTML = msg;
    document.getElementById('overlay-submit').classList.add('show');
}

function doSubmit() {
    clearInterval(timerInterval);
    document.getElementById('exam-form').submit();
}

function tutupOverlay(id) {
    document.getElementById(id).classList.remove('show');
}

// ── FULLSCREEN ─────────────────────────────────────────────
function mintaFullscreen() {
    const el = document.documentElement;
    if (el.requestFullscreen)        el.requestFullscreen();
    else if (el.mozRequestFullScreen)    el.mozRequestFullScreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
}

function kembaliFullscreen() {
    mintaFullscreen();
    tutupOverlay('overlay-fullscreen');
}

document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && MODE === 'ujian') {
        document.getElementById('overlay-fullscreen').classList.add('show');
        logAktivitas('keluar_fullscreen');
    }
});

// ── DETEKSI PINDAH TAB ─────────────────────────────────────
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        logAktivitas('pindah_tab');
    } else if (MODE === 'ujian') {
        tabCount++;
        document.getElementById('tab-count').textContent = tabCount;
        document.getElementById('overlay-tab').classList.add('show');
    }
});

// ── DISABLE KLIK KANAN & F12 (mode ujian) ─────────────────
if (MODE === 'ujian') {
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => {
        if (e.key === 'F12' || (e.ctrlKey && ['u','s','a'].includes(e.key.toLowerCase()))) {
            e.preventDefault();
        }
    });
    window.addEventListener('load', () => setTimeout(mintaFullscreen, 800));
}

// ── CEK STATUS KELOMPOK SECARA BERKALA ──────────────────────
// Kalau pengawas jeda kelompok SAAT peserta sedang ujian,
// peserta akan otomatis diarahkan keluar dalam beberapa detik.
setInterval(() => {
    fetch('<?= url('/peserta/cek_status.php') ?>?sesi_id=' + SESI_ID)
        .then(res => res.json())
        .then(data => {
            if (data.dijeda) {
                clearInterval(timerInterval);
                alert('Ujian Anda dijeda oleh pengawas. Anda akan diarahkan ke halaman utama.');
                window.location.href = '<?= url('/peserta/dashboard.php') ?>';
            }
        })
        .catch(() => {}); // diamkan kalau gagal, jangan ganggu ujian
}, 15000); // cek tiap 15 detik

// ── LOG AJAX ───────────────────────────────────────────────
function logAktivitas(jenis) {
    fetch('<?= url('/peserta/log.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sesi_id: SESI_ID, jenis: jenis })
    }).catch(() => {});
}
</script>

<?php endif; ?>
</body>
</html>