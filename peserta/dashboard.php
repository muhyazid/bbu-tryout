<?php
require_once '../config.php';
cekPeserta();

$peserta_id         = $_SESSION['peserta_id'];
$peserta_nama       = $_SESSION['peserta_nama'];
$peserta_paket_id   = $_SESSION['peserta_paket_id'];
$peserta_paket_nama = $_SESSION['peserta_paket_nama'];
$nama_depan         = explode(' ', $peserta_nama)[0];

/* ---------- STATUS UJIAN AKTIF ---------- */
$sesi = cari_sesi_aktif_peserta($conn, $peserta_paket_id);

$sudah_kerjakan = false;
$sedang_dijeda  = false;
$bisa_lanjut    = false;

if ($sesi) {
    $cek = $conn->prepare("SELECT * FROM hasil_ujian WHERE peserta_id = ? AND sesi_id = ?");
    $cek->bind_param("ii", $peserta_id, $sesi['id']);
    $cek->execute();
    $hasil_row = $cek->get_result()->fetch_assoc();

    if ($hasil_row) {
        if ($hasil_row['status_pengerjaan'] === 'selesai') {
            $sudah_kerjakan = true;
        } elseif ($hasil_row['status_pengerjaan'] === 'dijeda') {
            $sedang_dijeda = true;
        } elseif ($hasil_row['status_pengerjaan'] === 'sedang_dikerjakan') {
            // Cek status kelompok terbaru, agar peserta yang baru dijeda tidak lolos
            $cek_kelompok = $conn->prepare("
                SELECT kg.status
                FROM kelompok_anggota ka
                JOIN kelompok_angkatan kg ON ka.kelompok_id = kg.id
                WHERE ka.peserta_id = ?
            ");
            $cek_kelompok->bind_param("i", $peserta_id);
            $cek_kelompok->execute();
            $status_kelompok = $cek_kelompok->get_result()->fetch_assoc();

            if ($status_kelompok && $status_kelompok['status'] === 'dijeda') {
                $sedang_dijeda = true;
            } else {
                $bisa_lanjut = true; // misalnya browser tertutup saat ujian berjalan
            }
        }
    }
}

/* ---------- RIWAYAT: hanya ujian yang sudah selesai ---------- */
$stmt2 = $conn->prepare("
    SELECT h.*, s.nama_sesi, s.mode, pp.nama AS paket_nama
    FROM hasil_ujian h
    JOIN sesi_ujian s     ON h.sesi_id  = s.id
    JOIN paket_program pp ON s.paket_id = pp.id
    WHERE h.peserta_id = ? AND h.status_pengerjaan = 'selesai'
    ORDER BY h.submitted_at DESC
    LIMIT 10
");
$stmt2->bind_param("i", $peserta_id);
$stmt2->execute();
$riwayat = $stmt2->get_result();

$riwayat_rows = [];
while ($row = $riwayat->fetch_assoc()) {
    $riwayat_rows[] = $row;
}

/* Rincian per materi untuk semua riwayat, satu query */
$rincian = [];
if (!empty($riwayat_rows)) {
    $ids = implode(',', array_map(fn($r) => (int) $r['id'], $riwayat_rows));
    $res_rincian = $conn->query("
        SELECT hpm.hasil_ujian_id, k.nama, hpm.total_bobot_didapat, hpm.total_bobot_maksimal
        FROM hasil_per_materi hpm
        JOIN kategori k ON k.id = hpm.kategori_id
        WHERE hpm.hasil_ujian_id IN ($ids)
        ORDER BY k.nama
    ");
    while ($x = $res_rincian->fetch_assoc()) {
        $rincian[(int) $x['hasil_ujian_id']][] = $x;
    }
}

$hari_ini   = date('Y-m-d');
$page_title = 'Beranda - ' . $peserta_nama;

include __DIR__ . '/partials/layout-start.php';
?>

    <section class="peserta-welcome">
        <h2>Selamat datang, <?= e($nama_depan) ?></h2>
        <p>Program <?= e($peserta_paket_nama) ?>. Gunakan halaman ini untuk memulai ujian dan melihat hasil.</p>
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
            Waktu Anda tersimpan. Tunggu instruksi dari pengawas untuk melanjutkan.
        </p>
    </section>

    <?php elseif ($sesi && $bisa_lanjut): ?>
    <section class="status-card status-card--active">
        <p class="status-label">
            <span class="dot" aria-hidden="true"></span>
            Ujian sedang berlangsung
        </p>
        <h3><?= e($sesi['nama_sesi']) ?></h3>
        <div class="alert-box" role="note">
            Anda memiliki ujian yang belum selesai. Klik tombol di bawah untuk melanjutkan.
        </div>
        <a href="ujian.php?sesi_id=<?= (int) $sesi['id'] ?>" class="btn-primary-lg">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Lanjutkan ujian
        </a>
    </section>

    <?php elseif ($sesi && !$sudah_kerjakan): ?>
    <section class="status-card status-card--active">
        <p class="status-label">
            <span class="dot" aria-hidden="true"></span>
            Ujian tersedia sekarang
        </p>
        <h3><?= e($sesi['nama_sesi']) ?></h3>
        <div class="status-meta">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Semua materi sesuai paket
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= (int) $sesi['durasi_menit'] ?> menit
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
        <a href="ujian.php?sesi_id=<?= (int) $sesi['id'] ?>" class="btn-primary-lg">
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
            Nilai Anda sudah tercatat. Lihat rinciannya di riwayat nilai di bawah.
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
            Saat ujian dibuka, tombol <strong>Mulai ujian</strong> akan muncul di halaman ini.
        </p>
    </section>
    <?php endif; ?>

    
    
    <section class="rw" aria-labelledby="rw-title">

        <header class="rw-head">
            <div>
                <h3 id="rw-title">Riwayat Hasil Ujian</h3>
                <p class="rw-subtitle">Pilih ujian untuk melihat rincian nilai setiap materi.</p>
            </div>

            <?php if (!empty($riwayat_rows)): ?>
                <span><?= count($riwayat_rows) ?> ujian</span>
            <?php endif; ?>
        </header>

        <?php if (!empty($riwayat_rows)): ?>

            <ul class="rw-list">
            <?php foreach ($riwayat_rows as $r):

                $bisa_review = date('Y-m-d', strtotime($r['submitted_at'])) === $hari_ini;

                $items = [];
                foreach ($rincian[(int) $r['id']] ?? [] as $x) {
                    $x['persen'] = (int) (
                        $x['total_bobot_maksimal'] > 0
                        ? round(
                            $x['total_bobot_didapat']
                            / $x['total_bobot_maksimal'] * 100
                        )
                        : 0
                    );

                    $items[] = $x;
                }

            ?>
                <li class="rw-item">
                    <details class="rw-detail">

                        <summary class="rw-summary">
                            <span class="rw-summary-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.7"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="5" y="3" width="14" height="18" rx="2"/>
                                    <path d="M9 8h6M9 12h6M9 16h3"/>
                                </svg>
                            </span>

                            <span class="rw-summary-copy">
                                <strong class="rw-judul">
                                    <?= e($r['nama_sesi']) ?>
                                </strong>

                                <span class="rw-info">
                                    <?= date('d M Y', strtotime($r['submitted_at'])) ?>
                                    <span aria-hidden="true">·</span>
                                    <?= e($r['paket_nama']) ?>
                                </span>
                            </span>

                            <span class="rw-summary-action">
                                <span class="rw-buka">Lihat rincian</span>
                                <span class="rw-tutup">Tutup rincian</span>
                            </span>
                        </summary>

                        <div class="rw-detail-content">

                            <h4>Rincian Nilai per Materi</h4>

                            <?php if (!empty($items)): ?>

                                <div class="rw-materi-table-wrap">
                                    <table class="rw-materi-table">
                                        <thead>
                                            <tr>
                                                <th>Materi</th>
                                                <th class="rw-center">Nilai</th>
                                                <th class="rw-center">KKM</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                        <?php foreach ($items as $x):
                                            $kkm = 70;
                                            $memenuhi = $x['persen'] >= $kkm;
                                        ?>
                                            <tr>
                                                <td><?= e($x['nama']) ?></td>

                                                <td class="rw-center">
                                                    <?= $x['persen'] ?>
                                                </td>

                                                <td class="rw-center">
                                                    <?= $kkm ?>
                                                </td>

                                                <td>
                                                    <span class="<?= $memenuhi
                                                        ? 'rw-result-pass'
                                                        : 'rw-result-fail' ?>">
                                                        <?= $memenuhi
                                                            ? 'Memenuhi KKM'
                                                            : 'Belum memenuhi' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <?php else: ?>

                                <p class="rw-no-detail">
                                    Rincian nilai per materi belum tersedia untuk ujian ini.
                                </p>

                            <?php endif; ?>

                            <div class="rw-review-area">
                                <?php if ($bisa_review): ?>

                                    <a
                                        class="rw-review-link"
                                        href="<?= url('/review.php?hasil_id=' . (int) $r['id']) ?>"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Lihat pembahasan jawaban
                                    </a>

                                <?php else: ?>

                                    <span class="rw-review-disabled">
                                        Pembahasan hanya tersedia pada hari ujian.
                                    </span>

                                <?php endif; ?>
                            </div>

                        </div>
                    </details>
                </li>

            <?php endforeach; ?>
            </ul>

            <p class="rw-foot">
                Riwayat setiap percobaan ujian tetap tersimpan.
                Pembahasan jawaban hanya dapat dibuka pada hari ujian dilaksanakan.
            </p>

        <?php else: ?>

            <div class="rw-empty">
                <p>Belum ada riwayat ujian.</p>
                <p>Hasil akan tampil di sini setelah Anda menyelesaikan ujian.</p>
            </div>

        <?php endif; ?>

    </section>



<?php include __DIR__ . '/partials/layout-end.php'; ?>