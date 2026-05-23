<?php
require_once '../config.php';
cekAdmin();

$msg = '';

// HAPUS SOAL
if (isset($_GET['hapus'])) {
    $conn->query("DELETE FROM soal WHERE id=".(int)$_GET['hapus']);
    $msg = 'Soal berhasil dihapus.';
}

// HAPUS KATEGORI
if (isset($_GET['hapus_kat'])) {
    $conn->query("DELETE FROM kategori WHERE id=".(int)$_GET['hapus_kat']);
    $msg = 'Kategori dihapus.';
}

// TAMBAH KATEGORI
if (isset($_POST['tambah_kategori'])) {
    $nm = trim($_POST['nama_kategori']);
    $jn = $_POST['jenis_kategori'];
    $s  = $conn->prepare("INSERT INTO kategori (nama,jenis) VALUES (?,?)");
    $s->bind_param("ss", $nm, $jn);
    $s->execute();
    $msg = "Kategori '$nm' berhasil ditambahkan.";
}

// SIMPAN SOAL
if (isset($_POST['simpan_soal'])) {
    $id  = (int)($_POST['id'] ?? 0);
    $kid = (int)$_POST['kategori_id'];
    $p   = trim($_POST['pertanyaan']);
    $a   = trim($_POST['pilihan_a']);
    $b   = trim($_POST['pilihan_b']);
    $c   = trim($_POST['pilihan_c']);
    $d   = trim($_POST['pilihan_d']);
    $jwb = $_POST['jawaban_benar'];

    if ($id > 0) {
        $s = $conn->prepare("UPDATE soal SET kategori_id=?,pertanyaan=?,pilihan_a=?,pilihan_b=?,pilihan_c=?,pilihan_d=?,jawaban_benar=? WHERE id=?");
        $s->bind_param("issssssi", $kid,$p,$a,$b,$c,$d,$jwb,$id);
        $s->execute();
        $msg = 'Soal berhasil diperbarui.';
    } else {
        $s = $conn->prepare("INSERT INTO soal (kategori_id,pertanyaan,pilihan_a,pilihan_b,pilihan_c,pilihan_d,jawaban_benar) VALUES (?,?,?,?,?,?,?)");
        $s->bind_param("issssss", $kid,$p,$a,$b,$c,$d,$jwb);
        $s->execute();
        $msg = 'Soal berhasil ditambahkan.';
    }
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM soal WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);

// Ambil semua kategori
$kat_res  = $conn->query("SELECT * FROM kategori ORDER BY jenis, nama");
$kat_arr  = [];
while ($k = $kat_res->fetch_assoc()) $kat_arr[] = $k;

// Filter & ambil soal
$fj = $_GET['jenis'] ?? '';
$where = $fj ? "WHERE k.jenis='$fj'" : "";
$soal_res = $conn->query("
    SELECT s.*, k.nama as nama_kat, k.jenis
    FROM soal s
    LEFT JOIN kategori k ON s.kategori_id = k.id
    $where
    ORDER BY k.jenis, k.nama, s.id DESC
");

$admin_page = 'soal';
$page_title = 'Bank Soal — BBU Tryout';
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
        <h1>Bank soal</h1>
        <p>Kelola soal ujian SOU dan GMDSS</p>
      </header>
      <a href="soal.php?action=tambah" class="btn-admin-primary">
        <?= admin_icon('plus') ?> Tambah soal
      </a>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- KELOLA KATEGORI -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header" style="cursor:pointer;" onclick="toggleKat()">
        <h3>Kelola kategori soal</h3>
        <span id="kat-toggle" style="font-size:13px;color:var(--text-muted);">▼ Buka</span>
      </div>
      <div id="kat-body" style="display:none;">
        <div class="card-body" style="border-bottom:1px solid var(--border);">
          <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div>
              <label class="form-label">Nama Kategori</label>
              <input type="text" name="nama_kategori" class="form-input"
                     placeholder="Contoh: Navigasi Elektronik"
                     style="width:230px;" required>
            </div>
            <div>
              <label class="form-label">Program</label>
              <select name="jenis_kategori" class="form-input" style="width:130px;">
                <option value="SOU">SOU</option>
                <option value="GMDSS">GMDSS</option>
              </select>
            </div>
            <button type="submit" name="tambah_kategori"
                    style="background:var(--primary);color:white;border:none;
                           padding:10px 18px;border-radius:8px;font-size:14px;
                           font-weight:600;cursor:pointer;">
              Tambah
            </button>
          </form>
        </div>
        <div class="card-body">
          <?php if (count($kat_arr)): ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($kat_arr as $k): ?>
            <div style="display:flex;align-items:center;gap:8px;background:#f8f9fa;
                        border:1px solid var(--border);border-radius:8px;padding:6px 12px;">
              <span class="badge badge-<?= strtolower($k['jenis']) ?>"><?= $k['jenis'] ?></span>
              <span style="font-size:14px;"><?= htmlspecialchars($k['nama']) ?></span>
              <a href="soal.php?hapus_kat=<?= $k['id'] ?>"
                 onclick="return confirm('Hapus kategori ini?')"
                 style="color:var(--danger);font-size:14px;text-decoration:none;
                        font-weight:700;line-height:1;">✕</a>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color:var(--text-muted);font-size:14px;">
            Belum ada kategori. Tambahkan kategori dulu sebelum input soal.
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- FORM TAMBAH / EDIT SOAL -->
    <?php if ($show_form): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h3><?= $edit ? 'Edit soal' : 'Tambah soal baru' ?></h3>
        <a href="soal.php" class="link-muted">Tutup</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">

          <div style="margin-bottom:16px;">
            <label class="form-label">Kategori *</label>
            <select name="kategori_id" class="form-input" style="max-width:320px;" required>
              <option value="">— Pilih Kategori —</option>
              <?php foreach ($kat_arr as $k): ?>
              <option value="<?= $k['id'] ?>"
                <?= ($edit['kategori_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                [<?= $k['jenis'] ?>] <?= htmlspecialchars($k['nama']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="margin-bottom:16px;">
            <label class="form-label">Pertanyaan *</label>
            <textarea name="pertanyaan" class="form-input" rows="4"
                      placeholder="Tulis pertanyaan di sini..." required
              ><?= htmlspecialchars($edit['pertanyaan'] ?? '') ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <?php foreach (['a','b','c','d'] as $o): ?>
            <div>
              <label class="form-label">Pilihan <?= strtoupper($o) ?> *</label>
              <input type="text" name="pilihan_<?= $o ?>" class="form-input"
                     placeholder="Isi pilihan <?= strtoupper($o) ?>"
                     value="<?= htmlspecialchars($edit['pilihan_'.$o] ?? '') ?>" required>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-bottom:24px;">
            <label class="form-label">Jawaban Benar *</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
              <?php foreach (['A','B','C','D'] as $o): ?>
              <label class="jwb-label <?= ($edit['jawaban_benar'] ?? '') === $o ? 'checked' : '' ?>"
                     id="lbl-<?= $o ?>" onclick="pilihJawaban('<?= $o ?>')">
                <input type="radio" name="jawaban_benar" value="<?= $o ?>" style="display:none;"
                       <?= ($edit['jawaban_benar'] ?? '') === $o ? 'checked' : '' ?> required>
                Pilihan <?= $o ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" name="simpan_soal" class="btn-primary">Simpan soal</button>
            <a href="soal.php" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- FILTER -->
    <div style="display:flex;gap:8px;margin-bottom:14px;align-items:center;flex-wrap:wrap;">
      <span style="font-size:13px;color:var(--text-muted);font-weight:500;">Filter:</span>
      <a href="soal.php"
         style="<?= !$fj ? 'background:var(--primary);color:white;' : 'background:#f8f9fa;color:var(--text);' ?>
                padding:6px 16px;border-radius:20px;font-size:13px;
                text-decoration:none;border:1px solid var(--border);">Semua</a>
      <a href="soal.php?jenis=SOU"
         style="<?= $fj==='SOU' ? 'background:#1a3c6e;color:white;' : 'background:#e8f0fe;color:#1a3c6e;' ?>
                padding:6px 16px;border-radius:20px;font-size:13px;text-decoration:none;">SOU</a>
      <a href="soal.php?jenis=GMDSS"
         style="<?= $fj==='GMDSS' ? 'background:#856404;color:white;' : 'background:#fff3cd;color:#856404;' ?>
                padding:6px 16px;border-radius:20px;font-size:13px;text-decoration:none;">GMDSS</a>
    </div>

    <!-- TABEL SOAL -->
    <div class="card">
      <div class="card-header">
        <h3>Daftar soal
          <span style="background:#f0f2f5;padding:2px 10px;border-radius:20px;
                       font-size:13px;font-weight:400;margin-left:8px;">
            <?= $soal_res->num_rows ?> soal
          </span>
        </h3>
      </div>

      <?php if ($soal_res->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Pertanyaan</th>
            <th>Kategori</th>
            <th style="text-align:center;">Jawaban</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $no=1; while ($s = $soal_res->fetch_assoc()): ?>
          <tr>
            <td style="color:var(--text-muted);"><?= $no++ ?></td>
            <td style="max-width:320px;line-height:1.5;font-size:14px;">
              <?= htmlspecialchars(mb_substr($s['pertanyaan'], 0, 90)) ?>
              <?= mb_strlen($s['pertanyaan']) > 90 ? '…' : '' ?>
            </td>
            <td>
              <span class="badge badge-<?= strtolower($s['jenis']) ?>"><?= $s['jenis'] ?></span>
              <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
                <?= htmlspecialchars($s['nama_kat'] ?? '—') ?>
              </div>
            </td>
            <td style="text-align:center;">
              <span style="background:#e8f8f0;color:var(--success);font-weight:700;
                           padding:4px 14px;border-radius:6px;font-size:15px;">
                <?= $s['jawaban_benar'] ?>
              </span>
            </td>
            <td>
              <a href="soal.php?edit=<?= $s['id'] ?>" class="btn-link">Edit</a>
              <a href="soal.php?hapus=<?= $s['id'] ?>" class="btn-link-danger"
                 onclick="return confirm('Hapus soal ini?')">Hapus</a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada soal</strong></p>
        <p>Buka bagian <strong>Kategori</strong> dulu, lalu klik <strong>Tambah soal</strong>.</p>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
function toggleKat() {
    const b = document.getElementById('kat-body');
    const t = document.getElementById('kat-toggle');
    const open = b.style.display === 'none';
    b.style.display = open ? 'block' : 'none';
    t.textContent   = open ? '▲ Tutup' : '▼ Buka';
}

function pilihJawaban(opt) {
    ['A','B','C','D'].forEach(o => {
        const lbl = document.getElementById('lbl-'+o);
        const inp = lbl.querySelector('input');
        if (o === opt) {
            inp.checked = true;
            lbl.classList.add('checked');
        } else {
            inp.checked = false;
            lbl.classList.remove('checked');
        }
    });
}
</script>
</body>
</html>