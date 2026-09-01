<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

$msg = '';
$error = '';
// Ambil pesan flash dari redirect sebelumnya (kalau ada)
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
$materi_id = (int) ($_GET['materi'] ?? 0);
$cari_materi = trim($_GET['cari'] ?? '');
$cari_soal = trim($_GET['cari_soal'] ?? '');

if (isset($_GET['hapus'])) {
    $sid = (int) $_GET['hapus'];
    $row = $conn->query("SELECT kategori_id FROM soal WHERE id=$sid")->fetch_assoc();
    $del = $conn->prepare("DELETE FROM soal WHERE id=?");
    $del->bind_param("i", $sid);
    $del->execute();

    $tujuan_materi = $row ? (int) $row['kategori_id'] : 0;
    $_SESSION['flash_msg'] = 'Soal berhasil dihapus.';
    redirect('/admin/soal.php' . ($tujuan_materi ? '?materi=' . $tujuan_materi : ''));
}

if (isset($_GET['hapus_kat'])) {
    $kid = (int) $_GET['hapus_kat'];
    $del2 = $conn->prepare("DELETE FROM kategori WHERE id=?");
    $del2->bind_param("i", $kid);
    $del2->execute();

    $_SESSION['flash_msg'] = 'Materi dihapus.';
    redirect('/admin/soal.php');
}

// ——— Tambah materi ———
if (isset($_POST['tambah_kategori'])) {
    $nm = trim($_POST['nama_kategori'] ?? '');
    if ($nm !== '') {
        $s = $conn->prepare('INSERT INTO kategori (nama) VALUES (?)');
        $s->bind_param('s', $nm);
        $s->execute();
        $msg = "Materi \"$nm\" berhasil ditambahkan.";
    }
}

// ——— Simpan soal ———
if (isset($_POST['simpan_soal'])) {
    $id  = (int) ($_POST['id'] ?? 0);
    $kid = (int) $_POST['kategori_id'];
    $p   = trim($_POST['pertanyaan'] ?? '');
    $a   = trim($_POST['pilihan_a'] ?? '');
    $b   = trim($_POST['pilihan_b'] ?? '');
    $c   = trim($_POST['pilihan_c'] ?? '');
    $d   = trim($_POST['pilihan_d'] ?? '');
    $jwb = $_POST['jawaban_benar'] ?? '';

    if ($id > 0) {
        $s = $conn->prepare('UPDATE soal SET kategori_id=?,pertanyaan=?,pilihan_a=?,pilihan_b=?,pilihan_c=?,pilihan_d=?,jawaban_benar=? WHERE id=?');
        $s->bind_param('issssssi', $kid, $p, $a, $b, $c, $d, $jwb, $id);
        if ($s->execute()) {
            $msg = 'Soal berhasil diperbarui.';
        } else {
            $error = 'Gagal memperbarui soal.';
        }
    } else {
        $s = $conn->prepare('INSERT INTO soal (kategori_id,pertanyaan,pilihan_a,pilihan_b,pilihan_c,pilihan_d,jawaban_benar) VALUES (?,?,?,?,?,?,?)');
        $s->bind_param('issssss', $kid, $p, $a, $b, $c, $d, $jwb);
        if ($s->execute()) {
            $msg = 'Soal berhasil ditambahkan.';
        } else {
            $error = 'Gagal menyimpan soal. Silakan coba lagi.';
        }
    }
    $materi_id = $kid;
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query('SELECT * FROM soal WHERE id=' . (int) $_GET['edit'])->fetch_assoc();
    if ($edit) {
        $materi_id = (int) $edit['kategori_id'];
    }
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);

// Daftar materi + jumlah soal (dengan filter jenis & pencarian nama)
// Daftar materi + jumlah soal (dengan pencarian nama)
$sql_materi = "
    SELECT k.*, COUNT(s.id) AS jumlah_soal
    FROM kategori k
    LEFT JOIN soal s ON s.kategori_id = k.id
    WHERE 1=1
";
if ($cari_materi !== '') {
    $sql_materi .= " AND k.nama LIKE '%" . $conn->real_escape_string($cari_materi) . "%'";
}
$sql_materi .= ' GROUP BY k.id ORDER BY k.nama';

$materi_list = [];
$res_materi = $conn->query($sql_materi);
while ($row = $res_materi->fetch_assoc()) {
    $materi_list[] = $row;
}

$materi_aktif = null;
if ($materi_id > 0) {
    $st = $conn->prepare('SELECT k.*, (SELECT COUNT(*) FROM soal WHERE kategori_id=k.id) AS jumlah_soal FROM kategori k WHERE k.id=?');
    $st->bind_param('i', $materi_id);
    $st->execute();
    $materi_aktif = $st->get_result()->fetch_assoc();
}

// Daftar soal dalam materi aktif (dengan pencarian pertanyaan)
$soal_res = null;
if ($materi_aktif) {
    $sql_soal = 'SELECT * FROM soal WHERE kategori_id=?';
    if ($cari_soal !== '') {
        $sql_soal .= ' AND pertanyaan LIKE ?';
        $st = $conn->prepare($sql_soal . ' ORDER BY id DESC');
        $like = '%' . $cari_soal . '%';
        $st->bind_param('is', $materi_id, $like);
    } else {
        $st = $conn->prepare($sql_soal . ' ORDER BY id DESC');
        $st->bind_param('i', $materi_id);
    }
    $st->execute();
    $soal_res = $st->get_result();
}

$admin_page = 'soal';
$page_title = $materi_aktif
    ? ($materi_aktif['nama'] . ' - Bank Soal')
    : 'Bank Soal';

$topbar_breadcrumb = $materi_aktif
    ? 'Bank Soal / ' . e($materi_aktif['nama'])
    : 'Bank Soal';
$topbar_title = $materi_aktif
    ? e($materi_aktif['nama'])
    : 'Bank Soal';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($materi_aktif): ?>

    <?php else: ?>

    <section class="add-materi-box">
      <h4>Tambah materi ujian baru</h4>
      <form method="POST" class="add-materi-form">
        <div>
          <label class="form-label">Nama materi</label>
          <input type="text" name="nama_kategori" class="form-input"
                placeholder="Contoh: Navigasi Elektronik" required>
        </div>
        <div class="add-materi-action">
          <label class="form-label">&nbsp;</label>
          <button type="submit" name="tambah_kategori" class="btn-admin-primary">
            <i class="fas fa-plus" style="font-size:12px;"></i> Simpan materi
          </button>
        </div>
      </form>
    </section>

    <?php endif; ?>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$materi_aktif): ?>
      
    <!-- ═══════════════ LIST MATERI ═══════════════ -->
    <div class="soal-filter-row">
      <form method="GET" class="soal-search-box">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="text" name="cari" placeholder="Cari materi..." value="<?= e($cari_materi) ?>">
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <h3>Daftar materi</h3>
      </div>

      <?php if (count($materi_list) > 0): ?>
      <div class="materi-list">
        <?php foreach ($materi_list as $m): ?>
        <div class="materi-row">
          <a href="soal.php?materi=<?= (int) $m['id'] ?>" class="materi-row-name"><?= e($m['nama']) ?></a>
          <span class="materi-row-count"><?= (int) $m['jumlah_soal'] ?> soal</span>
          <div class="materi-row-actions">
            <a href="soal.php?materi=<?= (int) $m['id'] ?>"
               class="btn-icon" data-tooltip="Kelola soal">
              <i class="fas fa-list"></i>
            </a>
            <a href="soal.php?materi=<?= (int) $m['id'] ?>&action=tambah"
               class="btn-icon" data-tooltip="Tambah soal">
              <i class="fas fa-plus"></i>
            </a>
            <a href="#"
               class="btn-icon btn-icon-delete" data-tooltip="Hapus materi"
               onclick="if(confirm('Hapus materi ini beserta semua soalnya?')) window.location='soal.php?hapus_kat=<?= (int) $m['id'] ?>'; return false;">
              <i class="fas fa-trash"></i>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <p><strong><?= $cari_materi ? 'Materi tidak ditemukan' : 'Belum ada materi ujian' ?></strong></p>
        <p><?= $cari_materi ? 'Coba kata kunci lain.' : 'Gunakan formulir di atas untuk menambah materi pertama.' ?></p>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══════════════ DETAIL SATU MATERI ═══════════════ -->
    <?php if ($show_form): ?>
    <div class="card" id="form-soal" style="margin-bottom:20px;">
      <div class="card-header">
        <h3><?= $edit ? 'Edit soal' : 'Tambah soal baru' ?></h3>
        <a href="soal.php?materi=<?= $materi_id ?>" class="link-muted">Tutup</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
          <input type="hidden" name="kategori_id" value="<?= $materi_id ?>">

          <p style="margin-bottom:16px;font-size:14px;color:#64748b;">
            Materi: <strong><?= e($materi_aktif['nama']) ?></strong> 
          </p>

          <div style="margin-bottom:16px;">
            <label class="form-label">Pertanyaan *</label>
            <textarea name="pertanyaan" class="form-input" rows="4" required
                      placeholder="Tulis pertanyaan di sini..."><?= e($edit['pertanyaan'] ?? '') ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
            <?php foreach (['a', 'b', 'c', 'd'] as $o): ?>
            <div>
              <label class="form-label">Pilihan <?= strtoupper($o) ?> *</label>
              <input type="text" name="pilihan_<?= $o ?>" class="form-input" required
                     value="<?= e($edit['pilihan_' . $o] ?? '') ?>">
            </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-bottom:24px;">
            <label class="form-label">Jawaban benar *</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
              <?php foreach (['A', 'B', 'C', 'D'] as $o): ?>
              <label class="jwb-label <?= ($edit['jawaban_benar'] ?? '') === $o ? 'checked' : '' ?>"
                     id="lbl-<?= $o ?>" onclick="pilihJawaban('<?= $o ?>')">
                <input type="radio" name="jawaban_benar" value="<?= $o ?>" style="display:none;"
                       <?= ($edit['jawaban_benar'] ?? '') === $o ? 'checked' : '' ?> required>
                <?= $o ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" name="simpan_soal" class="btn-primary">Simpan soal</button>
            <a href="soal.php?materi=<?= $materi_id ?>" class="btn-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <div>
          <h3 style="margin:0;">Daftar soal</h3>
          <span style="font-size:12px;color:#64748b;">
            <?= e($materi_aktif['nama']) ?> · <?= (int)$materi_aktif['jumlah_soal'] ?> soal
          </span>
        </div>
        <a href="soal.php?materi=<?= $materi_id ?>&action=tambah#form-soal"
          class="btn-admin-primary" style="font-size:13px;padding:8px 14px;">
          <?= admin_icon('plus') ?> Tambah soal
        </a>
      </div>

      <div class="soal-search-inline">
        <form method="GET">
          <input type="hidden" name="materi" value="<?= $materi_id ?>">
          <i class="fas fa-search" aria-hidden="true"></i>
          <input type="text" name="cari_soal" placeholder="Cari pertanyaan di materi ini..." value="<?= e($cari_soal) ?>">
        </form>
      </div>

      <?php if ($soal_res && $soal_res->num_rows > 0): ?>
      <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th style="width:50px;">#</th>
            <th>Pertanyaan</th>
            <th style="width:80px;text-align:center;">Kunci</th>
            <th style="width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $no = 1; while ($s = $soal_res->fetch_assoc()): ?>
          <tr>
            <td style="color:#64748b;"><?= $no++ ?></td>
            <td style="max-width:480px;line-height:1.5;font-size:14px;">
              <?= e(mb_strlen($s['pertanyaan']) > 120 ? mb_substr($s['pertanyaan'], 0, 120) . '…' : $s['pertanyaan']) ?>
            </td>
            <td style="text-align:center;">
              <span style="background:#e6f4ec;color:#166534;font-weight:700;padding:4px 12px;border-radius:6px;">
                <?= e($s['jawaban_benar']) ?>
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <a href="soal.php?edit=<?= $s['id'] ?>#form-soal"
                  class="btn-icon btn-icon-edit"
                  data-tooltip="Edit soal">
                  <i class="fas fa-pen"></i>
                </a>
                <a href="soal.php?hapus=<?= $s['id'] ?>"
                  class="btn-icon btn-icon-delete"
                  data-tooltip="Hapus soal"
                  onclick="return confirm('Hapus soal <?= htmlspecialchars(addslashes($s['pertanyaan'])) ?>?')">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <p><strong><?= $cari_soal ? 'Soal tidak ditemukan' : 'Belum ada soal di materi ini' ?></strong></p>
        <p>
          <?php if ($cari_soal): ?>
            Coba kata kunci lain.
          <?php else: ?>
            <a href="soal.php?materi=<?= $materi_id ?>&action=tambah" class="btn-link">Tambah soal pertama</a>
          <?php endif; ?>
        </p>
      </div>
      <?php endif; ?>
    </div>

    <?php endif; ?>

<script>
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
<?php include __DIR__ . '/partials/layout-end.php'; ?>