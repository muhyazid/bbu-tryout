<?php
require_once '../config.php';
cekPeserta();

$peserta_id   = $_SESSION['peserta_id'];
$peserta_nama = $_SESSION['peserta_nama'];
$peserta_jenis= $_SESSION['peserta_jenis'];
$nama_depan   = explode(' ', $peserta_nama)[0];

$today = date('Y-m-d');
$now   = date('H:i:s');

$stmt = $conn->prepare("
    SELECT * FROM sesi_ujian
    WHERE status = 'aktif'
    AND jenis = ?
    AND tanggal = ?
    AND jam_mulai <= ?
    AND jam_selesai >= ?
    LIMIT 1
");
$stmt->bind_param("ssss", $peserta_jenis, $today, $now, $now);
$stmt->execute();
$sesi = $stmt->get_result()->fetch_assoc();

$sudah_kerjakan = false;
if ($sesi) {
    $cek = $conn->prepare("SELECT id FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
    $cek->bind_param("ii", $peserta_id, $sesi['id']);
    $cek->execute();
    $sudah_kerjakan = $cek->get_result()->num_rows > 0;
}

$stmt2 = $conn->prepare("
    SELECT h.*, s.nama_sesi, s.jenis, s.mode
    FROM hasil_ujian h
    JOIN sesi_ujian s ON h.sesi_id = s.id
    WHERE h.peserta_id = ?
    ORDER BY h.submitted_at DESC
    LIMIT 10
");
$stmt2->bind_param("i", $peserta_id);
$stmt2->execute();
$riwayat = $stmt2->get_result();

$page_title = 'Beranda — ' . $peserta_nama;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="peserta-page">
<?php include __DIR__ . '/partials/header.php'; ?>

<main class="peserta-main">

    <section class="peserta-welcome">
        <h2>Selamat datang, <?= htmlspecialchars($nama_depan) ?></h2>
        <p>Program <?= htmlspecialchars($peserta_jenis) ?> — gunakan halaman ini untuk memulai ujian dan melihat nilai.</p>
    </section>

    <?php if ($sesi && !$sudah_kerjakan): ?>
    <section class="status-card status-card--active" aria-labelledby="ujian-aktif-title">
        <p class="status-label" id="ujian-aktif-label">
            <span class="dot" aria-hidden="true"></span>
            Ujian tersedia sekarang
        </p>
        <h3 id="ujian-aktif-title"><?= htmlspecialchars($sesi['nama_sesi']) ?></h3>
        <div class="status-meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?= (int)$sesi['jumlah_soal'] ?> soal
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= (int)$sesi['durasi_menit'] ?> menit
            </span>
            <span><?= htmlspecialchars($sesi['jenis']) ?> · <?= htmlspecialchars(ucfirst($sesi['mode'])) ?></span>
        </div>
        <div class="alert-box" role="note">
            <strong>Sebelum mulai:</strong> Setelah Anda menekan tombol di bawah, waktu ujian langsung berjalan.
            Jangan menutup browser atau berpindah ke aplikasi lain selama ujian.
        </div>
        <a href="ujian.php?sesi_id=<?= (int)$sesi['id'] ?>" class="btn-primary-lg">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Mulai ujian
        </a>
    </section>

    <?php elseif ($sesi && $sudah_kerjakan): ?>
    <section class="status-card status-card--done">
        <div class="status-icon status-icon--success" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
        </div>
        <h3>Ujian sudah dikerjakan</h3>
        <p class="status-desc">
            <strong><?= htmlspecialchars($sesi['nama_sesi']) ?></strong><br>
            Nilai Anda tercatat. Lihat detail di tabel riwayat di bawah.
        </p>
    </section>

    <?php else: ?>
    <section class="status-card status-card--wait">
        <div class="status-icon status-icon--wait" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <h3>Belum ada ujian aktif</h3>
        <p class="status-desc">
            Tunggu instruksi dari pengawas.<br>
            Saat ujian dibuka, tombol <strong>Mulai ujian</strong> akan muncul di halaman ini secara otomatis.
        </p>
    </section>
    <?php endif; ?>

    <section class="panel" aria-labelledby="riwayat-title">
        <div class="panel-header">
            <h3 id="riwayat-title">Riwayat nilai saya</h3>
            <p>Daftar ujian yang sudah Anda selesaikan</p>
        </div>

        <?php if ($riwayat->num_rows > 0): ?>
        <table class="score-table">
            <thead>
                <tr>
                    <th>Sesi ujian</th>
                    <th>Nilai</th>
                    <th>Benar / total</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = $riwayat->fetch_assoc()):
                $lulus = $r['skor'] >= 70;
                $jenis_lower = strtolower($r['jenis']);
            ?>
                <tr>
                    <td data-label="Sesi ujian">
                        <div class="sesi-name"><?= htmlspecialchars($r['nama_sesi']) ?></div>
                        <span class="tag tag--<?= $jenis_lower === 'gmdss' ? 'gmdss' : 'sou' ?>"><?= htmlspecialchars($r['jenis']) ?></span>
                        <span class="tag tag--<?= htmlspecialchars($r['mode']) ?>"><?= htmlspecialchars(ucfirst($r['mode'])) ?></span>
                    </td>
                    <td data-label="Nilai">
                        <span class="score-value <?= $lulus ? 'score-value--pass' : 'score-value--fail' ?>">
                            <?= (int)$r['skor'] ?>
                        </span>
                        <span class="score-caption <?= $lulus ? 'score-caption--pass' : 'score-caption--fail' ?>">
                            <?= $lulus ? 'Memenuhi standar' : 'Di bawah standar (70)' ?>
                        </span>
                    </td>
                    <td data-label="Benar / total">
                        <?= (int)$r['total_benar'] ?> dari <?= (int)$r['total_soal'] ?> soal
                    </td>
                    <td data-label="Tanggal">
                        <?= date('d M Y', strtotime($r['submitted_at'])) ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
            </svg>
            <p>Belum ada riwayat ujian</p>
            <p class="empty-hint">Nilai akan tampil di sini setelah Anda menyelesaikan ujian.</p>
        </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>
