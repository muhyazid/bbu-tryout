<?php
require_once '../config.php';
cekPeserta();

$sesi_id       = (int)($_GET['sesi_id'] ?? 0);
$peserta_id    = $_SESSION['peserta_id'];
$peserta_jenis = $_SESSION['peserta_jenis'];
$peserta_nama  = $_SESSION['peserta_nama'];

// Validasi sesi
$st = $conn->prepare("SELECT * FROM sesi_ujian WHERE id=? AND status='aktif' AND jenis=?");
$st->bind_param("is", $sesi_id, $peserta_jenis);
$st->execute();
$sesi = $st->get_result()->fetch_assoc();
if (!$sesi) redirect('/bbu-tryout/peserta/dashboard.php');

// Cek sudah submit
$cek = $conn->prepare("SELECT id FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
$cek->bind_param("ii", $peserta_id, $sesi_id);
$cek->execute();
if ($cek->get_result()->num_rows > 0) redirect('/bbu-tryout/peserta/dashboard.php');

$phase = 'exam';
$result = null;

// Load soal (cache di session agar tidak acak ulang saat refresh)
$skey = 'ujian_' . $sesi_id . '_' . $peserta_id;
if (!isset($_SESSION[$skey])) {
    $res = $conn->query("
        SELECT s.id FROM soal s
        JOIN kategori k ON s.kategori_id = k.id
        WHERE k.jenis = '$peserta_jenis'
    ");
    $ids = [];
    while ($r = $res->fetch_assoc()) $ids[] = $r['id'];
    shuffle($ids);
    $_SESSION[$skey] = array_slice($ids, 0, min($sesi['jumlah_soal'], count($ids)));
}

$soal_ids = $_SESSION[$skey];

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ujian'])) {
    $total = count($soal_ids);
    $benar = 0;

    foreach ($soal_ids as $sid2) {
        $jwb = isset($_POST['jwb_'.$sid2]) && $_POST['jwb_'.$sid2] !== '' ? $_POST['jwb_'.$sid2] : null;
        $q   = $conn->query("SELECT jawaban_benar FROM soal WHERE id=$sid2")->fetch_assoc();
        $ib  = ($jwb && $q && $jwb === $q['jawaban_benar']) ? 1 : 0;
        if ($ib) $benar++;

        $ins = $conn->prepare("INSERT INTO jawaban (peserta_id,sesi_id,soal_id,jawaban_dipilih,is_benar) VALUES (?,?,?,?,?)");
        $ins->bind_param("iiisi", $peserta_id, $sesi_id, $sid2, $jwb, $ib);
        $ins->execute();
    }

    $salah   = $total - $benar;
    $skor    = $total > 0 ? round($benar / $total * 100) : 0;
    $durasi  = (int)($_POST['durasi_detik'] ?? 0);

    $ins2 = $conn->prepare("INSERT INTO hasil_ujian (peserta_id,sesi_id,total_soal,total_benar,total_salah,skor,durasi_detik) VALUES (?,?,?,?,?,?,?)");
    $ins2->bind_param("iiiiiii", $peserta_id, $sesi_id, $total, $benar, $salah, $skor, $durasi);
    $ins2->execute();

    // Log submit
    $lg = $conn->prepare("INSERT INTO log_aktivitas (peserta_id,sesi_id,jenis) VALUES (?,?,'submit')");
    $lg->bind_param("ii", $peserta_id, $sesi_id);
    $lg->execute();

    unset($_SESSION[$skey]);
    $phase  = 'result';
    $result = compact('skor','benar','salah','total','durasi');
}

// Muat soal untuk tampilan
$soal_arr = [];
if ($phase === 'exam' && !empty($soal_ids)) {
    $id_str   = implode(',', $soal_ids);
    $soal_res = $conn->query("SELECT * FROM soal WHERE id IN ($id_str) ORDER BY FIELD(id,$id_str)");
    while ($s = $soal_res->fetch_assoc()) $soal_arr[] = $s;
}
?>
<?php
$page_title = 'Ujian — ' . $sesi['nama_sesi'];
$timer_awal = sprintf('%02d:00', (int)$sesi['durasi_menit']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="<?= $phase === 'result' ? 'peserta-page' : 'exam-page' ?>">

<?php if ($phase === 'result' && $result): ?>
<?php $lulus = $result['skor'] >= 70; ?>
<div class="result-page">
  <div class="result-card">
    <div class="result-score" style="color:<?= $lulus ? 'var(--p-success)' : 'var(--p-text)' ?>">
      <?= (int)$result['skor'] ?>
    </div>
    <p class="result-score-label">Nilai akhir (skala 0–100)</p>

    <div class="result-badge <?= $lulus ? 'result-badge--pass' : 'result-badge--fail' ?>">
      <?= $lulus ? 'Lulus' : 'Belum memenuhi standar' ?>
      <small>
        <?= $lulus
          ? 'Nilai Anda memenuhi batas kelulusan (minimal 70).'
          : 'Nilai minimum kelulusan adalah 70. Anda tetap dapat melihat rincian di bawah.' ?>
      </small>
    </div>

    <div class="result-details">
      <div class="result-row">
        <span>Jawaban benar</span>
        <strong style="color:var(--p-success)"><?= (int)$result['benar'] ?> soal</strong>
      </div>
      <div class="result-row">
        <span>Jawaban salah</span>
        <strong style="color:var(--p-danger)"><?= (int)$result['salah'] ?> soal</strong>
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

    <a href="/bbu-tryout/peserta/dashboard.php" class="btn-back-dashboard">
      Kembali ke beranda
    </a>
  </div>
</div>

<?php else: ?>
<!-- ===== HALAMAN UJIAN ===== -->

<header class="exam-topbar">
  <div class="exam-title">
    BBU Tryout
    <span class="exam-subtitle"><?= htmlspecialchars($sesi['nama_sesi']) ?></span>
  </div>
  <div class="exam-timer-wrap">
    <div id="timer" class="exam-timer"><?= $timer_awal ?></div>
    <span class="exam-timer-label">Sisa waktu</span>
  </div>
  <div class="exam-user">
    <?= htmlspecialchars($peserta_nama) ?>
    <span><?= htmlspecialchars($peserta_jenis) ?></span>
  </div>
</header>

<div class="exam-progress-track" aria-hidden="true">
  <div id="progress-bar" class="exam-progress-fill"></div>
</div>

<!-- FORM UJIAN -->
<form method="POST" id="exam-form">
  <input type="hidden" name="submit_ujian" value="1">
  <input type="hidden" name="durasi_detik" id="durasi_detik" value="0">

  <div class="exam-content">
    <?php if (empty($soal_arr)): ?>
    <div class="exam-empty">
      <p><strong>Bank soal belum tersedia</strong></p>
      <p>Silakan hubungi pengawas atau admin untuk menambahkan soal.</p>
    </div>
    <?php else: ?>

    <?php foreach ($soal_arr as $i => $soal): ?>
    <article class="soal-card" id="card-<?= $soal['id'] ?>">
      <p class="soal-num">Soal <?= ($i+1) ?> dari <?= count($soal_arr) ?></p>
      <div class="soal-text"><?= nl2br(htmlspecialchars($soal['pertanyaan'])) ?></div>
      <div class="jwb-grid" role="group" aria-label="Pilihan jawaban soal <?= ($i+1) ?>">
        <?php foreach (['A','B','C','D'] as $opt): ?>
        <button type="button" class="jwb-option"
                id="btn-<?= $soal['id'] ?>-<?= $opt ?>"
                onclick="pilihJawaban(<?= $soal['id'] ?>,'<?= $opt ?>')">
          <span class="jwb-letter"><?= $opt ?></span>
          <span><?= htmlspecialchars($soal['pilihan_'.strtolower($opt)]) ?></span>
          <input type="radio" name="jwb_<?= $soal['id'] ?>" value="<?= $opt ?>"
                 id="r-<?= $soal['id'] ?>-<?= $opt ?>" style="display:none;">
        </button>
        <?php endforeach; ?>
      </div>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <footer class="exam-footer">
    <p class="exam-footer-progress">
      Terjawab: <strong id="answered-count">0</strong> dari <?= count($soal_arr) ?> soal
    </p>
    <?php if (!empty($soal_arr)): ?>
    <button type="button" class="btn-submit-exam" onclick="konfirmasiSubmit()">
      Selesai &amp; kirim jawaban
    </button>
    <?php endif; ?>
  </footer>

</form>

<div class="modal-overlay" id="overlay-submit" role="dialog" aria-modal="true" aria-labelledby="submit-title">
  <div class="modal-box">
    <div class="modal-icon modal-icon--info" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    </div>
    <h2 class="modal-title" id="submit-title">Kirim jawaban sekarang?</h2>
    <p class="modal-text" id="submit-msg">
      Pastikan semua soal sudah dijawab. Setelah dikirim, jawaban tidak dapat diubah.
    </p>
    <div class="modal-actions">
      <button type="button" class="modal-btn modal-btn--primary" onclick="doSubmit()">Ya, kirim jawaban</button>
      <button type="button" class="modal-btn modal-btn--secondary" onclick="tutupOverlay('overlay-submit')">Periksa lagi</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="overlay-fullscreen" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-icon modal-icon--warn" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <h2 class="modal-title">Mode layar penuh diperlukan</h2>
    <p class="modal-text">
      Ujian berjalan dalam layar penuh. Tekan tombol di bawah untuk melanjutkan.
    </p>
    <button type="button" class="modal-btn modal-btn--nav" onclick="kembaliFullscreen()">
      Aktifkan layar penuh
    </button>
  </div>
</div>

<div class="modal-overlay" id="overlay-tab" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-icon modal-icon--alert" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <h2 class="modal-title">Perhatian</h2>
    <p class="modal-text">
      Anda terdeteksi meninggalkan halaman ujian (misalnya membuka tab lain).
      Kejadian ini <strong>dicatat oleh sistem</strong>.<br><br>
      Total peringatan: <strong id="tab-count" style="color:var(--p-danger);font-size:1.1rem;">0</strong> kali
    </p>
    <button type="button" class="modal-btn modal-btn--danger" onclick="tutupOverlay('overlay-tab')">
      Mengerti, lanjutkan ujian
    </button>
  </div>
</div>

<script>
const SESI_ID      = <?= $sesi_id ?>;
const DURASI_DETIK = <?= $sesi['durasi_menit'] * 60 ?>;
const MODE         = '<?= $sesi['mode'] ?>';
const TOTAL_SOAL   = <?= count($soal_arr) ?>;

let sisaDetik  = DURASI_DETIK;
let elapsedDtk = 0;
let tabCount   = 0;
let answered   = 0;

// ===== TIMER =====
const timerEl = document.getElementById('timer');
const timerInterval = setInterval(() => {
    sisaDetik--;
    elapsedDtk++;
    document.getElementById('durasi_detik').value = elapsedDtk;

    const m = Math.floor(sisaDetik / 60);
    const s = sisaDetik % 60;
    timerEl.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');

    timerEl.className = 'exam-timer';
    if (sisaDetik <= 300) timerEl.classList.add('warn');
    if (sisaDetik <= 60)  timerEl.classList.add('danger');
    if (sisaDetik <= 0) {
        clearInterval(timerInterval);
        autoSubmit();
    }
}, 1000);

function autoSubmit() {
    document.getElementById('exam-form').submit();
}

// ===== PILIH JAWABAN =====
function pilihJawaban(soalId, opt) {
    ['A','B','C','D'].forEach(o => {
        const btn = document.getElementById('btn-'+soalId+'-'+o);
        const inp = document.getElementById('r-'+soalId+'-'+o);
        btn.classList.remove('selected');
        inp.checked = false;
    });
    document.getElementById('btn-'+soalId+'-'+opt).classList.add('selected');
    document.getElementById('r-'+soalId+'-'+opt).checked = true;

    // Tandai card sudah dijawab
    const card = document.getElementById('card-'+soalId);
    if (!card.classList.contains('answered')) {
        card.classList.add('answered');
        answered++;
        updateProgress();
    }
}

function updateProgress() {
    document.getElementById('answered-count').textContent = answered;
    const pct = TOTAL_SOAL > 0 ? (answered / TOTAL_SOAL * 100) : 0;
    document.getElementById('progress-bar').style.width = pct + '%';
}

// ===== SUBMIT =====
function konfirmasiSubmit() {
    const belum = TOTAL_SOAL - answered;
    let msg = 'Pastikan semua soal sudah dijawab. Setelah dikirim, jawaban tidak dapat diubah.';
    if (belum > 0) {
        msg = '<strong style="color:var(--p-danger)">Masih ada ' + belum + ' soal belum dijawab.</strong><br><br>' + msg;
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

// ===== FULLSCREEN =====
function mintaFullscreen() {
    const el = document.documentElement;
    if (el.requestFullscreen)       el.requestFullscreen();
    else if (el.mozRequestFullScreen)   el.mozRequestFullScreen();
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

// ===== DETEKSI PINDAH TAB =====
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        logAktivitas('pindah_tab');
    } else if (MODE === 'ujian') {
        tabCount++;
        document.getElementById('tab-count').textContent = tabCount;
        document.getElementById('overlay-tab').classList.add('show');
    }
});

// ===== DISABLE KLIK KANAN & F12 (mode ujian) =====
if (MODE === 'ujian') {
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => {
        if (e.key === 'F12' || (e.ctrlKey && ['u','s','a'].includes(e.key.toLowerCase()))) {
            e.preventDefault();
        }
    });
    // Minta fullscreen saat halaman dibuka
    window.addEventListener('load', () => {
        setTimeout(mintaFullscreen, 800);
    });
}

// ===== LOG AJAX =====
function logAktivitas(jenis) {
    fetch('/bbu-tryout/peserta/log.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sesi_id: SESI_ID, jenis: jenis })
    }).catch(() => {});
}
</script>
<?php endif; ?>

</body>
</html>