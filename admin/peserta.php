<?php
require_once '../config.php';
cekAdmin();

$msg = ''; $error = '';

// HAPUS
if (isset($_GET['hapus'])) {
    $conn->query("DELETE FROM peserta WHERE id=".(int)$_GET['hapus']);
    $msg = 'Peserta berhasil dihapus.';
}

// SIMPAN (tambah / edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama']);
    $nomor    = trim($_POST['nomor_peserta']);
    $jenis    = $_POST['jenis'];
    $angkatan = trim($_POST['angkatan']);
    $pass     = trim($_POST['password']);

    if ($id > 0) {
        if ($pass) {
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,jenis=?,angkatan=?,password=MD5(?) WHERE id=?");
            $s->bind_param("sssssi", $nama,$nomor,$jenis,$angkatan,$pass,$id);
        } else {
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,jenis=?,angkatan=? WHERE id=?");
            $s->bind_param("ssssi", $nama,$nomor,$jenis,$angkatan,$id);
        }
        $s->execute();
        $msg = 'Data peserta berhasil diperbarui.';
    } else {
        $s = $conn->prepare("INSERT INTO peserta (nama,nomor_peserta,jenis,angkatan,password) VALUES (?,?,?,?,MD5(?))");
        $s->bind_param("sssss", $nama,$nomor,$jenis,$angkatan,$pass);
        if ($s->execute()) $msg = 'Peserta berhasil ditambahkan.';
        else $error = 'Nomor peserta sudah digunakan!';
    }
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM peserta WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);
$list = $conn->query("SELECT * FROM peserta ORDER BY jenis, nama");

$admin_page = 'peserta';
$page_title = 'Kelola Peserta — BBU Tryout';
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
        <h1>Kelola peserta</h1>
        <p>Tambah, ubah, dan hapus data peserta</p>
      </header>
      <a href="peserta.php?action=tambah" class="btn-admin-primary">
        <?= admin_icon('plus') ?> Tambah peserta
      </a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- FORM TAMBAH / EDIT -->
    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h3><?= $edit ? 'Edit peserta' : 'Tambah peserta baru' ?></h3>
        <a href="peserta.php" class="link-muted">Tutup</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

            <div>
              <label class="form-label">Nama Lengkap *</label>
              <input type="text" name="nama" class="form-input"
                     placeholder="Contoh: Budi Santoso"
                     value="<?= htmlspecialchars($edit['nama'] ?? '') ?>" required>
            </div>

            <div>
              <label class="form-label">Nomor Peserta * <small style="color:var(--text-muted);font-weight:400;">(dipakai untuk login)</small></label>
              <input type="text" name="nomor_peserta" class="form-input"
                     placeholder="Contoh: BBU-001"
                     value="<?= htmlspecialchars($edit['nomor_peserta'] ?? '') ?>" required>
            </div>

            <div>
              <label class="form-label">Program *</label>
              <select name="jenis" class="form-input">
                <option value="SOU"   <?= ($edit['jenis'] ?? '') === 'SOU'   ? 'selected' : '' ?>>SOU — Ship's Officer Unlimited</option>
                <option value="GMDSS" <?= ($edit['jenis'] ?? '') === 'GMDSS' ? 'selected' : '' ?>>GMDSS — Global Maritime Distress</option>
              </select>
            </div>

            <div>
              <label class="form-label">Angkatan</label>
              <input type="text" name="angkatan" class="form-input"
                     placeholder="Contoh: Angkatan 2024-I"
                     value="<?= htmlspecialchars($edit['angkatan'] ?? '') ?>">
            </div>

            <div>
              <label class="form-label">
                Password <?= $edit ? '<small style="color:var(--text-muted);font-weight:400;">(kosongkan jika tidak diubah)</small>' : '*' ?>
              </label>
              <input type="text" name="password" class="form-input"
                     placeholder="<?= $edit ? 'Kosongkan jika tidak diubah' : 'Isi password peserta' ?>"
                     <?= $edit ? '' : 'required' ?>>
              <span class="form-hint">Saran: nomor peserta atau tanggal lahir (ddmmyyyy)</span>
            </div>

          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn-primary">Simpan</button>
            <a href="peserta.php" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- TABEL PESERTA -->
    <div class="card">
      <div class="card-header">
        <h3>Daftar peserta<span class="count-pill"><?= $list->num_rows ?> orang</span></h3>
      </div>

      <?php if ($list->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Nama</th>
            <th>Nomor Peserta</th>
            <th>Program</th>
            <th>Angkatan</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; while ($p = $list->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--text-muted);"><?= $no++ ?></td>
            <td style="font-weight:500;font-size:15px;"><?= htmlspecialchars($p['nama']) ?></td>
            <td>
              <code style="background:#f0f2f5;padding:3px 10px;border-radius:6px;font-size:13px;">
                <?= htmlspecialchars($p['nomor_peserta']) ?>
              </code>
            </td>
            <td><span class="badge badge-<?= strtolower($p['jenis']) ?>"><?= $p['jenis'] ?></span></td>
            <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($p['angkatan'] ?: '—') ?></td>
            <td>
              <a href="peserta.php?edit=<?= $p['id'] ?>" class="btn-link">Edit</a>
              <a href="peserta.php?hapus=<?= $p['id'] ?>" class="btn-link-danger"
                 onclick="return confirm('Hapus peserta <?= htmlspecialchars(addslashes($p['nama'])) ?>?')">Hapus</a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada peserta</strong></p>
        <p>Gunakan tombol <strong>Tambah peserta</strong> di atas.</p>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>
</body>
</html>