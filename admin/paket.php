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

// ——— Simpan perubahan checklist materi per paket ———
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_paket'])) {
    $paket_id = (int) $_POST['paket_id'];
    $kategori_dipilih = $_POST['kategori'] ?? []; // array of kategori_id yang dicentang

    // Hapus dulu semua mapping lama untuk paket ini
    $del = $conn->prepare("DELETE FROM paket_kategori WHERE paket_id=?");
    $del->bind_param("i", $paket_id);
    $del->execute();

    // Masukkan ulang sesuai centang terbaru
    $gagal = false;
    if (!empty($kategori_dipilih)) {
        $ins = $conn->prepare("INSERT INTO paket_kategori (paket_id, kategori_id) VALUES (?, ?)");
        foreach ($kategori_dipilih as $kid) {
            $kid = (int) $kid;
            $ins->bind_param("ii", $paket_id, $kid);
            if (!$ins->execute()) {
                $gagal = true;
            }
        }
    }

    $_SESSION['flash_msg'] = $gagal
        ? 'Sebagian perubahan gagal disimpan. Silakan cek kembali.'
        : 'Materi untuk paket berhasil diperbarui.';
    redirect('/admin/paket.php?paket=' . $paket_id);
}

// Daftar semua paket
$paket_list = [];
$res_paket = $conn->query("SELECT * FROM paket_program ORDER BY urutan");
while ($row = $res_paket->fetch_assoc()) {
    $paket_list[] = $row;
}

// Paket yang sedang aktif dilihat/diedit
$paket_aktif_id = (int) ($_GET['paket'] ?? ($paket_list[0]['id'] ?? 0));

// Semua kategori/materi yang ada
$semua_kategori = [];
$res_kat = $conn->query("SELECT * FROM kategori ORDER BY nama");
while ($row = $res_kat->fetch_assoc()) {
    $semua_kategori[] = $row;
}

// Kategori yang SUDAH ter-assign ke paket aktif
$kategori_terpilih = [];
if ($paket_aktif_id) {
    $st = $conn->prepare("SELECT kategori_id FROM paket_kategori WHERE paket_id=?");
    $st->bind_param("i", $paket_aktif_id);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $kategori_terpilih[] = (int) $row['kategori_id'];
    }
}

$admin_page = 'paket';
$page_title = 'Kelola Paket — BBU Tryout';
$topbar_breadcrumb = 'Kelola Paket';
$topbar_title      = 'Kelola Paket Program';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <div style="margin-bottom:16px;font-size:13px;color:var(--text-secondary);line-height:1.6;">
      Pilih paket, lalu centang materi yang termasuk di dalamnya. Perubahan langsung
      mempengaruhi soal yang akan diambil sistem saat peserta mengerjakan ujian.
    </div>

    <!-- TAB PAKET -->
    <div class="filter-group" style="margin-bottom:20px;">
      <?php foreach ($paket_list as $p): ?>
      <a href="paket.php?paket=<?= (int)$p['id'] ?>"
         class="filter-btn <?= $paket_aktif_id === (int)$p['id'] ? 'filter-btn-active' : '' ?>">
        <?= e($p['nama']) ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php
    $paket_aktif = null;
    foreach ($paket_list as $p) {
        if ((int)$p['id'] === $paket_aktif_id) { $paket_aktif = $p; break; }
    }
    ?>

    <?php if ($paket_aktif): ?>
    <div class="card">
      <div class="card-header">
        <h3>Materi untuk <?= e($paket_aktif['nama']) ?></h3>
        <span class="count-pill"><?= count($kategori_terpilih) ?> dari <?= count($semua_kategori) ?> materi</span>
      </div>

      <?php if (count($semua_kategori) > 0): ?>
      <form method="POST">
        <input type="hidden" name="paket_id" value="<?= $paket_aktif_id ?>">

        <div class="paket-checklist">
          <?php foreach ($semua_kategori as $k): ?>
          <label class="paket-check-row">
            <input type="checkbox" name="kategori[]" value="<?= (int)$k['id'] ?>"
                   <?= in_array((int)$k['id'], $kategori_terpilih) ? 'checked' : '' ?>>
            <span class="paket-check-name"><?= e($k['nama']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="card-body" style="border-top:1px solid var(--border);padding-top:16px;">
          <button type="submit" name="simpan_paket" class="btn-primary">Simpan perubahan</button>
        </div>
      </form>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada materi di Bank Soal</strong></p>
        <p>Tambahkan materi terlebih dahulu di menu <a href="soal.php" class="btn-link">Bank Soal</a>.</p>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <p><strong>Belum ada paket program</strong></p>
      <p>Hubungi pengembang sistem untuk menambahkan paket baru.</p>
    </div>
    <?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>