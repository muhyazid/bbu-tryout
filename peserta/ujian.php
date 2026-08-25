<?php
require_once '../config.php';
cekPeserta();

$sesi_id       = (int)($_GET['sesi_id'] ?? 0);
$peserta_id    = $_SESSION['peserta_id'];
$peserta_jenis = $_SESSION['peserta_jenis'];
$peserta_nama  = $_SESSION['peserta_nama'];

$sesi = validasi_sesi_peserta($conn, $sesi_id, $peserta_jenis);
if (!$sesi) redirect('/peserta/dashboard.php');

// Cek sudah submit
$cek = $conn->prepare("SELECT id FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
$cek->bind_param("ii", $peserta_id, $sesi_id);
$cek->execute();
if ($cek->get_result()->num_rows > 0) redirect('/peserta/dashboard.php');

$phase  = 'exam';
$result = null;

// ── LOAD SOAL (cache di session) ──────────────────────────
// Menyimpan soal beserta info mapel/kategori untuk pengelompokan grid
$skey = 'ujian_' . $sesi_id . '_' . $peserta_id;

if (!isset($_SESSION[$skey])) {
    // Ambil soal beserta info kategori, diurutkan per kategori
    $stmt = $conn->prepare("
        SELECT s.id, k.id AS kat_id, k.nama AS kat_nama
        FROM soal s
        JOIN kategori k ON s.kategori_id = k.id
        WHERE k.jenis = ?
        ORDER BY k.id, s.id
    ");
    $stmt->bind_param("s", $peserta_jenis);
    $stmt->execute();
    $res = $stmt->get_result();

    // Kelompokkan per kategori dulu
    $per_kat = [];
    while ($r = $res->fetch_assoc()) {
        $kid = $r['kat_id'];
        if (!isset($per_kat[$kid])) {
            $per_kat[$kid] = [
                'nama'  => $r['kat_nama'],
                'soals' => [],
            ];
        }
        $per_kat[$kid]['soals'][] = $r['id'];
    }

    // Shuffle dalam tiap kategori, lalu gabungkan
    $soal_ids   = [];
    $mapel_map  = []; // soal_id => ['kat_nama', 'kat_urut_dalam_kat']
    $mapel_ranges = []; // untuk grid panel kanan

    $jumlah_target = (int)$sesi['jumlah_soal'];
    $total_tersedia = array_sum(array_map(fn($k) => count($k['soals']), $per_kat));
    $per_kat_arr    = array_values($per_kat);
    $n_kat          = count($per_kat_arr);

    // Ambil proporsional dari tiap kategori
    $sisa  = $jumlah_target;
    $kat_result = [];
    foreach ($per_kat_arr as $i => $kat) {
        if ($sisa <= 0) break;
        $porsi = ($i === $n_kat - 1)
            ? $sisa
            : (int)round($jumlah_target * count($kat['soals']) / max($total_tersedia, 1));
        $porsi = min($porsi, count($kat['soals']), $sisa);
        shuffle($kat['soals']);
        $kat_result[] = [
            'nama'  => $kat['nama'],
            'soals' => array_slice($kat['soals'], 0, $porsi),
        ];
        $sisa -= $porsi;
    }

    // Buat flat array + map
    $no_global = 1;
    foreach ($kat_result as $kat) {
        $start = $no_global;
        foreach ($kat['soals'] as $no_dalam_kat => $sid) {
            $soal_ids[] = $sid;
            $mapel_map[$sid] = [
                'kat_nama'      => $kat['nama'],
                'no_dalam_kat'  => $no_dalam_kat + 1,
                'total_dalam_kat' => count($kat['soals']),
            ];
            $no_global++;
        }
        $mapel_ranges[] = [
            'nama'  => $kat['nama'],
            'start' => $start,
            'end'   => $no_global - 1,
        ];
    }

    $_SESSION[$skey] = [
        'ids'           => $soal_ids,
        'mapel_map'     => $mapel_map,
        'mapel_ranges'  => $mapel_ranges,
    ];
}

$soal_ids     = $_SESSION[$skey]['ids'];
$mapel_map    = $_SESSION[$skey]['mapel_map'];
$mapel_ranges = $_SESSION[$skey]['mapel_ranges'];

// ── HANDLE SUBMIT ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ujian'])) {
    $total = count($soal_ids);
    $benar = 0;

    // Ambil semua kunci jawaban sekaligus (hindari N+1 query)
    $id_str    = implode(',', array_map('intval', $soal_ids));
    $kunci_res = $conn->query("SELECT id, jawaban_benar FROM soal WHERE id IN ($id_str)");
    $kunci_map = [];
    while ($k = $kunci_res->fetch_assoc()) {
        $kunci_map[$k['id']] = $k['jawaban_benar'];
    }

    foreach ($soal_ids as $sid2) {
        $jwb = isset($_POST['jwb_'.$sid2]) && $_POST['jwb_'.$sid2] !== ''
               ? $_POST['jwb_'.$sid2]
               : null;
        $ib  = ($jwb && isset($kunci_map[$sid2]) && $jwb === $kunci_map[$sid2]) ? 1 : 0;
        if ($ib) $benar++;

        $ins = $conn->prepare("INSERT INTO jawaban (peserta_id,sesi_id,soal_id,jawaban_dipilih,is_benar) VALUES (?,?,?,?,?)");
        $ins->bind_param("iiisi", $peserta_id, $sesi_id, $sid2, $jwb, $ib);
        if (!$ins->execute()) {
            error_log("Gagal insert jawaban: soal_id=$sid2, peserta_id=$peserta_id");
        }
    }

    $salah  = $total - $benar;
    $skor   = $total > 0 ? round($benar / $total * 100) : 0;
    $durasi = (int)($_POST['durasi_detik'] ?? 0);

    $ins2 = $conn->prepare("INSERT INTO hasil_ujian (peserta_id,sesi_id,total_soal,total_benar,total_salah,skor,durasi_detik) VALUES (?,?,?,?,?,?,?)");
    $ins2->bind_param("iiiiiii", $peserta_id, $sesi_id, $total, $benar, $salah, $skor, $durasi);
    if (!$ins2->execute()) {
        error_log("Gagal insert hasil ujian: peserta_id=$peserta_id, sesi_id=$sesi_id");
    }

    $lg = $conn->prepare("INSERT INTO log_aktivitas (peserta_id,sesi_id,jenis) VALUES (?,?,'submit')");
    $lg->bind_param("ii", $peserta_id, $sesi_id);
    $lg->execute();

    unset($_SESSION[$skey]);
    $phase  = 'result';
    $result = compact('skor','benar','salah','total','durasi');
}

// ── MUAT SOAL UNTUK TAMPILAN ──────────────────────────────
$soal_arr = [];
if ($phase === 'exam' && !empty($soal_ids)) {
    $id_str   = implode(',', array_map('intval', $soal_ids));
    $soal_res = $conn->query("SELECT * FROM soal WHERE id IN ($id_str) ORDER BY FIELD(id,$id_str)");
    while ($s = $soal_res->fetch_assoc()) $soal_arr[] = $s;
}

$page_title  = 'Ujian — ' . e($sesi['nama_sesi']);
$timer_awal  = sprintf('%02d:00', (int)$sesi['durasi_menit']);
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
      <div id="timer" class="exam-timer"><?= $timer_awal ?></div>
    </div>
    <div class="exam-user">
      <span><?= e($peserta_nama) ?></span>
      <span><?= e($peserta_jenis) ?></span>
    </div>
  </header>

  <!-- Form ujian -->
  <form method="POST" id="exam-form">
    <input type="hidden" name="submit_ujian" value="1">
    <input type="hidden" name="durasi_detik" id="durasi_detik" value="0">

    <div class="exam-content-area">

      <!-- Kiri: soal -->
      <div class="exam-main">

        <?php if (empty($soal_arr)): ?>
        <div class="exam-empty">
          <p><strong>Bank soal belum tersedia</strong></p>
          <p>Silakan hubungi pengawas atau admin untuk menambahkan soal.</p>
        </div>
        <?php else: ?>

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
        <article class="soal-card" id="card-<?= $soal['id'] ?>">
          <div class="soal-meta">
            <span class="soal-num">Soal <strong><?= $no_global ?></strong> dari <?= count($soal_arr) ?></span>
            <?php if ($kat_nama): ?>
            <span class="soal-mapel-tag"><?= e($kat_nama) ?> · <?= $no_kat ?> dari <?= $total_kat ?></span>
            <?php endif; ?>
          </div>
          <div class="soal-text"><?= nl2br(e($soal['pertanyaan'])) ?></div>
          <div class="jwb-grid" role="group" aria-label="Pilihan jawaban soal <?= $no_global ?>">
            <?php foreach (['A','B','C','D'] as $opt): ?>
            <button type="button" class="jwb-option"
                    id="btn-<?= $soal['id'] ?>-<?= $opt ?>"
                    onclick="pilihJawaban(<?= $soal['id'] ?>,'<?= $opt ?>')">
              <span class="jwb-letter"><?= $opt ?></span>
              <span><?= e($soal['pilihan_'.strtolower($opt)]) ?></span>
              <input type="radio" name="jwb_<?= $soal['id'] ?>" value="<?= $opt ?>"
                     id="r-<?= $soal['id'] ?>-<?= $opt ?>" style="display:none;">
            </button>
            <?php endforeach; ?>
          </div>
        </article>

        <?php endforeach; ?>

        <?php endif; ?>

      </div><!-- /exam-main -->

      <!-- Kanan: grid soal per mapel -->
      <aside class="exam-sidebar">
        <div class="exam-sidebar-inner">
          <?php foreach ($mapel_ranges as $mr): ?>
          <div class="grid-mapel-section">
            <div class="grid-mapel-label"><?= e($mr['nama']) ?></div>
            <div class="soal-num-grid" id="grid-<?= $mr['start'] ?>">
              <?php for ($n = $mr['start']; $n <= $mr['end']; $n++): ?>
              <button type="button"
                      class="soal-num-btn unanswered"
                      id="gnum-<?= $n ?>"
                      onclick="scrollToSoal(<?= $n ?>)">
                <?= $n ?>
              </button>
              <?php endfor; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="grid-legend">
            <div class="legend-row"><div class="legend-dot current"></div> Saat ini</div>
            <div class="legend-row"><div class="legend-dot answered"></div> Terjawab</div>
            <div class="legend-row"><div class="legend-dot unanswered"></div> Belum</div>
          </div>
        </div>
      </aside>

    </div><!-- /exam-content-area -->

    <!-- Footer -->
    <footer class="exam-footer">
        <button type="button" id="btn-prev"
                onclick="prevSoal()"
                class="btn-secondary"
                style="padding:8px 18px;">
            ← Sebelumnya
        </button>
        <p class="exam-footer-progress">
            Soal <strong id="current-no">1</strong> dari <?= count($soal_arr) ?> &nbsp;·&nbsp;
            Terjawab: <strong id="answered-count">0</strong>
        </p>
        <button type="button" id="btn-next"
                onclick="nextSoal()"
                class="btn-primary"
                style="padding:8px 18px;">
            Selanjutnya →
        </button>
    </footer>

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
const SESI_ID      = <?= $sesi_id ?>;
const DURASI_DETIK = <?= $sesi['durasi_menit'] * 60 ?>;
const MODE         = '<?= e($sesi['mode']) ?>';
const TOTAL_SOAL   = <?= count($soal_arr) ?>;
// ── ONE QUESTION PER SCREEN ────────────────────────────────
let currentNo = 1; // soal yang sedang ditampilkan (1-based)

const allCards    = document.querySelectorAll('.soal-card');
const allIndicators = document.querySelectorAll('.mapel-indicator');

function tampilSoal(no) {
    // Sembunyikan semua
    allCards.forEach(c => c.classList.remove('aktif'));
    allIndicators.forEach(i => i.classList.remove('aktif'));

    // Tampilkan soal ke-no
    if (allCards[no - 1]) allCards[no - 1].classList.add('aktif');

    // Tampilkan mapel indicator yang relevan
    // Cari indicator terdekat sebelum soal ini
    const card = allCards[no - 1];
    if (card) {
        let prev = card.previousElementSibling;
        while (prev) {
            if (prev.classList.contains('mapel-indicator')) {
                prev.classList.add('aktif');
                break;
            }
            if (prev.classList.contains('soal-card')) break;
            prev = prev.previousElementSibling;
        }
    }

    // Update grid nomor
    document.querySelectorAll('.soal-num-btn').forEach(btn => {
        btn.classList.remove('current');
    });
    const gBtn = document.getElementById('gnum-' + no);
    if (gBtn) gBtn.classList.add('current');

    // Update tombol prev/next
    currentNo = no;
    updateNavBtn();

    // Scroll soal ke atas
    if (allCards[no - 1]) allCards[no - 1].scrollIntoView({ block: 'start' });
}

function nextSoal() {
    if (currentNo < TOTAL_SOAL) {
        tampilSoal(currentNo + 1);
    } else {
        konfirmasiSubmit();
    }
}

function prevSoal() {
    if (currentNo > 1) tampilSoal(currentNo - 1);
}

function updateNavBtn() {
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    if (btnPrev) btnPrev.disabled = (currentNo === 1);
    if (btnNext) btnNext.textContent = currentNo === TOTAL_SOAL
        ? 'Selesai & kirim jawaban'
        : 'Selanjutnya →';
}

// Override scrollToSoal agar pakai one-page mode
function scrollToSoal(no) { tampilSoal(no); }

// Inisialisasi — tampilkan soal pertama
tampilSoal(1);

let sisaDetik  = DURASI_DETIK;
let elapsedDtk = 0;
let tabCount   = 0;
let answered   = 0;

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
    ['A','B','C','D'].forEach(o => {
        const btn = document.getElementById('btn-' + soalId + '-' + o);
        const inp = document.getElementById('r-' + soalId + '-' + o);
        btn.classList.remove('selected');
        const ltr = btn.querySelector('.jwb-letter');
        ltr.style.background = '';
        ltr.style.color = '';
        inp.checked = false;
    });

    const selBtn = document.getElementById('btn-' + soalId + '-' + opt);
    const selInp = document.getElementById('r-' + soalId + '-' + opt);
    selBtn.classList.add('selected');
    selBtn.querySelector('.jwb-letter').style.background = 'var(--accent)';
    selBtn.querySelector('.jwb-letter').style.color = '#fff';
    selInp.checked = true;

    // Tandai grid nomor soal
    const card = document.getElementById('card-' + soalId);
    const noGlobal = parseInt(card.dataset.no || card.getAttribute('data-no'));

    // Cari nomor global dari posisi di soal_arr
    const allCards = document.querySelectorAll('.soal-card');
    let cardNo = 0;
    allCards.forEach((c, idx) => {
        if (c.id === 'card-' + soalId) cardNo = idx + 1;
    });

    if (cardNo > 0) {
        const gBtn = document.getElementById('gnum-' + cardNo);
        if (gBtn && !gBtn.classList.contains('answered')) {
            gBtn.classList.remove('unanswered');
            gBtn.classList.add('answered');
            answered++;
            updateProgress();
        }
    }
}


function updateProgress() {
    document.getElementById('answered-count').textContent = answered;
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