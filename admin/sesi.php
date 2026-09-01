<?php
require_once '../config.php';
cekAdmin();

$msg = '';
$error = '';

// Ambil pesan flash dari redirect sebelumnya (kalau ada)
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Toggle aktif/nonaktif
if (isset($_GET['toggle'])) {
    $id  = (int)$_GET['toggle'];
    $cur = $conn->query("SELECT status FROM sesi_ujian WHERE id=$id")->fetch_assoc()['status'];
    $new = $cur === 'aktif' ? 'nonaktif' : 'aktif';
    $upd = $conn->prepare("UPDATE sesi_ujian SET status=? WHERE id=?");
    $upd->bind_param("si", $new, $id);
    $upd->execute();
    $_SESSION['flash_msg'] = $new === 'aktif' ? 'Sesi berhasil diaktifkan.' : 'Sesi dinonaktifkan.';
    redirect('/admin/sesi.php');
}

// Hapus (dengan opsi force untuk hapus sesi + semua nilai terkait)
if (isset($_GET['hapus'])) {
    $del_id = (int)$_GET['hapus'];
    $force  = isset($_GET['force']);

    // Cek dulu apakah sesi ini punya hasil ujian terkait
    $cek = $conn->prepare("SELECT COUNT(*) as n FROM hasil_ujian WHERE sesi_id=?");
    $cek->bind_param("i", $del_id);
    $cek->execute();
    $jumlah_hasil = $cek->get_result()->fetch_assoc()['n'];

    if ($jumlah_hasil > 0 && !$force) {
        // Ada data terkait, tapi belum konfirmasi force — jangan hapus, minta konfirmasi
        $error = 'PUNYA_HASIL:' . $del_id . ':' . $jumlah_hasil;
    } else {
        // Aman dihapus langsung, ATAU sudah dikonfirmasi force
        if ($force && $jumlah_hasil > 0) {
            // Hapus berurutan: jawaban dulu, baru hasil_ujian, baru sesi
            $del_jwb = $conn->prepare("DELETE FROM jawaban WHERE sesi_id=?");
            $del_jwb->bind_param("i", $del_id);
            $del_jwb->execute();

            $del_hasil = $conn->prepare("DELETE FROM hasil_ujian WHERE sesi_id=?");
            $del_hasil->bind_param("i", $del_id);
            $del_hasil->execute();
        }

        $del = $conn->prepare("DELETE FROM sesi_ujian WHERE id=?");
        $del->bind_param("i", $del_id);

        if ($del->execute()) {
            $_SESSION['flash_msg'] = $force
                ? "Sesi ujian dan {$jumlah_hasil} nilai terkait berhasil dihapus permanen."
                : 'Sesi ujian dihapus.';
            redirect('/admin/sesi.php');
        } else {
            $error = 'Gagal menghapus sesi. Silakan coba lagi.';
        }
    }
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM sesi_ujian WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

// Simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama_sesi']);
    $paket_id = (int)$_POST['paket_id'];
    $tgl      = $_POST['tanggal'];
    $jam1  = $_POST['jam_mulai'];
    $jam2  = $_POST['jam_selesai'];
    $dur   = (int)$_POST['durasi_menit'];
    $jml   = (int)$_POST['jumlah_soal'];
    $mode  = $_POST['mode'];

    if ($id > 0) {
        $s = $conn->prepare("UPDATE sesi_ujian SET nama_sesi=?,paket_id=?,tanggal=?,jam_mulai=?,jam_selesai=?,durasi_menit=?,jumlah_soal=?,mode=? WHERE id=?");
        $s->bind_param("sisssiisi", $nama,$paket_id,$tgl,$jam1,$jam2,$dur,$jml,$mode,$id);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = 'Sesi berhasil diperbarui.';
            redirect('/admin/sesi.php');
        } else {
            $error = 'Gagal memperbarui sesi.';
        }
    } else {
        $s = $conn->prepare("INSERT INTO sesi_ujian (nama_sesi,paket_id,tanggal,jam_mulai,jam_selesai,durasi_menit,jumlah_soal,mode) VALUES (?,?,?,?,?,?,?,?)");
        $s->bind_param("sisssiis", $nama,$paket_id,$tgl,$jam1,$jam2,$dur,$jml,$mode);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = 'Sesi ujian berhasil dibuat.';
            redirect('/admin/sesi.php');
        } else {
            $error = 'Gagal membuat sesi ujian.';
        }
    }
    $edit = null;
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);
$list = $conn->query("
    SELECT s.*, pp.nama AS paket_nama
    FROM sesi_ujian s
    JOIN paket_program pp ON s.paket_id = pp.id
    ORDER BY s.tanggal DESC, s.jam_mulai DESC
");

$admin_page = 'sesi';
$page_title = 'Sesi Ujian';
$topbar_breadcrumb = 'Sesi Ujian';
$topbar_title      = 'Kelola Sesi Ujian';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php
    // Deteksi apakah $error adalah sinyal khusus "punya hasil ujian"
    $modal_hapus_id = null;
    $modal_hapus_jumlah = 0;
    $modal_hapus_nama = '';
    if ($error && strpos($error, 'PUNYA_HASIL:') === 0) {
        [, $modal_hapus_id, $modal_hapus_jumlah] = explode(':', $error);
        $cek_nama = $conn->prepare("SELECT nama_sesi FROM sesi_ujian WHERE id=?");
        $cek_nama->bind_param("i", $modal_hapus_id);
        $cek_nama->execute();
        $modal_hapus_nama = $cek_nama->get_result()->fetch_assoc()['nama_sesi'] ?? '';
        $error = ''; // jangan tampilkan sebagai alert biasa
    }
    ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FORM -->
    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h3><?= $edit ? 'Edit sesi' : 'Buat sesi ujian baru' ?></h3>
        <a href="sesi.php" class="link-muted">Tutup</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

            <div style="grid-column:1/-1;">
              <label class="form-label">Nama Sesi *</label>
              <input type="text" name="nama_sesi" class="form-input"
                     placeholder="Contoh: CAT Hari 1 — SOU Angkatan 2024-I"
                     value="<?= htmlspecialchars($edit['nama_sesi'] ?? '') ?>" required>
            </div>

            <div>
              <label class="form-label">Program *</label>
              <select name="paket_id" class="form-input">
                <?php
                $opt_paket = $conn->query("SELECT id, nama FROM paket_program ORDER BY urutan");
                while ($op = $opt_paket->fetch_assoc()):
                ?>
                <option value="<?= (int)$op['id'] ?>" <?= (int)($edit['paket_id'] ?? 0) === (int)$op['id'] ? 'selected' : '' ?>>
                  <?= e($op['nama']) ?>
                </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div>
              <label class="form-label">Mode *</label>
              <select name="mode" class="form-input">
                <option value="latihan" <?= ($edit['mode'] ?? '') === 'latihan' ? 'selected' : '' ?>>Latihan (boleh browsing)</option>
                <option value="ujian"   <?= ($edit['mode'] ?? '') === 'ujian'   ? 'selected' : '' ?>>Ujian (ketat)</option>
              </select>
            </div>

            <div>
              <label class="form-label">Tanggal *</label>
              <input type="date" name="tanggal" class="form-input"
                     value="<?= $edit['tanggal'] ?? date('Y-m-d') ?>" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div>
                <label class="form-label">Jam Mulai *</label>
                <input type="time" name="jam_mulai" class="form-input"
                       value="<?= isset($edit['jam_mulai']) ? substr($edit['jam_mulai'], 0, 5) : '00:00' ?>" required>
              </div>
              <div>
                <label class="form-label">Jam Selesai *</label>
                <input type="time" name="jam_selesai" class="form-input"
                       value="<?= isset($edit['jam_selesai']) ? substr($edit['jam_selesai'], 0, 5) : '23:59' ?>" required>
              </div>
            </div>

            <div>
              <label class="form-label">Durasi Ujian (menit) *</label>
              <input type="number" name="durasi_menit" class="form-input"
                     min="5" max="300" placeholder="60"
                     value="<?= $edit['durasi_menit'] ?? 60 ?>" required>
              <small style="color:var(--text-muted);font-size:12px;">Timer akan otomatis submit saat habis</small>
            </div>

            <div>
              <label class="form-label">Jumlah Soal *</label>
              <input type="number" name="jumlah_soal" class="form-input"
                     min="1" max="200" placeholder="50"
                     value="<?= $edit['jumlah_soal'] ?? 50 ?>" required>
              <small style="color:var(--text-muted);font-size:12px;">Diambil acak dari bank soal</small>
            </div>

            <div style="grid-column:1/-1;padding:12px 14px;background:#eff6ff;border-radius:8px;font-size:13px;color:#1e40af;line-height:1.5;">
              <strong>Agar peserta bisa melihat ujian:</strong> status <em>Aktif</em>, tanggal = hari ini,
              program sama dengan peserta (SOU/GMDSS). Default jam 00:00–23:59 = seharian.
            </div>

          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn-primary">Simpan sesi</button>
            <a href="sesi.php" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABEL SESI -->
    <div class="card">
      <div class="card-header">
      <h3>Daftar sesi ujian</h3>
      <a href="sesi.php?action=tambah" class="btn-admin-primary btn-sm">
        <?= admin_icon('plus') ?> Buat sesi baru
      </a>
    </div>
      <?php if ($list->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Nama Sesi</th>
            <th>Program</th>
            <th>Jadwal</th>
            <th>Durasi / Soal</th>
            <th>Mode</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $hari_ini = date('Y-m-d');
        while ($s = $list->fetch_assoc()):
            $tampil_peserta = ($s['status'] === 'aktif' && $s['tanggal'] === $hari_ini);
        ?>
          <tr>
            <td style="font-weight:500;">
              <?= htmlspecialchars($s['nama_sesi']) ?>
              <?php if ($tampil_peserta): ?>
              <span class="badge badge-aktif" style="margin-left:6px;font-size:10px;">Tampil ke peserta</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-paket"><?= e($s['paket_nama']) ?></span></td>
            <td style="font-size:13px;">
              <?= date('d M Y', strtotime($s['tanggal'])) ?><br>
              <span style="color:var(--text-muted);">
                <?= substr($s['jam_mulai'],0,5) ?> – <?= substr($s['jam_selesai'],0,5) ?>
              </span>
            </td>
            <td style="font-size:13px;">
              <?= (int)$s['durasi_menit'] ?> menit<br>
              <span style="color:var(--text-muted);"><?= (int)$s['jumlah_soal'] ?> soal</span>
            </td>
            <td>
              <span class="badge badge-<?= $s['mode'] ?>">
                <?= $s['mode'] === 'ujian' ? 'Ujian' : 'Latihan' ?>
              </span>
            </td>
            <td>
              <a href="#"
                class="<?= $s['status'] === 'aktif' ? 'toggle-on' : 'toggle-off' ?>"
                onclick="konfirmasiToggle(<?= (int)$s['id'] ?>, '<?= $s['status'] === 'aktif' ? 'nonaktif' : 'aktif' ?>', '<?= htmlspecialchars(addslashes($s['nama_sesi'])) ?>'); return false;">
                <?= $s['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
              </a>
            </td>

            <td>
              <div class="action-buttons">
                <a href="sesi.php?edit=<?= $s['id'] ?>" 
                  class="btn-icon btn-icon-edit" 
                  data-tooltip="Edit sesi">
                  <i class="fas fa-pen"></i>
                </a>
                <a href="sesi.php?hapus=<?= $s['id'] ?>" 
                  class="btn-icon btn-icon-delete" 
                  data-tooltip="Hapus sesi"
                  onclick="return confirm('Hapus sesi <?= htmlspecialchars(addslashes($s['nama_sesi'])) ?>?')">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state">
          <p><strong>Belum ada sesi ujian</strong></p>
          <p>Gunakan tombol <strong>Buat sesi baru</strong> di atas untuk membuat jadwal ujian.</p>
        </div>
      <?php endif; ?>
    </div>
    

    <!-- Modal konfirmasi toggle status -->
    <div class="modal-overlay" id="modal-toggle-status">
      <div class="modal-box">
        <div class="modal-icon modal-icon--info" id="toggle-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 8v4l3 3"/>
          </svg>
        </div>
        <h2 class="modal-title" id="toggle-title">Ubah status sesi?</h2>
        <p class="modal-text" id="toggle-text">
          Sesi akan diubah statusnya.
        </p>
        <div class="modal-actions">
          <a href="#" id="btn-toggle-confirm" class="modal-btn modal-btn--primary">
            Ya, lanjutkan
          </a>
          <button type="button" class="modal-btn modal-btn--secondary" onclick="tutupToggleModal()">
            Batal
          </button>
        </div>
      </div>
    </div>
    <script>
    function konfirmasiToggle(id, statusTujuan, namaSesi) {
        const judul = document.getElementById('toggle-title');
        const teks  = document.getElementById('toggle-text');
        const btn   = document.getElementById('btn-toggle-confirm');

        if (statusTujuan === 'aktif') {
            judul.textContent = 'Aktifkan sesi ini?';
            teks.innerHTML = 'Sesi <strong>' + namaSesi + '</strong> akan aktif dan bisa diakses peserta yang sesuai jadwal.';
        } else {
            judul.textContent = 'Nonaktifkan sesi ini?';
            teks.innerHTML = 'Sesi <strong>' + namaSesi + '</strong> akan disembunyikan dari peserta. Data nilai yang sudah ada tetap aman tersimpan.';
        }

        btn.href = 'sesi.php?toggle=' + id;
        document.getElementById('modal-toggle-status').classList.add('show');
    }

    function tutupToggleModal() {
        document.getElementById('modal-toggle-status').classList.remove('show');
    }
    </script>

    <?php if ($modal_hapus_id): ?>

  <div class="modal-overlay show" id="modal-hapus-paksa">
    <div class="modal-box">
      <div class="modal-icon modal-icon--alert" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <h2 class="modal-title">Sesi ini punya data nilai</h2>
      <p class="modal-text">
        Sesi <strong><?= htmlspecialchars($modal_hapus_nama) ?></strong> sudah dikerjakan
        oleh <strong style="color:var(--danger)"><?= (int)$modal_hapus_jumlah ?> peserta</strong>.
        Menghapus sesi ini akan <strong>menghapus permanen semua nilai mereka juga</strong>
        — tindakan ini tidak bisa dibatalkan.
        <br><br>
        Ketik nama sesi persis untuk konfirmasi:
      </p>
      <div style="padding:0 24px 16px;">
        <input type="text" id="konfirmasi-nama-sesi" class="form-input"
              placeholder="<?= htmlspecialchars($modal_hapus_nama) ?>"
              oninput="cekKonfirmasiSesi()">
      </div>
      <div class="modal-actions">
        <button type="button" id="btn-hapus-paksa" class="modal-btn modal-btn--danger" disabled
                onclick="window.location='sesi.php?hapus=<?= (int)$modal_hapus_id ?>&force=1'">
          Hapus permanen (<?= (int)$modal_hapus_jumlah ?> nilai ikut terhapus)
        </button>
        <button type="button" class="modal-btn modal-btn--secondary" onclick="window.location='sesi.php'">
          Batal, jangan hapus
        </button>
      </div>
    </div>
  </div>
<script>
function cekKonfirmasiSesi() {
    const target = <?= json_encode($modal_hapus_nama) ?>;
    const input  = document.getElementById('konfirmasi-nama-sesi').value;
    document.getElementById('btn-hapus-paksa').disabled = (input !== target);
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>