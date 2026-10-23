<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

const BS_KUNCI = ['A', 'B', 'C', 'D'];

function bs_flash(string $tipe, string $msg): void
{
    $_SESSION['flash_tipe'] = $tipe;
    $_SESSION['flash_msg']  = $msg;
}

function bs_count(mysqli $conn, string $sql, int $id): int
{
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    return (int) $st->get_result()->fetch_assoc()['n'];
}

function bs_ringkas(string $teks, int $maks = 110): string
{
    $teks = preg_replace('/\s+/u', ' ', trim($teks));
    return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks) . '…' : $teks;
}

$materi_id   = (int) ($_GET['materi'] ?? 0);
$cari_materi = trim($_GET['cari'] ?? '');
$cari_soal   = trim($_GET['q'] ?? '');

$form_kosong = [
    'id' => 0, 'pertanyaan' => '',
    'pilihan_a' => '', 'pilihan_b' => '', 'pilihan_c' => '', 'pilihan_d' => '',
    'jawaban_benar' => '', 'bobot' => 2,
];
$form         = $form_kosong;
$form_error   = '';
$buka_modal   = '';
$dipakai_edit = 0;

// ── TAMBAH MATERI ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_materi'])) {
    $nama = trim($_POST['nama_materi'] ?? '');
    if ($nama === '' || mb_strlen($nama) > 100) {
        bs_flash('error', 'Nama materi wajib diisi, maksimal 100 karakter.');
    } else {
        $s = $conn->prepare("INSERT INTO kategori (nama) VALUES (?)");
        $s->bind_param("s", $nama);
        if ($s->execute()) {
            bs_flash('success', "Materi \"$nama\" berhasil ditambahkan.");
        } else {
            bs_flash('error', 'Gagal menambahkan materi.');
        }
    }
    redirect('/admin/soal.php');
}

// ── HAPUS MATERI (hanya jika kosong) ──────────────────────
if (isset($_GET['hapus_materi'])) {
    $kid     = (int) $_GET['hapus_materi'];
    $n_soal  = bs_count($conn, "SELECT COUNT(*) AS n FROM soal WHERE kategori_id = ?", $kid);
    $n_hasil = bs_count($conn, "SELECT COUNT(*) AS n FROM hasil_per_materi WHERE kategori_id = ?", $kid);

    if ($n_soal > 0) {
        bs_flash('error', "Materi tidak bisa dihapus karena masih berisi $n_soal soal. Hapus soalnya terlebih dahulu.");
    } elseif ($n_hasil > 0) {
        bs_flash('error', 'Materi ini sudah tercatat dalam rekap nilai dan tidak bisa dihapus.');
    } else {
        $d = $conn->prepare("DELETE FROM kategori WHERE id = ?");
        $d->bind_param("i", $kid);
        if ($d->execute()) {
            bs_flash('success', 'Materi berhasil dihapus.');
        } else {
            bs_flash('error', 'Gagal menghapus materi.');
        }
    }
    redirect('/admin/soal.php');
}

// ── HAPUS SOAL (hanya jika belum pernah dijawab) ──────────
if (isset($_GET['hapus_soal'])) {
    $sid = (int) $_GET['hapus_soal'];
    $st  = $conn->prepare("SELECT kategori_id FROM soal WHERE id = ?");
    $st->bind_param("i", $sid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $kat = $row ? (int) $row['kategori_id'] : 0;

    if (!$row) {
        bs_flash('error', 'Soal tidak ditemukan.');
    } elseif (bs_count($conn, "SELECT COUNT(*) AS n FROM jawaban WHERE soal_id = ?", $sid) > 0) {
        bs_flash('error', 'Soal ini sudah dipakai dalam ujian dan tidak bisa dihapus. Anda bisa mengeditnya bila perlu.');
    } else {
        $d = $conn->prepare("DELETE FROM soal WHERE id = ?");
        $d->bind_param("i", $sid);
        if ($d->execute()) {
            bs_flash('success', 'Soal berhasil dihapus.');
        } else {
            bs_flash('error', 'Gagal menghapus soal.');
        }
    }
    redirect('/admin/soal.php' . ($kat ? '?materi=' . $kat : ''));
}

// ── SIMPAN SOAL (tambah / edit) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_soal'])) {
    $form = [
        'id'            => (int) ($_POST['id'] ?? 0),
        'pertanyaan'    => trim($_POST['pertanyaan'] ?? ''),
        'pilihan_a'     => trim($_POST['pilihan_a'] ?? ''),
        'pilihan_b'     => trim($_POST['pilihan_b'] ?? ''),
        'pilihan_c'     => trim($_POST['pilihan_c'] ?? ''),
        'pilihan_d'     => trim($_POST['pilihan_d'] ?? ''),
        'jawaban_benar' => strtoupper(trim($_POST['jawaban_benar'] ?? '')),
        'bobot'         => (int) ($_POST['bobot'] ?? 2),
    ];
    $kid        = (int) ($_POST['kategori_id'] ?? 0);
    $materi_id  = $kid;
    $error      = '';

    if ($form['pertanyaan'] === '') {
        $error = 'Pertanyaan wajib diisi.';
    }
    foreach (BS_KUNCI as $o) {
        if (!$error && $form['pilihan_' . strtolower($o)] === '') {
            $error = "Pilihan $o wajib diisi.";
        }
    }
    if (!$error && !in_array($form['jawaban_benar'], BS_KUNCI, true)) {
        $error = 'Pilih kunci jawaban.';
    }
    if (!$error && ($form['bobot'] < 1 || $form['bobot'] > 10)) {
        $error = 'Bobot harus antara 1 dan 10.';
    }
    if (!$error && bs_count($conn, "SELECT COUNT(*) AS n FROM kategori WHERE id = ?", $kid) === 0) {
        $error = 'Materi tidak ditemukan.';
    }

    if ($error === '') {
        if ($form['id'] > 0) {
            $s = $conn->prepare("
                UPDATE soal
                SET kategori_id=?, pertanyaan=?, pilihan_a=?, pilihan_b=?, pilihan_c=?, pilihan_d=?,
                    jawaban_benar=?, bobot=?
                WHERE id=?
            ");
            $s->bind_param("issssssii", $kid, $form['pertanyaan'], $form['pilihan_a'], $form['pilihan_b'],
                           $form['pilihan_c'], $form['pilihan_d'], $form['jawaban_benar'], $form['bobot'], $form['id']);
        } else {
            $s = $conn->prepare("
                INSERT INTO soal (kategori_id, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, bobot)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $s->bind_param("issssssi", $kid, $form['pertanyaan'], $form['pilihan_a'], $form['pilihan_b'],
                           $form['pilihan_c'], $form['pilihan_d'], $form['jawaban_benar'], $form['bobot']);
        }

        if ($s->execute()) {
            bs_flash('success', $form['id'] > 0 ? 'Soal berhasil diperbarui.' : 'Soal berhasil ditambahkan.');
            redirect('/admin/soal.php?materi=' . $kid);
        }
        $error = 'Gagal menyimpan soal. Silakan coba lagi.';
    }

    $form_error = $error;
    $buka_modal = 'bs-modal-soal';
}

// ── BUKA FORM EDIT ────────────────────────────────────────
if (isset($_GET['edit'])) {
    $st = $conn->prepare("SELECT * FROM soal WHERE id = ?");
    $eid = (int) $_GET['edit'];
    $st->bind_param("i", $eid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $form = [
            'id' => (int) $row['id'], 'pertanyaan' => $row['pertanyaan'],
            'pilihan_a' => $row['pilihan_a'], 'pilihan_b' => $row['pilihan_b'],
            'pilihan_c' => $row['pilihan_c'], 'pilihan_d' => $row['pilihan_d'],
            'jawaban_benar' => $row['jawaban_benar'], 'bobot' => (int) $row['bobot'],
        ];
        $materi_id  = (int) $row['kategori_id'];
        $buka_modal = 'bs-modal-soal';
    }
}

// ── AMBIL DATA MATERI AKTIF ───────────────────────────────
$materi = null;
if ($materi_id > 0) {
    $st = $conn->prepare("SELECT id, nama FROM kategori WHERE id = ?");
    $st->bind_param("i", $materi_id);
    $st->execute();
    $materi = $st->get_result()->fetch_assoc() ?: null;
    if (!$materi) {
        $materi_id  = 0;
        $buka_modal = '';
    }
}
if ($buka_modal === 'bs-modal-soal' && !$materi) {
    $buka_modal = '';
}
if ($buka_modal === 'bs-modal-soal' && $form['id'] > 0) {
    $dipakai_edit = bs_count($conn, "SELECT COUNT(*) AS n FROM jawaban WHERE soal_id = ?", $form['id']);
}

$flash_msg  = $_SESSION['flash_msg'] ?? '';
$flash_tipe = $_SESSION['flash_tipe'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipe']);

// ── DATA TAMPILAN ─────────────────────────────────────────
$admin_page = 'soal';
$page_title = $materi ? ($materi['nama'] . ' — Bank Soal') : 'Bank Soal — BBU Tryout';
$topbar_breadcrumb = $materi ? 'Bank Soal / ' . $materi['nama'] : 'Bank Soal';
$topbar_title      = $materi ? $materi['nama'] : 'Bank Soal';

if ($materi) {
    // ── PAGINASI ──────────────────────────────────────────
    $per_pilihan = [20, 30, 40, 50];
    $per_page    = (int) ($_GET['per'] ?? 20);
    if (!in_array($per_page, $per_pilihan, true)) {
        $per_page = 20;
    }
    $halaman_req = max(1, (int) ($_GET['page'] ?? 1));

    // Filter pencarian (dipakai bersama untuk hitung & ambil data)
    $filter_sql    = " WHERE s.kategori_id = ?" . ($cari_soal !== '' ? " AND s.pertanyaan LIKE ?" : "");
    $bind_tipe     = $cari_soal !== '' ? "is" : "i";
    $bind_nilai    = $cari_soal !== '' ? [$materi_id, '%' . $cari_soal . '%'] : [$materi_id];

    // Total soal hasil filter
    $st = $conn->prepare("SELECT COUNT(*) AS n FROM soal s" . $filter_sql);
    $st->bind_param($bind_tipe, ...$bind_nilai);
    $st->execute();
    $total_hasil = (int) $st->get_result()->fetch_assoc()['n'];

    $total_halaman = max(1, (int) ceil($total_hasil / $per_page));
    $halaman       = min($halaman_req, $total_halaman);
    $offset        = ($halaman - 1) * $per_page;

    // Data untuk halaman aktif (LIMIT/OFFSET sudah integer, aman diinterpolasi)
    $st = $conn->prepare("
        SELECT s.*, (SELECT COUNT(*) FROM jawaban j WHERE j.soal_id = s.id) AS dipakai
        FROM soal s
        " . $filter_sql . "
        ORDER BY s.id
        LIMIT $per_page OFFSET $offset
    ");
    $st->bind_param($bind_tipe, ...$bind_nilai);
    $st->execute();
    $res = $st->get_result();
    $soal_list = [];
    while ($r = $res->fetch_assoc()) $soal_list[] = $r;

    // Parameter dasar untuk link paginasi
    $pg_base = ['materi' => $materi_id, 'per' => $per_page];
    if ($cari_soal !== '') $pg_base['q'] = $cari_soal;
    $pg_url = function (int $p) use ($pg_base) {
        return url('/admin/soal.php?' . http_build_query($pg_base + ['page' => $p]));
    };

    // Ringkasan materi (total seluruh soal, tidak terpengaruh pencarian)
    $st = $conn->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(bobot), 0) AS b FROM soal WHERE kategori_id = ?");
    $st->bind_param("i", $materi_id);
    $st->execute();
    $ringkas_materi = $st->get_result()->fetch_assoc();
} else {
    $sql = "
        SELECT k.id, k.nama,
               COUNT(s.id) AS jml,
               COALESCE(SUM(s.bobot), 0) AS bobot,
               (SELECT GROUP_CONCAT(pp.nama ORDER BY pp.urutan SEPARATOR '||')
                  FROM paket_kategori pk
                  JOIN paket_program pp ON pp.id = pk.paket_id
                 WHERE pk.kategori_id = k.id) AS paket_nama
        FROM kategori k
        LEFT JOIN soal s ON s.kategori_id = k.id
    ";
    if ($cari_materi !== '') $sql .= " WHERE k.nama LIKE ?";
    $sql .= " GROUP BY k.id, k.nama ORDER BY k.id";
    $st = $conn->prepare($sql);
    if ($cari_materi !== '') {
        $like = '%' . $cari_materi . '%';
        $st->bind_param("s", $like);
    }
    $st->execute();
    $res = $st->get_result();
    $materi_list = [];
    while ($r = $res->fetch_assoc()) $materi_list[] = $r;

    $total_materi = (int) $conn->query("SELECT COUNT(*) AS n FROM kategori")->fetch_assoc()['n'];
    $total_soal   = (int) $conn->query("SELECT COUNT(*) AS n FROM soal")->fetch_assoc()['n'];
    $total_kosong = (int) $conn->query("
        SELECT COUNT(*) AS n FROM kategori k
        WHERE NOT EXISTS (SELECT 1 FROM soal s WHERE s.kategori_id = k.id)
    ")->fetch_assoc()['n'];
}

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($flash_msg): ?>
    <div class="alert alert-<?= $flash_tipe === 'error' ? 'error' : 'success' ?>"><?= e($flash_msg) ?></div>
    <?php endif; ?>

<?php if (!$materi): ?>
    <!-- ═════════════ DAFTAR MATERI ═════════════ -->

    <div class="bs3-hero">
      <div class="bs3-hero-teks">
        <h2>Bank Soal</h2>
        <p>Kelola materi dan soal yang dipakai dalam tryout.</p>
      </div>
      <div class="bs3-stats">
        <div class="bs3-stat">
          <strong><?= $total_materi ?></strong>
          <span>Materi</span>
        </div>
        <div class="bs3-stat">
          <strong><?= $total_soal ?></strong>
          <span>Soal</span>
        </div>
        <div class="bs3-stat<?= $total_kosong > 0 ? ' bs3-stat-warn' : '' ?>">
          <strong><?= $total_kosong ?></strong>
          <span>Belum ada soal</span>
        </div>
      </div>
      <button type="button" class="bs3-tambah" data-buka="bs-modal-materi">
        <i class="fas fa-plus"></i> Tambah materi
      </button>
    </div>

    <form method="GET" class="bs3-cari" role="search">
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" name="cari" value="<?= e($cari_materi) ?>" placeholder="Cari nama materi..." autocomplete="off">
    </form>

    <?php if (count($materi_list) > 0): ?>
    <ul class="bs3-list">
      <?php foreach ($materi_list as $m):
          $paket   = $m['paket_nama'] ? explode('||', $m['paket_nama']) : [];
          $jml     = (int) $m['jml'];
          $inisial = mb_strtoupper(mb_substr(trim($m['nama']), 0, 2));
          $warna   = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'][(int) $m['id'] % 6];
      ?>
      <li class="bs3-row">
        <a class="bs3-row-link" href="<?= url('/admin/soal.php?materi=' . (int) $m['id']) ?>">
          <span class="bs3-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>

          <span class="bs3-info">
            <span class="bs3-nama"><?= e($m['nama']) ?></span>
            <span class="bs3-paket">
              <?php if ($paket): foreach ($paket as $pn): ?>
                <span class="bs3-chip"><?= e($pn) ?></span>
              <?php endforeach; else: ?>
                <span class="bs3-belum">Belum masuk paket</span>
              <?php endif; ?>
            </span>
          </span>

          <span class="bs3-jumlah<?= $jml === 0 ? ' kosong' : '' ?>">
            <strong><?= $jml ?></strong>
            <span><?= $jml === 0 ? 'belum ada soal' : 'soal' ?></span>
          </span>

          <i class="fas fa-chevron-right bs3-panah" aria-hidden="true"></i>
        </a>

        <?php if ($jml === 0): ?>
        <a class="bs3-hapus" title="Hapus materi"
           href="<?= url('/admin/soal.php?hapus_materi=' . (int) $m['id']) ?>"
           onclick="return confirm('Hapus materi <?= e(addslashes($m['nama'])) ?>?')">
          <i class="fas fa-trash"></i>
        </a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="empty-state">
      <?php if ($cari_materi !== ''): ?>
        <p><strong>Materi tidak ditemukan</strong></p>
        <p>Coba kata kunci lain.</p>
      <?php else: ?>
        <p><strong>Belum ada materi</strong></p>
        <p>Tambahkan materi pertama untuk mulai mengisi soal.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

<?php else: ?>
    <!-- ═════════════ SOAL DALAM SATU MATERI ═════════════ -->

    <a class="bs-kembali" href="<?= url('/admin/soal.php') ?>">← Semua materi</a>

    <div class="card bs-head">
      <div>
        <h2><?= e($materi['nama']) ?></h2>
        <p><?= (int) $ringkas_materi['n'] ?> soal · <?= (int) $ringkas_materi['b'] ?> poin</p>
      </div>
      <button type="button" class="btn-admin-primary" data-buka="bs-modal-soal">
        <i class="fas fa-plus"></i> Tambah soal
      </button>
    </div>

    <form method="GET" class="bs-cari" role="search">
      <input type="hidden" name="materi" value="<?= $materi_id ?>">
      <input type="hidden" name="per" value="<?= $per_page ?>">
      <i class="fas fa-search" aria-hidden="true"></i>
      <input type="search" name="q" value="<?= e($cari_soal) ?>" placeholder="Cari teks pertanyaan..." autocomplete="off">
    </form>

    <div class="card">
      <?php if (count($soal_list) > 0): ?>
      <div style="overflow-x:auto;">
      <table class="bs-tabel">
        <thead>
          <tr>
            <th class="bs-col-no">No</th>
            <th>Pertanyaan</th>
            <th class="bs-col-kunci">Kunci</th>
            <th class="bs-col-bobot">Bobot</th>
            <th class="bs-col-aksi"></th>
          </tr>
        </thead>
        <tbody>
        <?php $no = $offset + 1; foreach ($soal_list as $s): ?>
          <tr>
            <td class="bs-col-no"><?= $no++ ?></td>
            <td class="bs-teks"><?= e(bs_ringkas($s['pertanyaan'])) ?></td>
            <td class="bs-col-kunci">
              <span class="bs-kunci-pill" title="<?= e($s['pilihan_' . strtolower($s['jawaban_benar'])]) ?>">
                <?= e($s['jawaban_benar']) ?>
              </span>
            </td>
            <td class="bs-col-bobot"><?= (int) $s['bobot'] ?></td>
            <td class="bs-col-aksi">
              <a class="bs-icon" title="Edit soal" href="<?= url('/admin/soal.php?materi=' . $materi_id . '&edit=' . (int) $s['id']) ?>">
                <i class="fas fa-pen"></i>
              </a>
              <?php if ((int) $s['dipakai'] > 0): ?>
                <span class="bs-icon bs-icon-off" title="Sudah dipakai ujian, tidak bisa dihapus"><i class="fas fa-lock"></i></span>
              <?php else: ?>
                <a class="bs-icon bs-icon-hapus" title="Hapus soal"
                   href="<?= url('/admin/soal.php?hapus_soal=' . (int) $s['id']) ?>"
                   onclick="return confirm('Hapus soal ini?')">
                  <i class="fas fa-trash"></i>
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <?php if ($total_hasil > 0): ?>
      <div class="bs-pg">
        <div class="bs-pg-info">
          Menampilkan <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_hasil) ?>
          dari <strong><?= $total_hasil ?></strong> soal
        </div>

        <di class="bs-pg-kontrol">
          <form method="GET" class="bs-pg-per">
            <input type="hidden" name="materi" value="<?= $materi_id ?>">
            <?php if ($cari_soal !== ''): ?>
              <input type="hidden" name="q" value="<?= e($cari_soal) ?>">
            <?php endif; ?>
            <label>
              Per halaman
              <select name="per" onchange="this.form.submit()">
                <?php foreach ($per_pilihan as $p): ?>
                  <option value="<?= $p ?>" <?= $p === $per_page ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </form>

          <?php if ($total_halaman > 1): ?>
          <nav class="bs-pg-nav" aria-label="Paginasi soal">
            <?php if ($halaman > 1): ?>
              <a href="<?= $pg_url($halaman - 1) ?>" aria-label="Sebelumnya"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
              <span class="off"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $total_halaman; $p++):
                if ($p !== 1 && $p !== $total_halaman && abs($p - $halaman) > 1) {
                    if ($p === 2 || $p === $total_halaman - 1) echo '<span class="dots">…</span>';
                    continue;
                }
            ?>
              <a href="<?= $pg_url($p) ?>" class="<?= $p === $halaman ? 'aktif' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($halaman < $total_halaman): ?>
              <a href="<?= $pg_url($halaman + 1) ?>" aria-label="Berikutnya"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
              <span class="off"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
          </nav>
          <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="empty-state">
        <?php if ($cari_soal !== ''): ?>
          <p><strong>Soal tidak ditemukan</strong></p>
          <p>Coba kata kunci lain.</p>
        <?php else: ?>
          <p><strong>Belum ada soal di materi ini</strong></p>
          <p>Klik <strong>Tambah soal</strong> di atas untuk mulai mengisi.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- MODAL: FORM SOAL -->
    <div class="modal-overlay" id="bs-modal-soal" aria-hidden="true">
      <div class="modal-box bs-modal-lebar" role="dialog" aria-modal="true" aria-labelledby="bs-soal-judul">
        <form method="POST" class="bs-form" action="<?= url('/admin/soal.php?materi=' . $materi_id) ?>">
          <div class="modal-header">
            <h3 id="bs-soal-judul"><?= $form['id'] > 0 ? 'Edit soal' : 'Tambah soal' ?></h3>
            <button type="button" class="modal-close" data-tutup aria-label="Tutup">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="modal-body">
            <?php if ($form_error): ?>
            <div class="alert alert-error"><?= e($form_error) ?></div>
            <?php endif; ?>

            <?php if ($dipakai_edit > 0): ?>
            <div class="bs-catatan">
              Soal ini sudah dipakai dalam <?= $dipakai_edit ?> jawaban peserta. Perubahan kunci tidak mengubah nilai yang sudah tersimpan.
            </div>
            <?php endif; ?>

            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
            <input type="hidden" name="kategori_id" value="<?= $materi_id ?>">

            <div class="bs-field">
              <label for="bs-pertanyaan">Pertanyaan</label>
              <textarea id="bs-pertanyaan" name="pertanyaan" rows="4" required><?= e($form['pertanyaan']) ?></textarea>
            </div>

            <div class="bs-pilihan">
              <?php foreach (BS_KUNCI as $o): $k = strtolower($o); ?>
              <div class="bs-field">
                <label for="bs-p-<?= $k ?>">Pilihan <?= $o ?></label>
                <input id="bs-p-<?= $k ?>" type="text" name="pilihan_<?= $k ?>"
                       value="<?= e($form['pilihan_' . $k]) ?>" required>
              </div>
              <?php endforeach; ?>
            </div>

            <div class="bs-row2">
              <fieldset class="bs-field bs-fieldset">
                <legend>Kunci jawaban</legend>
                <div class="bs-kunci">
                  <?php foreach (BS_KUNCI as $o): ?>
                  <label class="bs-kunci-opt">
                    <input type="radio" name="jawaban_benar" value="<?= $o ?>"
                           <?= $form['jawaban_benar'] === $o ? 'checked' : '' ?> required>
                    <span><?= $o ?></span>
                  </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>

              <div class="bs-field">
                <label for="bs-bobot">Bobot nilai</label>
                <input id="bs-bobot" type="number" name="bobot" min="1" max="10"
                       value="<?= (int) $form['bobot'] ?>" required>
                <small>Umumnya 2 atau 3 poin.</small>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn-secondary" data-tutup>Batal</button>
            <button type="submit" name="simpan_soal" class="btn-admin-primary">Simpan soal</button>
          </div>
        </form>
      </div>
    </div>

<?php endif; ?>

    <!-- MODAL: FORM MATERI (hanya di daftar) -->
<?php if (!$materi): ?>
    <div class="modal-overlay" id="bs-modal-materi" aria-hidden="true">
      <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="bs-materi-judul">
        <form method="POST" class="bs-form" action="<?= url('/admin/soal.php') ?>">
          <div class="modal-header">
            <h3 id="bs-materi-judul">Tambah materi</h3>
            <button type="button" class="modal-close" data-tutup aria-label="Tutup">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="modal-body">
            <div class="bs-field">
              <label for="bs-nama-materi">Nama materi</label>
              <input id="bs-nama-materi" type="text" name="nama_materi" maxlength="100"
                     placeholder="Contoh: Teknik Radio" required>
              <small>Materi bisa dipakai oleh satu atau lebih paket. Atur di menu Kelola Paket.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-secondary" data-tutup>Batal</button>
            <button type="submit" name="tambah_materi" class="btn-admin-primary">Simpan materi</button>
          </div>
        </form>
      </div>
    </div>
<?php endif; ?>

<script>
(function () {
  function buka(id) {
    var m = document.getElementById(id);
    if (!m) return;
    m.classList.add('show');
    m.setAttribute('aria-hidden', 'false');
    var f = m.querySelector('input:not([type="hidden"]):not([type="radio"]), textarea');
    if (f) f.focus();
  }
  function tutup(m) {
    m.classList.remove('show');
    m.setAttribute('aria-hidden', 'true');
  }

  document.querySelectorAll('[data-buka]').forEach(function (b) {
    b.addEventListener('click', function () { buka(b.dataset.buka); });
  });

  document.querySelectorAll('.modal-overlay').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) tutup(m); });
    m.querySelectorAll('[data-tutup]').forEach(function (b) {
      b.addEventListener('click', function () { tutup(m); });
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.show').forEach(tutup);
    }
  });

  document.querySelectorAll('form.bs-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var b = e.submitter || f.querySelector('button[type="submit"]');
      if (b) {
        b.textContent = 'Menyimpan…';
        b.setAttribute('aria-busy', 'true');
      }
    });
  });

  var awal = <?= json_encode($buka_modal) ?>;
  if (awal) buka(awal);
})();
</script>

<?php include __DIR__ . '/partials/layout-end.php'; ?>