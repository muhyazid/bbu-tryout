<?php
require_once '../config.php';
cekAdmin();

$msg = ''; $error = '';

// Ambil pesan flash dari redirect sebelumnya (kalau ada)
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// HAPUS
if (isset($_GET['hapus'])) {
    $hid = (int)$_GET['hapus'];
    $del = $conn->prepare("DELETE FROM peserta WHERE id=?");
    $del->bind_param("i", $hid);

    if ($del->execute()) {
        $_SESSION['flash_msg'] = 'Peserta berhasil dihapus.';
    } else {
        $_SESSION['flash_msg'] = 'Peserta ini tidak bisa dihapus karena masih memiliki riwayat nilai ujian.';
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
            $s->bind_param("ssisssi", $nama,$nomor,$paket_id,$angkatan,$hashed,$id);
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
         // Kalau password kosong → auto-generate
        if ($pass === '') {
            $pass = generate_password_acak();
        }
        // Tambah peserta baru → hash password UNTUK LOGIN, simpan juga versi asli UNTUK CETAK KARTU
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $s = $conn->prepare("INSERT INTO peserta (nama,nomor_peserta,paket_id,angkatan,password) VALUES (?,?,?,?,?)");
        $s->bind_param("ssiss", $nama,$nomor,$paket_id,$angkatan,$hashed);
        if ($s->execute()) {
            $_SESSION['flash_msg'] = 'Peserta berhasil ditambahkan.';
            $_SESSION['peserta_baru_id'] = $conn->insert_id;
        } else {
            $_SESSION['flash_msg'] = 'Gagal menambahkan peserta. Nomor peserta mungkin sudah digunakan.';
        }
    }
    redirect('/admin/peserta.php');
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM peserta WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);
$list = $conn->query("
    SELECT p.*, pp.nama AS paket_nama
    FROM peserta p
    JOIN paket_program pp ON p.paket_id = pp.id
    ORDER BY pp.urutan, p.nama
");

$semua_peserta = [];
$tmp = $conn->query("
    SELECT p.id, p.nama, p.nomor_peserta, p.paket_id, p.angkatan, pp.nama AS paket_nama
    FROM peserta p
    JOIN paket_program pp ON p.paket_id = pp.id
    ORDER BY pp.urutan, p.nama
");
while ($row = $tmp->fetch_assoc()) $semua_peserta[] = $row;

$admin_page = 'peserta';
$page_title = 'Kelola Peserta';
$topbar_breadcrumb = 'Peserta';
$topbar_title      = 'Kelola Peserta';

include __DIR__ . '/partials/layout-start.php';
?>
    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
                  <label class="form-label">Program <span class="required">*</span></label>
                  <select name="paket_id" id="field-paket" class="form-input">
                    <?php
                    $opt_paket = $conn->query("SELECT id, nama FROM paket_program ORDER BY urutan");
                    while ($op = $opt_paket->fetch_assoc()):
                    ?>
                    <option value="<?= (int)$op['id'] ?>"><?= e($op['nama']) ?></option>
                    <?php endwhile; ?>
                  </select>
              </div>

              <div>
                <label class="form-label">Angkatan</label>
                <input type="text" name="angkatan" id="field-angkatan" class="form-input"
                      placeholder="Contoh: Angkatan 2024-I">
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
                  Saran: nomor peserta atau tanggal lahir (ddmmyyyy)
                </span>
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

    // Klik backdrop = tutup
    document.getElementById('modal-peserta').addEventListener('click', function(e) {
      if (e.target === this) tutupModal();
    });

    // Show/hide password
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

        // Auto-suggest username & password dari nama + nomor absen
    document.getElementById('field-nama').addEventListener('input', function() {
        updateSuggestions();
    });
    document.getElementById('field-absen').addEventListener('input', function() {
        updateSuggestions();
    });

    function updateSuggestions() {
        const nama  = document.getElementById('field-nama').value.trim();
        const absen = document.getElementById('field-absen').value.trim();

        if (!nama || !absen) return;

        const namaDepan   = nama.split(' ')[0];
        const namaGabung  = nama.replace(/\s+/g, ''); // hapus semua spasi
        const absenFormat = absen.padStart(2, '0');

        // Username: nama depan + absen (kalau belum diubah manual)
        const usernameField = document.getElementById('field-nomor');
        if (!usernameField.dataset.diubahManual) {
            usernameField.value = namaDepan + absenFormat;
            // Trigger cek unik yang sudah ada
            usernameField.dispatchEvent(new Event('input', { bubbles: true }));
        }

        // Password: nama lengkap (tanpa spasi) + absen (kalau belum diubah manual)
        const passwordField = document.getElementById('field-password');
        if (!passwordField.dataset.diubahManual) {
            passwordField.value = namaGabung + absenFormat;
        }
    }

    // Tandai kalau kurpel edit manual
    document.getElementById('field-nomor').addEventListener('input', function(e) {
    // Hanya tandai "diubah manual" kalau event ini benar-benar dari
    // ketikan asli pengguna, BUKAN dari dispatchEvent otomatis sistem
    if (e.isTrusted) {
            this.dataset.diubahManual = 'true';
        }
    });
    document.getElementById('field-password').addEventListener('input', function() {
        this.dataset.diubahManual = 'true';
    });
    </script>

    <!-- TABEL PESERTA -->
    <div class="card">
      <div class="card-header">
        <h3>Daftar peserta</h3>
        <button onclick="bukaModal()" class="btn-admin-primary btn-sm">
          <?= admin_icon('plus') ?> Tambah peserta
        </button>
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
            <th style="width:120px;">Aksi</th>
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
            <td><span class="badge badge-paket"><?= e($p['paket_nama']) ?></span></td>
            <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($p['angkatan'] ?: '—') ?></td>
            <td>
              <div class="action-buttons">
                <a onclick="bukaModal(<?= $p['id'] ?>)" 
                  class="btn-icon btn-icon-edit" 
                  data-tooltip="Edit peserta"
                  style="cursor:pointer;">
                  <i class="fas fa-pencil"></i>
                </a>
                <a href="peserta.php?hapus=<?= $p['id'] ?>" 
                  class="btn-icon btn-icon-delete" 
                  data-tooltip="Hapus peserta"
                  onclick="return confirm('Hapus peserta <?= htmlspecialchars(addslashes($p['nama'])) ?>?')">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada peserta</strong></p>
        <p>Gunakan tombol <strong>Tambah peserta</strong> di atas untuk menambahkan data.</p>
      </div>
      <?php endif; ?>
    </div>

    
<?php include __DIR__ . '/partials/layout-end.php'; ?>
