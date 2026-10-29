<?php
require_once '../config.php';
cekAdmin();

$msg = ''; $error = '';

if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// ——— Buat angkatan baru ———
if (isset($_POST['buat_angkatan'])) {
    $nama_angkatan = trim($_POST['nama_angkatan'] ?? '');
    if ($nama_angkatan !== '') {
        $s = $conn->prepare("INSERT INTO angkatan (nama) VALUES (?)");
        $s->bind_param("s", $nama_angkatan);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = "Angkatan \"$nama_angkatan\" berhasil dibuat.";
        } else {
            $_SESSION['flash_msg'] = 'Gagal membuat angkatan. Mungkin nama sudah dipakai.';
        }
    }
    redirect('/admin/peserta.php');
}

// HAPUS
if (isset($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    $angkatan_terkait = $conn->query("SELECT angkatan FROM peserta WHERE id=$hid")->fetch_assoc();
    $del = $conn->prepare("DELETE FROM peserta WHERE id=?");
    $del->bind_param("i", $hid);

    if ($del->execute()) {
        $_SESSION['flash_msg'] = 'Peserta berhasil dihapus.';
    } else {
        $_SESSION['flash_msg'] = 'Peserta ini tidak bisa dihapus karena masih memiliki riwayat nilai ujian.';
    }
    redirect('/admin/peserta.php' . ($angkatan_terkait ? '?angkatan=' . urlencode($angkatan_terkait['angkatan']) : ''));
}

// ——— Hapus angkatan (hanya kalau kosong) ———
if (isset($_GET['hapus_angkatan'])) {
    $nama_angkatan_hapus = urldecode($_GET['hapus_angkatan']);

    $cek_jumlah = $conn->prepare("SELECT COUNT(*) as n FROM peserta WHERE angkatan = ?");
    $cek_jumlah->bind_param("s", $nama_angkatan_hapus);
    $cek_jumlah->execute();
    $jumlah_peserta = $cek_jumlah->get_result()->fetch_assoc()['n'];

    if ($jumlah_peserta > 0) {
        $_SESSION['flash_msg'] = "Tidak bisa menghapus \"$nama_angkatan_hapus\" karena masih ada $jumlah_peserta peserta di dalamnya. Pindahkan atau hapus peserta tersebut terlebih dahulu.";
    } else {
        $del = $conn->prepare("DELETE FROM angkatan WHERE nama = ?");
        $del->bind_param("s", $nama_angkatan_hapus);
        if ($del->execute()) {
            $_SESSION['flash_msg'] = "Angkatan \"$nama_angkatan_hapus\" berhasil dihapus.";
        } else {
            $_SESSION['flash_msg'] = 'Gagal menghapus angkatan.';
        }
    }
    redirect('/admin/peserta.php');
}

// SIMPAN (tambah / edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama']);
    $nomor    = trim($_POST['nomor_peserta']);
    $paket_id = (int)($_POST['paket_id'] ?? 0);
    $angkatan = trim($_POST['angkatan']);
    $pass     = trim($_POST['password']);

    if ($id > 0) {
        if ($pass) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,paket_id=?,angkatan=?,password=? WHERE id=?");
            $s->bind_param("ssissi", $nama,$nomor,$paket_id,$angkatan,$hashed,$id);
        } else {
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,paket_id=?,angkatan=? WHERE id=?");
            $s->bind_param("ssisi", $nama,$nomor,$paket_id,$angkatan,$id);
        }
        if ($s->execute()) {
            $_SESSION['flash_msg'] = 'Data peserta berhasil diperbarui.';
        } else {
            $_SESSION['flash_msg'] = 'Gagal memperbarui data peserta.';
        }
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $s = $conn->prepare("INSERT INTO peserta (nama,nomor_peserta,paket_id,angkatan,password) VALUES (?,?,?,?,?)");
        $s->bind_param("ssiss", $nama,$nomor,$paket_id,$angkatan,$hashed);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = 'Peserta berhasil ditambahkan.';
        } else {
            $_SESSION['flash_msg'] = 'Gagal menambahkan peserta. Nomor peserta mungkin sudah digunakan.';
        }
    }
    redirect('/admin/peserta.php' . ($angkatan ? '?angkatan=' . urlencode($angkatan) : ''));
}

// ── DAFTAR ANGKATAN (LEVEL 1) ────────────────────────────────
// Sumber utama: tabel angkatan (termasuk yang masih kosong 0 peserta)
$daftar_angkatan = [];
$res_ang = $conn->query("
    SELECT a.nama AS angkatan, COUNT(p.id) as jumlah
    FROM angkatan a
    LEFT JOIN peserta p ON p.angkatan = a.nama
    GROUP BY a.id, a.nama
    ORDER BY a.created_at DESC
");
while ($row = $res_ang->fetch_assoc()) {
    $daftar_angkatan[] = $row;
}

// Peserta tanpa angkatan (edge case, tampilkan terpisah)
$tanpa_angkatan = $conn->query("SELECT COUNT(*) as n FROM peserta WHERE angkatan IS NULL OR angkatan = ''")->fetch_assoc()['n'];

// ── ANGKATAN YANG SEDANG DIBUKA (LEVEL 2) ────────────────────
$angkatan_aktif = $_GET['angkatan'] ?? null;

$list = null;
$semua_peserta = [];
if ($angkatan_aktif !== null) {
    if ($angkatan_aktif === '__tanpa_angkatan__') {
        $list = $conn->query("
            SELECT p.*, pp.nama AS paket_nama
            FROM peserta p
            JOIN paket_program pp ON p.paket_id = pp.id
            WHERE p.angkatan IS NULL OR p.angkatan = ''
            ORDER BY p.nama
        ");
        $tmp = $conn->query("
            SELECT p.id, p.nama, p.nomor_peserta, p.paket_id, p.angkatan
            FROM peserta p
            WHERE p.angkatan IS NULL OR p.angkatan = ''
            ORDER BY p.nama
        ");
    } else {
        $st = $conn->prepare("
            SELECT p.*, pp.nama AS paket_nama
            FROM peserta p
            JOIN paket_program pp ON p.paket_id = pp.id
            WHERE p.angkatan = ?
            ORDER BY p.nama
        ");
        $st->bind_param("s", $angkatan_aktif);
        $st->execute();
        $list = $st->get_result();

        $st2 = $conn->prepare("SELECT id, nama, nomor_peserta, paket_id, angkatan FROM peserta WHERE angkatan = ? ORDER BY nama");
        $st2->bind_param("s", $angkatan_aktif);
        $st2->execute();
        $tmp = $st2->get_result();
    }
    while ($row = $tmp->fetch_assoc()) $semua_peserta[] = $row;
}

// Daftar paket untuk dropdown form
$daftar_paket = [];
$res_paket = $conn->query("SELECT id, nama FROM paket_program ORDER BY urutan");
while ($row = $res_paket->fetch_assoc()) $daftar_paket[] = $row;

$admin_page = 'peserta';
$page_title = 'Kelola Peserta';
$topbar_breadcrumb = 'Peserta';
$topbar_title      = $angkatan_aktif !== null
    ? ($angkatan_aktif === '__tanpa_angkatan__' ? 'Tanpa Angkatan' : $angkatan_aktif)
    : 'Kelola Peserta';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($angkatan_aktif === null): ?>
    <!-- ═══════════════ LEVEL 1: DAFTAR ANGKATAN ═══════════════ -->

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2>Kelola Peserta</h2>
        <p>Peserta dikelompokkan per angkatan. Pilih angkatan untuk melihat dan mengelola pesertanya.</p>
      </div>
    </div>

    <form method="POST" class="ps-buat" autocomplete="off">
      <div class="ps-buat-input">
        <i class="fas fa-layer-group" aria-hidden="true"></i>
        <input type="text" name="nama_angkatan" placeholder="Nama angkatan baru, contoh: Angkatan 2026-III" required>
      </div>
      <button type="submit" name="buat_angkatan" class="btn-admin-primary pk-btn">
        <i class="fas fa-plus"></i> Buat angkatan
      </button>
    </form>

    <?php if (count($daftar_angkatan) > 0 || $tanpa_angkatan > 0): ?>
    <ul class="ps-list">
      <?php foreach ($daftar_angkatan as $ang):
          $jml     = (int) $ang['jumlah'];
          $inisial = mb_strtoupper(mb_substr(trim($ang['angkatan']), 0, 2));
          $warna   = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'][crc32($ang['angkatan']) % 6];
      ?>
      <li class="ps-row">
        <a class="ps-row-link" href="<?= url('/admin/peserta.php?angkatan=' . urlencode($ang['angkatan'])) ?>">
          <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
          <span class="ps-info">
            <span class="ps-nama"><?= e($ang['angkatan']) ?></span>
            <span class="ps-meta"><?= $jml ?> peserta</span>
          </span>
          <i class="fas fa-chevron-right ps-panah" aria-hidden="true"></i>
        </a>
        <?php if ($jml === 0): ?>
        <a class="ps-hapus" title="Hapus angkatan"
           href="<?= url('/admin/peserta.php?hapus_angkatan=' . urlencode($ang['angkatan'])) ?>"
           onclick="return confirm('Hapus angkatan <?= e(addslashes($ang['angkatan'])) ?>?')">
          <i class="fas fa-trash"></i>
        </a>
        <?php else: ?>
        <span class="ps-hapus ps-hapus-off" title="Kosongkan peserta terlebih dahulu">
          <i class="fas fa-lock"></i>
        </span>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>

      <?php if ($tanpa_angkatan > 0): ?>
      <li class="ps-row">
        <a class="ps-row-link" href="<?= url('/admin/peserta.php?angkatan=__tanpa_angkatan__') ?>">
          <span class="pk-avatar" style="--warna: #94A3B8">?</span>
          <span class="ps-info">
            <span class="ps-nama">Tanpa angkatan</span>
            <span class="ps-meta"><?= (int) $tanpa_angkatan ?> peserta</span>
          </span>
          <i class="fas fa-chevron-right ps-panah" aria-hidden="true"></i>
        </a>
      </li>
      <?php endif; ?>
    </ul>
    <?php else: ?>
    <div class="empty-state">
      <p><strong>Belum ada angkatan</strong></p>
      <p>Buat angkatan pertama di atas, lalu tambahkan pesertanya.</p>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- ═══════════════ LEVEL 2: PESERTA DALAM ANGKATAN ═══════════════ -->

    <a class="ps-kembali" href="<?= url('/admin/peserta.php') ?>">← Semua angkatan</a>

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2><?= $angkatan_aktif === '__tanpa_angkatan__' ? 'Tanpa angkatan' : e($angkatan_aktif) ?></h2>
        <p><?= count($semua_peserta) ?> peserta terdaftar</p>
      </div>
      <button type="button" class="ps-tambah" onclick="bukaModal()">
        <i class="fas fa-plus"></i> Tambah peserta
      </button>
    </div>

    <!-- MODAL PESERTA -->
    <div id="modal-peserta" class="modal-overlay">
      <div class="modal-box">

        <div class="modal-header">
          <h3 id="modal-judul">Tambah peserta baru</h3>
          <button onclick="tutupModal()" class="modal-close" title="Tutup">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <div class="modal-body">
          <form method="POST" id="form-peserta">
            <input type="hidden" name="id" id="field-id" value="0">

            <div class="form-grid">

              <div>
                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input type="text" name="nama" id="field-nama" class="form-input"
                      placeholder="Contoh: Budi Santoso" required>
              </div>

              <div>
                <label class="form-label">Nomor Absen <span class="required">*</span>
                  <small>(untuk generate username & password otomatis)</small>
                </label>
                <input type="text" id="field-absen" class="form-input"
                      placeholder="Contoh: 05" autocomplete="off">
              </div>

              <div>
                <label class="form-label">Username / Nomor Peserta <span class="required">*</span>
                  <small>(dipakai untuk login, bisa diedit manual)</small>
                </label>
                <div class="input-check-wrap">
                  <input type="text" name="nomor_peserta" id="field-nomor" class="form-input"
                        placeholder="Otomatis dari nama + absen" required autocomplete="off">
                  <span id="nomor-status" class="nomor-status"></span>
                </div>
              </div>

              <div>
                <label class="form-label">Program / Paket <span class="required">*</span></label>
                <select name="paket_id" id="field-paket" class="form-input">
                  <?php foreach ($daftar_paket as $pk): ?>
                  <option value="<?= (int)$pk['id'] ?>"><?= e($pk['nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="form-label">Angkatan</label>
                <input type="text" name="angkatan" id="field-angkatan" class="form-input"
                      placeholder="Contoh: Angkatan 2024-I"
                      value="<?= $angkatan_aktif !== '__tanpa_angkatan__' ? e($angkatan_aktif) : '' ?>">
              </div>

              <div class="form-grid-full">
                <label class="form-label">
                  Password <span class="required" id="pw-star">*</span>
                  <small id="label-password-hint"></small>
                </label>
                <div class="input-eye-wrap">
                  <input type="password" name="password" id="field-password" class="form-input"
                        placeholder="Otomatis dari nama lengkap + absen">
                  <button type="button" class="btn-eye" onclick="togglePassword()" title="Tampilkan password">
                    <i class="fas fa-eye" id="eye-icon"></i>
                  </button>
                </div>
                <span class="form-hint">
                  <i class="fas fa-info-circle" style="color:#6b7a99;margin-right:4px;"></i>
                  Otomatis: Nama Lengkap + Nomor Absen (contoh: BudiSantoso05). Bisa diedit manual.
                </span>
              </div>

            </div>

            <div class="modal-footer">
              <button type="submit" class="btn-admin-primary">
                <i class="fas fa-save" style="font-size:13px;"></i> Simpan
              </button>
              <button type="button" onclick="tutupModal()" class="btn-secondary">
                Batal
              </button>
            </div>

          </form>
        </div>

      </div>
    </div>

    <script>
    const dataPeserta = <?= json_encode($semua_peserta) ?>;
    let editId = null;

    function bukaModal(id = null) {
      const modal  = document.getElementById('modal-peserta');
      modal.style.display = 'flex';

      requestAnimationFrame(() => {
        requestAnimationFrame(() => modal.classList.add('aktif'));
      });

      const judul  = document.getElementById('modal-judul');
      const hint   = document.getElementById('label-password-hint');
      const pwStar = document.getElementById('pw-star');
      const pw     = document.getElementById('field-password');

      document.getElementById('form-peserta').reset();
      document.getElementById('nomor-status').textContent = '';
      document.getElementById('nomor-status').className   = 'nomor-status';
      resetEye();

      // Reset flag auto-suggest supaya username & password
      // bisa di-generate ulang dari nol setiap modal dibuka
      delete document.getElementById('field-nomor').dataset.diubahManual;
      delete document.getElementById('field-password').dataset.diubahManual;

      if (id) {
        editId = id;
        const p = dataPeserta.find(x => x.id == id);
        judul.textContent = 'Edit peserta';
        hint.textContent  = '(kosongkan jika tidak diubah)';
        pwStar.style.display = 'none';
        pw.required       = false;
        pw.placeholder    = 'Kosongkan jika tidak diubah';
        document.getElementById('field-id').value       = p.id;
        document.getElementById('field-nama').value     = p.nama;
        document.getElementById('field-nomor').value    = p.nomor_peserta;
        document.getElementById('field-paket').value    = p.paket_id;
        document.getElementById('field-angkatan').value = p.angkatan;
        // Tandai supaya auto-suggest tidak menimpa saat mode edit
        document.getElementById('field-nomor').dataset.diubahManual = 'true';
      } else {
        editId = null;
        judul.textContent = 'Tambah peserta baru';
        hint.textContent  = '';
        pwStar.style.display = 'inline';
        pw.required       = true;
        pw.placeholder    = 'Isi password peserta';
        document.getElementById('field-id').value = '0';
      }

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function tutupModal() {
      const modal = document.getElementById('modal-peserta');
      modal.classList.remove('aktif');
      modal.style.display = 'none';
      document.body.style.overflow = '';
      editId = null;
    }

    document.getElementById('modal-peserta').addEventListener('click', function(e) {
      if (e.target === this) tutupModal();
    });

    function togglePassword() {
      const input = document.getElementById('field-password');
      const icon  = document.getElementById('eye-icon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
      }
    }
    function resetEye() {
      document.getElementById('field-password').type = 'password';
      document.getElementById('eye-icon').className  = 'fas fa-eye';
    }

    // Auto-suggest username & password dari nama + nomor absen
    document.getElementById('field-nama').addEventListener('input', updateSuggestions);
    document.getElementById('field-absen').addEventListener('input', updateSuggestions);

    function updateSuggestions() {
        const nama  = document.getElementById('field-nama').value.trim();
        const absen = document.getElementById('field-absen').value.trim();

        if (!nama || !absen) return;

        const namaDepan   = nama.split(' ')[0];
        const namaGabung  = nama.replace(/\s+/g, '');
        const absenFormat = absen.padStart(2, '0');

        const usernameField = document.getElementById('field-nomor');
        if (!usernameField.dataset.diubahManual) {
            usernameField.value = namaDepan + absenFormat;
            usernameField.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const passwordField = document.getElementById('field-password');
        if (!passwordField.dataset.diubahManual) {
            passwordField.value = namaGabung + absenFormat;
        }
    }

    document.getElementById('field-nomor').addEventListener('input', function(e) {
        if (e.isTrusted) {
            this.dataset.diubahManual = 'true';
        }
    });
    document.getElementById('field-password').addEventListener('input', function(e) {
        if (e.isTrusted) {
            this.dataset.diubahManual = 'true';
        }
    });

    // Cek nomor peserta unik (real-time)
    let checkTimer = null;
    document.getElementById('field-nomor').addEventListener('input', function() {
      const val    = this.value.trim();
      const status = document.getElementById('nomor-status');

      clearTimeout(checkTimer);
      if (!val) { status.textContent = ''; status.className = 'nomor-status'; return; }

      checkTimer = setTimeout(() => {
        const sudahAda = dataPeserta.some(p =>
          p.nomor_peserta.toLowerCase() === val.toLowerCase() && p.id != editId
        );
        if (sudahAda) {
          status.textContent = '✕ Sudah dipakai';
          status.className   = 'nomor-status nomor-taken';
        } else {
          status.textContent = '✓ Tersedia';
          status.className   = 'nomor-status nomor-ok';
        }
      }, 300);
    });
    </script>

    <?php if ($list && $list->num_rows > 0): ?>
    <ul class="ps-list">
      <?php while ($p = $list->fetch_assoc()):
          $inisial = mb_strtoupper(mb_substr(trim($p['nama']), 0, 2));
          $warna   = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'][(int) $p['id'] % 6];
      ?>
      <li class="ps-row">
        <div class="ps-row-link ps-row-statis">
          <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
          <span class="ps-info">
            <span class="ps-nama"><?= e($p['nama']) ?></span>
            <span class="ps-chips">
              <span class="ps-chip ps-chip-nomor"><?= e($p['nomor_peserta']) ?></span>
              <span class="ps-chip ps-chip-paket"><?= e($p['paket_nama']) ?></span>
            </span>
          </span>
        </div>
        <div class="ps-aksi">
          <button type="button" class="ps-ikon" title="Edit peserta" onclick="bukaModal(<?= (int) $p['id'] ?>)">
            <i class="fas fa-pen"></i>
          </button>
          <a class="ps-ikon ps-ikon-hapus" title="Hapus peserta"
             href="<?= url('/admin/peserta.php?hapus=' . (int) $p['id']) ?>"
             onclick="return confirm('Hapus peserta <?= e(addslashes($p['nama'])) ?>?')">
            <i class="fas fa-trash"></i>
          </a>
        </div>
      </li>
      <?php endwhile; ?>
    </ul>
    <?php else: ?>
    <div class="empty-state">
      <p><strong>Belum ada peserta di angkatan ini</strong></p>
      <p>Klik <strong>Tambah peserta</strong> di atas untuk mulai mengisi.</p>
    </div>
    <?php endif; ?>

    <?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>