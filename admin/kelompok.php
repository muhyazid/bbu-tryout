<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

$msg = '';
$error = '';

if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// ——— Tambah kelompok baru ———
if (isset($_POST['tambah_kelompok'])) {
    $angkatan = trim($_POST['angkatan'] ?? '');
    $nama     = trim($_POST['nama_kelompok'] ?? '');
    if ($angkatan !== '' && $nama !== '') {
        $s = $conn->prepare("INSERT INTO kelompok_angkatan (angkatan, nama_kelompok) VALUES (?, ?)");
        $s->bind_param("ss", $angkatan, $nama);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = "Kelompok \"$nama\" berhasil dibuat.";
        } else {
            $_SESSION['flash_msg'] = 'Gagal membuat kelompok.';
        }
        redirect('/admin/kelompok.php?angkatan=' . urlencode($angkatan));
    }
}

// ——— Hapus kelompok ———
if (isset($_GET['hapus'])) {
    $kid = (int) $_GET['hapus'];
    $angkatan_terkait = $conn->query("SELECT angkatan FROM kelompok_angkatan WHERE id=$kid")->fetch_assoc();
    $del = $conn->prepare("DELETE FROM kelompok_angkatan WHERE id=?");
    $del->bind_param("i", $kid);
    $del->execute();
    $_SESSION['flash_msg'] = 'Kelompok dihapus.';
    redirect('/admin/kelompok.php' . ($angkatan_terkait ? '?angkatan=' . urlencode($angkatan_terkait['angkatan']) : ''));
}

// ——— Simpan assign anggota kelompok ———
if (isset($_POST['simpan_anggota'])) {
    $kelompok_id = (int) $_POST['kelompok_id'];
    $peserta_dipilih = $_POST['peserta'] ?? [];

    // Hapus dulu semua anggota lama dari kelompok ini
    $del = $conn->prepare("DELETE FROM kelompok_anggota WHERE kelompok_id=?");
    $del->bind_param("i", $kelompok_id);
    $del->execute();

    // Masukkan ulang sesuai pilihan terbaru
    if (!empty($peserta_dipilih)) {
        $ins = $conn->prepare("INSERT INTO kelompok_anggota (kelompok_id, peserta_id) VALUES (?, ?)");
        foreach ($peserta_dipilih as $pid) {
            $pid = (int) $pid;
            $ins->bind_param("ii", $kelompok_id, $pid);
            $ins->execute(); // abaikan error duplikat (peserta sudah di kelompok lain akan gagal karena UNIQUE)
        }
    }

    $angkatan_terkait = $conn->query("SELECT angkatan FROM kelompok_angkatan WHERE id=$kelompok_id")->fetch_assoc();
    $_SESSION['flash_msg'] = 'Anggota kelompok berhasil diperbarui.';
    redirect('/admin/kelompok.php' . ($angkatan_terkait ? '?angkatan=' . urlencode($angkatan_terkait['angkatan']) : ''));
}

// ——— Toggle status kelompok (jeda/lanjutkan) ———
if (isset($_GET['toggle'])) {
    $kid = (int) $_GET['toggle'];
    $now = $conn->query("SELECT status, angkatan FROM kelompok_angkatan WHERE id=$kid")->fetch_assoc();

    if ($now) {
        $baru = $now['status'] === 'berjalan' ? 'dijeda' : 'berjalan';

        $upd = $conn->prepare("UPDATE kelompok_angkatan SET status=? WHERE id=?");
        $upd->bind_param("si", $baru, $kid);
        $upd->execute();

        // Ambil semua peserta di kelompok ini
        $anggota = $conn->prepare("SELECT peserta_id FROM kelompok_anggota WHERE kelompok_id=?");
        $anggota->bind_param("i", $kid);
        $anggota->execute();
        $res_anggota = $anggota->get_result();
        $daftar_peserta_id = [];
        while ($row = $res_anggota->fetch_assoc()) {
            $daftar_peserta_id[] = (int) $row['peserta_id'];
        }

        if (!empty($daftar_peserta_id)) {
            $id_str = implode(',', $daftar_peserta_id);

            if ($baru === 'dijeda') {
                // JEDA: tandai hasil_ujian yang sedang dikerjakan, catat waktu pause mulai
                $conn->query("
                    UPDATE hasil_ujian
                    SET status_pengerjaan = 'dijeda', waktu_pause_mulai = NOW()
                    WHERE peserta_id IN ($id_str)
                      AND status_pengerjaan = 'sedang_dikerjakan'
                ");

                // Force logout semua peserta di kelompok ini
                // (session PHP tidak bisa dihapus dari sini secara langsung,
                //  jadi kita tandai lewat tabel — dicek saat peserta akses halaman manapun)
            } else {
                // LANJUTKAN: hitung durasi pause, tambahkan ke total_durasi_pause
                $conn->query("
                    UPDATE hasil_ujian
                    SET total_durasi_pause = total_durasi_pause + TIMESTAMPDIFF(SECOND, waktu_pause_mulai, NOW()),
                        status_pengerjaan = 'sedang_dikerjakan',
                        waktu_pause_mulai = NULL
                    WHERE peserta_id IN ($id_str)
                      AND status_pengerjaan = 'dijeda'
                ");
            }
        }

        $_SESSION['flash_msg'] = $baru === 'dijeda'
            ? 'Kelompok berhasil dijeda. Peserta akan otomatis logout.'
            : 'Kelompok dilanjutkan. Peserta bisa login kembali.';
        redirect('/admin/kelompok.php?angkatan=' . urlencode($now['angkatan']));
    }
}

// Daftar semua angkatan yang ada (dari data peserta)
$daftar_angkatan = [];
$res_ang = $conn->query("SELECT DISTINCT angkatan FROM peserta WHERE angkatan IS NOT NULL AND angkatan != '' ORDER BY angkatan DESC");
while ($row = $res_ang->fetch_assoc()) {
    $daftar_angkatan[] = $row['angkatan'];
}

$angkatan_aktif = $_GET['angkatan'] ?? ($daftar_angkatan[0] ?? '');

// Kelompok-kelompok dalam angkatan aktif
$daftar_kelompok = [];
if ($angkatan_aktif) {
    $st = $conn->prepare("SELECT * FROM kelompok_angkatan WHERE angkatan=? ORDER BY id");
    $st->bind_param("s", $angkatan_aktif);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        // Hitung jumlah anggota tiap kelompok
        $cnt = $conn->query("SELECT COUNT(*) as n FROM kelompok_anggota WHERE kelompok_id=" . (int)$row['id'])->fetch_assoc();
        $row['jumlah_anggota'] = (int) $cnt['n'];
        $daftar_kelompok[] = $row;
    }
}

// Semua peserta di angkatan aktif (untuk form assign)
$semua_peserta_angkatan = [];
if ($angkatan_aktif) {
    $st = $conn->prepare("SELECT id, nama, nomor_peserta FROM peserta WHERE angkatan=? ORDER BY nama");
    $st->bind_param("s", $angkatan_aktif);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $semua_peserta_angkatan[] = $row;
    }
}

// Kelompok yang sedang dibuka untuk edit anggota
$edit_kelompok_id = (int) ($_GET['edit_anggota'] ?? 0);
$anggota_terpilih = [];
if ($edit_kelompok_id) {
    $st = $conn->prepare("SELECT peserta_id FROM kelompok_anggota WHERE kelompok_id=?");
    $st->bind_param("i", $edit_kelompok_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $anggota_terpilih[] = (int) $row['peserta_id'];
    }
}

// Peserta yang SUDAH masuk kelompok manapun di angkatan ini (untuk info "sudah di kelompok lain")
$sudah_masuk_kelompok = [];
if ($angkatan_aktif) {
    $res = $conn->query("
        SELECT ka.peserta_id, kg.nama_kelompok
        FROM kelompok_anggota ka
        JOIN kelompok_angkatan kg ON ka.kelompok_id = kg.id
        WHERE kg.angkatan = '" . $conn->real_escape_string($angkatan_aktif) . "'
    ");
    while ($row = $res->fetch_assoc()) {
        $sudah_masuk_kelompok[(int)$row['peserta_id']] = $row['nama_kelompok'];
    }
}

$admin_page = 'kelompok';
$page_title = 'Kelola Kelompok — BBU Tryout';
$topbar_breadcrumb = 'Kelola Kelompok';
$topbar_title      = 'Kelola Kelompok Ujian';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div style="margin-bottom:16px;font-size:13px;color:var(--text-secondary);line-height:1.6;">
      Buat kelompok untuk membagi peserta yang ujian bergantian (misal karena keterbatasan
      tempat/laptop). Jeda kelompok akan menghentikan sementara waktu ujian mereka dan
      otomatis logout — waktu tersisa akan tetap sama saat dilanjutkan.
    </div>

    <!-- PILIH ANGKATAN -->
    <?php if (count($daftar_angkatan) > 0): ?>
    <div class="filter-group" style="margin-bottom:20px;">
      <?php foreach ($daftar_angkatan as $ang): ?>
      <a href="kelompok.php?angkatan=<?= urlencode($ang) ?>"
         class="filter-btn <?= $angkatan_aktif === $ang ? 'filter-btn-active' : '' ?>">
        <?= e($ang) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <p><strong>Belum ada data angkatan</strong></p>
      <p>Tambahkan peserta dengan kolom Angkatan terisi terlebih dahulu.</p>
    </div>
    <?php endif; ?>

    <?php if ($angkatan_aktif): ?>

    <!-- FORM TAMBAH KELOMPOK -->
    <section class="add-materi-box">
      <h4>Buat kelompok baru untuk <?= e($angkatan_aktif) ?></h4>
      <form method="POST" class="add-materi-form">
        <input type="hidden" name="angkatan" value="<?= e($angkatan_aktif) ?>">
        <div>
          <label class="form-label">Nama kelompok</label>
          <input type="text" name="nama_kelompok" class="form-input"
                placeholder="Contoh: Kelompok A" required>
        </div>
        <div class="add-materi-action">
          <label class="form-label">&nbsp;</label>
          <button type="submit" name="tambah_kelompok" class="btn-admin-primary">
            <i class="fas fa-plus" style="font-size:12px;"></i> Buat kelompok
          </button>
        </div>
      </form>
    </section>

    <!-- DAFTAR KELOMPOK -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h3>Kelompok di <?= e($angkatan_aktif) ?></h3>
      </div>

      <?php if (count($daftar_kelompok) > 0): ?>
      <div class="materi-list">
        <?php foreach ($daftar_kelompok as $kel): ?>
        <div class="materi-row">
          <span class="badge <?= $kel['status'] === 'berjalan' ? 'badge-aktif' : 'badge-nonaktif' ?>" style="width:80px;text-align:center;justify-content:center;">
            <?= $kel['status'] === 'berjalan' ? '● Berjalan' : '○ Dijeda' ?>
          </span>
          <span class="materi-row-name" style="cursor:default;"><?= e($kel['nama_kelompok']) ?></span>
          <span class="materi-row-count"><?= $kel['jumlah_anggota'] ?> peserta</span>
          <div class="materi-row-actions">
            <a href="kelompok.php?angkatan=<?= urlencode($angkatan_aktif) ?>&edit_anggota=<?= (int)$kel['id'] ?>"
               class="btn-icon" data-tooltip="Kelola anggota">
              <i class="fas fa-users"></i>
            </a>
            <a href="#"
               class="btn-icon"
               data-tooltip="<?= $kel['status'] === 'berjalan' ? 'Jeda kelompok' : 'Lanjutkan kelompok' ?>"
               onclick="konfirmasiToggleKelompok(<?= (int)$kel['id'] ?>, '<?= $kel['status'] === 'berjalan' ? 'dijeda' : 'berjalan' ?>', '<?= htmlspecialchars(addslashes($kel['nama_kelompok'])) ?>', <?= $kel['jumlah_anggota'] ?>); return false;">
              <i class="fas <?= $kel['status'] === 'berjalan' ? 'fa-pause' : 'fa-play' ?>"></i>
            </a>
            <a href="#"
               class="btn-icon btn-icon-delete" data-tooltip="Hapus kelompok"
               onclick="if(confirm('Hapus kelompok ini?')) window.location='kelompok.php?hapus=<?= (int)$kel['id'] ?>'; return false;">
              <i class="fas fa-trash"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada kelompok</strong></p>
        <p>Buat kelompok di atas jika ujian perlu dibagi bergantian. Jika tidak, biarkan kosong — semua peserta bisa ujian bersamaan tanpa perlu kelompok.</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- FORM ASSIGN ANGGOTA (muncul kalau ada kelompok yang sedang di-edit) -->
    <?php
    $kelompok_diedit = null;
    foreach ($daftar_kelompok as $kel) {
        if ((int)$kel['id'] === $edit_kelompok_id) { $kelompok_diedit = $kel; break; }
    }
    ?>
    <?php if ($kelompok_diedit): ?>
    <div class="card">
      <div class="card-header">
        <h3>Anggota — <?= e($kelompok_diedit['nama_kelompok']) ?></h3>
        <a href="kelompok.php?angkatan=<?= urlencode($angkatan_aktif) ?>" class="link-muted">Tutup</a>
      </div>

      <?php if (count($semua_peserta_angkatan) > 0): ?>
      <form method="POST">
        <input type="hidden" name="kelompok_id" value="<?= $edit_kelompok_id ?>">
        <div class="paket-checklist">
          <?php foreach ($semua_peserta_angkatan as $p):
              $sudah_di_lain = isset($sudah_masuk_kelompok[$p['id']]) && !in_array((int)$p['id'], $anggota_terpilih);
          ?>
          <label class="paket-check-row" style="<?= $sudah_di_lain ? 'opacity:0.5;' : '' ?>">
            <input type="checkbox" name="peserta[]" value="<?= (int)$p['id'] ?>"
                   <?= in_array((int)$p['id'], $anggota_terpilih) ? 'checked' : '' ?>
                   <?= $sudah_di_lain ? 'disabled' : '' ?>>
            <span class="paket-check-name">
              <?= e($p['nama']) ?> <code style="font-size:11px;color:var(--text-muted);">(<?= e($p['nomor_peserta']) ?>)</code>
              <?php if ($sudah_di_lain): ?>
              <span style="color:var(--text-muted);font-size:11px;"> — sudah di <?= e($sudah_masuk_kelompok[$p['id']]) ?></span>
              <?php endif; ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="card-body" style="border-top:1px solid var(--border);padding-top:16px;">
          <button type="submit" name="simpan_anggota" class="btn-primary">Simpan anggota</button>
        </div>
      </form>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada peserta di angkatan ini</strong></p>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- MODAL KONFIRMASI TOGGLE KELOMPOK -->
    <div class="modal-overlay" id="modal-toggle-kelompok">
      <div class="modal-box">
        <div class="modal-icon modal-icon--info" id="tk-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 8v4l3 3"/>
          </svg>
        </div>
        <h2 class="modal-title" id="tk-title">Ubah status kelompok?</h2>
        <p class="modal-text" id="tk-text">Kelompok akan diubah statusnya.</p>
        <div class="modal-actions">
          <a href="#" id="tk-confirm-btn" class="modal-btn modal-btn--primary">Ya, lanjutkan</a>
          <button type="button" class="modal-btn modal-btn--secondary" onclick="tutupToggleKelompok()">Batal</button>
        </div>
      </div>
    </div>
    <script>
    function konfirmasiToggleKelompok(id, statusTujuan, namaKelompok, jumlahAnggota) {
        const judul = document.getElementById('tk-title');
        const teks  = document.getElementById('tk-text');
        const btn   = document.getElementById('tk-confirm-btn');

        if (statusTujuan === 'dijeda') {
            judul.textContent = 'Jeda kelompok ini?';
            teks.innerHTML = '<strong>' + namaKelompok + '</strong> (' + jumlahAnggota + ' peserta) akan dijeda. '
                + 'Peserta yang sedang mengerjakan ujian akan otomatis logout, dan waktu tersisa mereka akan dibekukan.';
        } else {
            judul.textContent = 'Lanjutkan kelompok ini?';
            teks.innerHTML = '<strong>' + namaKelompok + '</strong> (' + jumlahAnggota + ' peserta) akan dilanjutkan. '
                + 'Peserta bisa login kembali dan waktu ujian akan lanjut dari sisa sebelumnya.';
        }

        btn.href = 'kelompok.php?toggle=' + id;
        document.getElementById('modal-toggle-kelompok').classList.add('show');
    }

    function tutupToggleKelompok() {
        document.getElementById('modal-toggle-kelompok').classList.remove('show');
    }
    </script>

<?php include __DIR__ . '/partials/layout-end.php'; ?>