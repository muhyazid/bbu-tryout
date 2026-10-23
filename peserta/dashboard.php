<?php
require_once '../config.php';
cekPeserta();

$peserta_id     = $_SESSION['peserta_id'];
$peserta_nama   = $_SESSION['peserta_nama'];
$peserta_paket_id = $_SESSION['peserta_paket_id'];
$peserta_paket_nama = $_SESSION['peserta_paket_nama'];
$nama_depan     = explode(' ', $peserta_nama)[0];

$sesi = cari_sesi_aktif_peserta($conn, $peserta_paket_id);

$sudah_kerjakan   = false;
$sedang_dijeda    = false;
$bisa_lanjut      = false;
$hasil_row        = null;

if ($sesi) {
    $cek = $conn->prepare("SELECT * FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
    $cek->bind_param("ii", $peserta_id, $sesi['id']);
    $cek->execute();
    $hasil_row = $cek->get_result()->fetch_assoc();

    if ($hasil_row) {
        if ($hasil_row['status_pengerjaan'] === 'selesai') {
            $sudah_kerjakan = true;
        } elseif ($hasil_row['status_pengerjaan'] === 'dijeda') {
            $sedang_dijeda = true;
        } elseif ($hasil_row['status_pengerjaan'] === 'sedang_dikerjakan') {
            // Cek apakah kelompok peserta ini SEKARANG statusnya dijeda
            // (mencegah kasus: kelompok baru saja dijeda tapi status hasil_ujian belum keupdate)
            $cek_kelompok = $conn->prepare("
                SELECT kg.status FROM kelompok_anggota ka
                JOIN kelompok_angkatan kg ON ka.kelompok_id = kg.id
                WHERE ka.peserta_id = ?
            ");
            $cek_kelompok->bind_param("i", $peserta_id);
            $cek_kelompok->execute();
            $status_kelompok = $cek_kelompok->get_result()->fetch_assoc();

            if ($status_kelompok && $status_kelompok['status'] === 'dijeda') {
                $sedang_dijeda = true;
            } else {
                $bisa_lanjut = true; // sedang mengerjakan, boleh lanjut (misal browser ke-refresh)
            }
        }
    }
}

$stmt2 = $conn->prepare("
    SELECT h.*, s.nama_sesi, s.mode, pp.nama AS paket_nama
    FROM hasil_ujian h
    JOIN sesi_ujian s ON h.sesi_id = s.id
    JOIN paket_program pp ON s.paket_id = pp.id
    WHERE h.peserta_id = ?
    ORDER BY h.submitted_at DESC
    LIMIT 10
");
$stmt2->bind_param("i", $peserta_id);
$stmt2->execute();
$riwayat = $stmt2->get_result();

$page_title = 'Beranda — ' . $peserta_nama;

include __DIR__ . '/partials/layout-start.php';
?>

    <section class="peserta-welcome">
        <h2>Selamat datang, <?= e($nama_depan) ?></h2>
        <p>Program <?= e($peserta_paket_nama) ?> - gunakan halaman ini untuk memulai ujian dan melihat nilai.</p>
    </section>
    
    <?php if ($sesi && $sedang_dijeda): ?>
    <section class="status-card status-card--paused">
        <div class="status-icon status-icon--wait" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <rect x="6" y="4" width="4" height="16"/>
                <rect x="14" y="4" width="4" height="16"/>
            </svg>
        </div>
        <h3>Ujian Anda sedang dijeda</h3>
        <p class="status-desc">
            Pengawas sedang menjeda sesi ini sementara.<br>
            Waktu Anda tersimpan aman — silakan tunggu instruksi untuk melanjutkan.
        </p>
    </section>

    <?php elseif ($sesi && ($bisa_lanjut)): ?>
    <section class="status-card status-card--active">
        <p class="status-label" id="ujian-aktif-label">
            <span class="dot" aria-hidden="true"></span>
            Ujian sedang berlangsung
        </p>
        <h3 id="ujian-aktif-title"><?= e($sesi['nama_sesi']) ?></h3>
        <div class="alert-box" role="note">
            Anda memiliki ujian yang belum selesai. Klik tombol di bawah untuk melanjutkan.
        </div>
        <a href="ujian.php?sesi_id=<?= (int)$sesi['id'] ?>" class="btn-primary-lg">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Lanjutkan ujian
        </a>
    </section>

    <?php elseif ($sesi && !$sudah_kerjakan): ?>
    <section class="status-card status-card--active" aria-labelledby="ujian-aktif-title">
        <p class="status-label" id="ujian-aktif-label">
            <span class="dot" aria-hidden="true"></span>
            Ujian tersedia sekarang
        </p>
        <h3 id="ujian-aktif-title"><?= e($sesi['nama_sesi']) ?></h3>
        <div class="status-meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Materi lengkap sesuai paket
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= (int)$sesi['durasi_menit'] ?> menit
            </span>
            <span><?= e($peserta_paket_nama) ?> · <?= e(ucfirst($sesi['mode'])) ?></span>
        </div>
        <?php if (!empty($sesi['info_jadwal'])): ?>
        <div class="alert-box" role="note" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
            <?= e($sesi['info_jadwal']) ?>
        </div>
        <?php endif; ?>
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
            <strong><?= e($sesi['nama_sesi']) ?></strong><br>
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
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($r = $riwayat->fetch_assoc()):
                $lulus = $r['skor'] >= 70;
            ?>
                <tr>
                    <td data-label="Sesi ujian">
                        <div class="sesi-name"><?= e($r['nama_sesi']) ?></div>
                        <span class="tag tag--paket"><?= e($r['paket_nama']) ?></span>
                        <span class="tag tag--<?= e($r['mode']) ?>"><?= e(ucfirst($r['mode'])) ?></span>
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
                    <td data-label="Aksi">
                        <a href="<?= url('/review.php?hasil_id=' . $r['id']) ?>" target="_blank" class="btn-link">
                            Lihat review
                        </a>
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

<?php include __DIR__ . '/partials/layout-end.php'; ?>