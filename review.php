<?php
require_once __DIR__ . '/config.php';

// ── OTORISASI ──────────────────────────────────────────────
// Halaman ini bisa diakses peserta (lihat punya sendiri) atau admin (lihat siapa saja)
$is_admin   = isset($_SESSION['admin_id']);
$is_peserta = isset($_SESSION['peserta_id']);

if (!$is_admin && !$is_peserta) {
    redirect('/index.php');
}

$hasil_id = (int) ($_GET['hasil_id'] ?? 0);
if ($hasil_id <= 0) {
    redirect('/index.php');
}

// Ambil data hasil_ujian + validasi kepemilikan
$stmt = $conn->prepare("
    SELECT h.*, p.nama AS peserta_nama, p.nomor_peserta, p.paket_id,
           s.nama_sesi, s.mode, pp.nama AS paket_nama
    FROM hasil_ujian h
    JOIN peserta p ON h.peserta_id = p.id
    JOIN sesi_ujian s ON h.sesi_id = s.id
    JOIN paket_program pp ON s.paket_id = pp.id
    WHERE h.id = ?
");
$stmt->bind_param("i", $hasil_id);
$stmt->execute();
$hasil = $stmt->get_result()->fetch_assoc();

if (!$hasil) {
    redirect('/index.php');
}

// Kalau yang akses peserta, pastikan ini hasil miliknya sendiri
if ($is_peserta && (int)$hasil['peserta_id'] !== (int)$_SESSION['peserta_id']) {
    redirect('/peserta/dashboard.php');
}
// Kalau yang akses peserta, pastikan ujian ini SUDAH SELESAI (bukan sedang berjalan/dijeda)
// Ini mencegah peserta melihat kunci jawaban sebelum ujian benar-benar tuntas
if ($is_peserta && $hasil['status_pengerjaan'] !== 'selesai') {
    redirect('/peserta/dashboard.php');
}
// Peserta hanya boleh membuka pembahasan pada hari ujian dilaksanakan
if ($is_peserta && date('Y-m-d', strtotime($hasil['submitted_at'])) !== date('Y-m-d')) {
    redirect('/peserta/dashboard.php');
}

// ── AMBIL JUMLAH MATERI UNTUK PAKET INI ─────────────────────
// Kalau paket cuma punya 1 materi (misal COC GMDSS) → tampilkan sebagai 1 nilai total
$cek_jml_materi = $conn->prepare("SELECT COUNT(*) AS n FROM paket_kategori WHERE paket_id=?");
$cek_jml_materi->bind_param("i", $hasil['paket_id']);
$cek_jml_materi->execute();
$jumlah_materi_paket = (int) $cek_jml_materi->get_result()->fetch_assoc()['n'];

$tampilkan_per_materi = $jumlah_materi_paket > 1;

// ── AMBIL RINCIAN NILAI PER MATERI (kalau relevan) ──────────
$rincian_materi = [];
if ($tampilkan_per_materi) {
    $st = $conn->prepare("
        SELECT hpm.*, k.nama AS kategori_nama
        FROM hasil_per_materi hpm
        JOIN kategori k ON hpm.kategori_id = k.id
        WHERE hpm.hasil_ujian_id = ?
        ORDER BY k.nama
    ");
    $st->bind_param("i", $hasil_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['persen'] = $row['total_bobot_maksimal'] > 0
            ? round($row['total_bobot_didapat'] / $row['total_bobot_maksimal'] * 100)
            : 0;
        $rincian_materi[] = $row;
    }
}

// ── AMBIL SEMUA JAWABAN PESERTA + DETAIL SOAL ───────────────
$stmt2 = $conn->prepare("
    SELECT j.*, s.pertanyaan, s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d, s.pilihan_e,
           s.jawaban_benar, s.bobot, k.id AS kategori_id, k.nama AS kategori_nama
    FROM jawaban j
    JOIN soal s ON j.soal_id = s.id
    JOIN kategori k ON s.kategori_id = k.id
    WHERE j.peserta_id = ? AND j.sesi_id = ?
    ORDER BY k.nama, j.id
");
$stmt2->bind_param("ii", $hasil['peserta_id'], $hasil['sesi_id']);
$stmt2->execute();
$res_jawaban = $stmt2->get_result();

// Kelompokkan jawaban per kategori (untuk tampilan per-materi)
$jawaban_per_kategori = [];
while ($j = $res_jawaban->fetch_assoc()) {
    $kat_id = $j['kategori_id'];
    if (!isset($jawaban_per_kategori[$kat_id])) {
        $jawaban_per_kategori[$kat_id] = [
            'nama' => $j['kategori_nama'],
            'soal' => [],
        ];
    }
    $jawaban_per_kategori[$kat_id]['soal'][] = $j;
}

// Bikin lookup cepat: kategori_id => data nilai (untuk ditempel di judul materi)
$nilai_by_kategori = [];
foreach ($rincian_materi as $rm) {
    $nilai_by_kategori[$rm['kategori_id']] = $rm;
}

$page_title = 'Review Jawaban — ' . e($hasil['nama_sesi']);
$page_title = 'Review Jawaban — ' . e($hasil['nama_sesi']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/review.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="review-page">

<header class="review-header">
    <div class="review-header-left">
        <div class="review-title">Review Jawaban</div>
        <div class="review-subtitle"><?= e($hasil['nama_sesi']) ?></div>
    </div>
    <div class="review-header-right">
        <div class="review-peserta-nama"><?= e($hasil['peserta_nama']) ?></div>
        <div class="review-peserta-info"><?= e($hasil['nomor_peserta']) ?> · <?= e($hasil['paket_nama']) ?></div>
    </div>
</header>

<main class="review-content">

    <!-- RINGKASAN SKOR — hanya untuk paket 1 materi (COC GMDSS) -->
    <?php if (!$tampilkan_per_materi): ?>
    <div class="review-summary-card <?= $hasil['skor'] >= 70 ? 'skor-tinggi' : 'skor-rendah' ?>">
        <div class="summary-score"><?= (int)$hasil['skor'] ?></div>
        <div class="summary-label">Nilai Akhir</div>
        <div class="summary-detail"><?= (int)$hasil['total_benar'] ?> benar dari <?= (int)$hasil['total_soal'] ?> soal</div>
    </div>
    <?php endif; ?>
    <!-- DAFTAR JAWABAN PER MATERI -->
    <?php foreach ($jawaban_per_kategori as $kat_id => $kat_data): ?>
    <section class="review-materi-section">
        <?php if ($tampilkan_per_materi):
            $nilai_materi_ini = $nilai_by_kategori[$kat_id] ?? null;
        ?>
        <div class="review-materi-header">
            <h3 class="review-materi-title"><?= e($kat_data['nama']) ?></h3>
            <?php if ($nilai_materi_ini): ?>
            <span class="review-materi-nilai <?= $nilai_materi_ini['persen'] >= 70 ? 'nilai-tinggi' : 'nilai-rendah' ?>">
                <?= (int)$nilai_materi_ini['total_bobot_didapat'] ?>/<?= (int)$nilai_materi_ini['total_bobot_maksimal'] ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php foreach ($kat_data['soal'] as $no => $j):
            $benar_terjawab = $j['is_benar'] == 1;
            $tidak_dijawab  = $j['jawaban_dipilih'] === null;
        ?>
        <div class="review-soal-card">
            <div class="review-soal-num">Soal <?= $no + 1 ?></div>
            <div class="review-soal-text"><?= nl2br(e($j['pertanyaan'])) ?></div>

            <div class="review-jwb-list">
                <?php foreach (opsi_soal($j) as $opt => $isi_pilihan):
                    $ini_kunci   = ($opt === $j['jawaban_benar']);
                    $ini_dipilih = ($opt === $j['jawaban_dipilih']);

                    $kelas = 'review-jwb-opt';
                    if ($ini_kunci) {
                        $kelas .= ' opt-kunci';
                    }
                    if ($ini_dipilih && !$benar_terjawab) {
                        $kelas .= ' opt-salah-dipilih';
                    }
                ?>
                <div class="<?= $kelas ?>">
                    <span class="review-jwb-letter"><?= $opt ?></span>
                    <span class="review-jwb-text"><?= e($isi_pilihan) ?></span>
                    <?php if ($ini_kunci): ?>
                    <span class="review-jwb-tag tag-kunci"><i class="fas fa-check"></i> Kunci jawaban</span>
                    <?php elseif ($ini_dipilih): ?>
                    <span class="review-jwb-tag tag-dipilih"><i class="fas fa-times"></i> Jawaban Anda</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if ($tidak_dijawab): ?>
                <div class="review-tidak-dijawab">
                    <i class="fas fa-exclamation-circle"></i> Anda tidak menjawab soal ini
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

</main>

</body>
</html>