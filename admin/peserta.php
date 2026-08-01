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
            // Ada password baru → hash dulu
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,jenis=?,angkatan=?,password=? WHERE id=?");
            $s->bind_param("sssssi", $nama,$nomor,$jenis,$angkatan,$hashed,$id);
        } else {
            // Tidak ada password baru → jangan ubah password
            $s = $conn->prepare("UPDATE peserta SET nama=?,nomor_peserta=?,jenis=?,angkatan=? WHERE id=?");
            $s->bind_param("ssssi", $nama,$nomor,$jenis,$angkatan,$id);
        }
        if ($s->execute()) {
            $msg = 'Data peserta berhasil diperbarui.';
        } else {
            $error = 'Gagal memperbarui data peserta.';
        }
    } else {
        // Tambah peserta baru → hash password
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $s = $conn->prepare("INSERT INTO peserta (nama,nomor_peserta,jenis,angkatan,password) VALUES (?,?,?,?,?)");
        $s->bind_param("sssss", $nama,$nomor,$jenis,$angkatan,$hashed);
        if ($s->execute()) {
            $msg = 'Peserta berhasil ditambahkan.';
        } else {
            $error = 'Gagal menambahkan peserta. Nomor peserta mungkin sudah digunakan.';
        }
    }
}

// Edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM peserta WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

$show_form = (($_GET['action'] ?? '') === 'tambah' || $edit);
$list = $conn->query("SELECT * FROM peserta ORDER BY jenis, nama");

$semua_peserta = [];
$tmp = $conn->query("SELECT id, nama, nomor_peserta, jenis, angkatan FROM peserta ORDER BY jenis, nama");
while ($row = $tmp->fetch_assoc()) $semua_peserta[] = $row;

$admin_page = 'peserta';
$page_title = 'Kelola Peserta — BBU Tryout';
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
                <label class="form-label">Nomor Peserta <span class="required">*</span>
                  <small>(dipakai untuk login)</small>
                </label>
                <div class="input-check-wrap">
                  <input type="text" name="nomor_peserta" id="field-nomor" class="form-input"
                        placeholder="Contoh: BBU-001" required autocomplete="off">
                  <span id="nomor-status" class="nomor-status"></span>
                </div>
              </div>

              <div>
                <label class="form-label">Program <span class="required">*</span></label>
                <select name="jenis" id="field-jenis" class="form-input">
                  <option value="SOU">SOU — Ship's Officer Unlimited</option>
                  <option value="GMDSS">GMDSS — Global Maritime Distress</option>
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
                        placeholder="Isi password peserta">
                  <button type="button" class="btn-eye" onclick="togglePassword()" title="Tampilkan password">
                    <i class="fas fa-eye" id="eye-icon"></i>
                  </button>
                </div>
                <span class="form-hint">
                  <i class="fas fa-info-circle" style="color:#6b7a99;margin-right:4px;"></i>
                  Saran: nomor peserta atau tanggal lahir (ddmmyyyy)
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
        document.getElementById('field-jenis').value    = p.jenis;
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

      modal.addEventListener('transitionend', function handler() {
        modal.style.display = 'none';
        modal.removeEventListener('transitionend', handler);
      });
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
    </script>

    <!-- TABEL PESERTA -->
    <div class="card">
      <div class="card-header">
        <h3>Daftar peserta</h3>
        <button onclick="bukaModal()" class="btn-admin-primary" style="font-size:13px;padding:8px 14px;">
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
            <td><span class="badge badge-<?= strtolower($p['jenis']) ?>"><?= $p['jenis'] ?></span></td>
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