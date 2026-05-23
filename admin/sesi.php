<?php
require_once '../config.php';
cekAdmin();

$msg = '';

// Toggle aktif/nonaktif
if (isset($_GET['toggle'])) {
    $id  = (int)$_GET['toggle'];
    $cur = $conn->query("SELECT status FROM sesi_ujian WHERE id=$id")->fetch_assoc()['status'];
    $new = $cur === 'aktif' ? 'nonaktif' : 'aktif';
    $conn->query("UPDATE sesi_ujian SET status='$new' WHERE id=$id");
    $msg = $new === 'aktif' ? 'Sesi berhasil diaktifkan.' : 'Sesi dinonaktifkan.';
}

// Hapus
if (isset($_GET['hapus'])) {
    $conn->query("DELETE FROM sesi_ujian WHERE id=".(int)$_GET['hapus']);
    $msg = 'Sesi ujian dihapus.';
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM sesi_ujian WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

// Simpan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $nama  = trim($_POST['nama_sesi']);
    $jenis = $_POST['jenis'];
    $tgl   = $_POST['tanggal'];
    $jam1  = $_POST['jam_mulai'];
    $jam2  = $_POST['jam_selesai'];
    $dur   = (int)$_POST['durasi_menit'];
    $jml   = (int)$_POST['jumlah_soal'];
    $mode  = $_POST['mode'];

    if ($id > 0) {
        $s = $conn->prepare("UPDATE sesi_ujian SET nama_sesi=?,jenis=?,tanggal=?,jam_mulai=?,jam_selesai=?,durasi_menit=?,jumlah_soal=?,mode=? WHERE id=?");
        $s->bind_param("sssssiisi", $nama,$jenis,$tgl,$jam1,$jam2,$dur,$jml,$mode,$id);
        $s->execute();
        $msg = 'Sesi berhasil diperbarui.';
    } else {
        $s = $conn->prepare("INSERT INTO sesi_ujian (nama_sesi,jenis,tanggal,jam_mulai,jam_selesai,durasi_menit,jumlah_soal,mode) VALUES (?,?,?,?,?,?,?,?)");
        $s->bind_param("sssssiis", $nama,$jenis,$tgl,$jam1,$jam2,$dur,$jml,$mode);
        $s->execute();
        $msg = 'Sesi ujian berhasil dibuat.';
    }
    $edit = null;
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);
$list = $conn->query("SELECT * FROM sesi_ujian ORDER BY tanggal DESC, jam_mulai DESC");

$admin_page = 'sesi';
$page_title = 'Sesi Ujian — BBU Tryout';
require_once __DIR__ . '/partials/icons.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body>
<div class="admin-wrapper">
<?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main-content">

    <div class="page-toolbar">
      <header class="page-header">
        <h1>Sesi ujian</h1>
        <p>Buat dan kelola jadwal ujian</p>
      </header>
      <a href="sesi.php?action=tambah" class="btn-admin-primary">
        <?= admin_icon('plus') ?> Buat sesi baru
      </a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
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
              <select name="jenis" class="form-input">
                <option value="SOU"   <?= ($edit['jenis'] ?? '') === 'SOU'   ? 'selected' : '' ?>>SOU</option>
                <option value="GMDSS" <?= ($edit['jenis'] ?? '') === 'GMDSS' ? 'selected' : '' ?>>GMDSS</option>
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
                       value="<?= $edit['jam_mulai'] ?? '08:00' ?>" required>
              </div>
              <div>
                <label class="form-label">Jam Selesai *</label>
                <input type="time" name="jam_selesai" class="form-input"
                       value="<?= $edit['jam_selesai'] ?? '10:00' ?>" required>
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
      <div class="card-header"><h3>Daftar sesi ujian</h3></div>
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
        <?php while ($s = $list->fetch_assoc()): ?>
          <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($s['nama_sesi']) ?></td>
            <td><span class="badge badge-<?= strtolower($s['jenis']) ?>"><?= $s['jenis'] ?></span></td>
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
              <a href="sesi.php?toggle=<?= $s['id'] ?>"
                 class="<?= $s['status'] === 'aktif' ? 'toggle-on' : 'toggle-off' ?>">
                <?= $s['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
              </a>
            </td>
            <td>
              <a href="sesi.php?edit=<?= $s['id'] ?>" class="btn-link">Edit</a>
              <a href="sesi.php?hapus=<?= $s['id'] ?>" class="btn-link-danger"
                 onclick="return confirm('Hapus sesi ini?')">Hapus</a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada sesi ujian</strong></p>
        <p>Gunakan tombol <strong>Buat sesi baru</strong> di atas.</p>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</body>
</html>