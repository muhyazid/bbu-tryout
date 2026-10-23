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
    $jml   = (int)($_POST['jumlah_soal'] ?? 50);
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
    SELECT s.*, pp.nama AS paket_nama,
           (SELECT COUNT(DISTINCT so.id)
            FROM soal so
            JOIN paket_kategori pk ON pk.kategori_id = so.kategori_id
            WHERE pk.paket_id = s.paket_id) AS total_soal_paket
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

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2>Sesi Ujian</h2>
        <p>Atur jadwal, durasi, dan mode ujian. Sesi aktif dengan tanggal hari ini akan tampil ke peserta.</p>
      </div>
      <a href="<?= url('/admin/sesi.php?action=tambah') ?>" class="ps-tambah">
        <i class="fas fa-plus"></i> Buat sesi baru
      </a>
    </div>

    <!-- FORM -->
    <?php if ($show_form): ?>
    <section class="pk-panel ss-form" id="ss-form">
      <header class="pk-panel-head">
        <div class="pk-panel-judul">
          <h2><?= $edit ? 'Edit sesi ujian' : 'Buat sesi ujian baru' ?></h2>
          <p>Isi informasi di bawah. Pratinjau di sebelah kanan menunjukkan apa yang akan dilihat peserta.</p>
        </div>
        <a href="<?= url('/admin/sesi.php') ?>" class="ps-tutup">Tutup</a>
      </header>

      <form method="POST" class="ss-form-body">
        <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
        <input type="hidden" name="jumlah_soal" value="<?= (int)($edit['jumlah_soal'] ?? 50) ?>">

        <div class="ss-layout">

          <div class="ss-main">

            <div class="ss-bagian">
              <div class="ss-bagian-head"><span class="ss-nomor">1</span> Informasi sesi</div>

              <div class="ss-field">
                <label class="ss-label" for="ss-nama">Nama sesi</label>
                <input id="ss-nama" type="text" name="nama_sesi" class="ss-input"
                       placeholder="Contoh: CAT Hari 1, SOU Angkatan 2024-I"
                       value="<?= htmlspecialchars($edit['nama_sesi'] ?? '') ?>" required>
              </div>

              <div class="ss-dua">
                <div class="ss-field">
                  <label class="ss-label" for="ss-paket">Program</label>
                  <select id="ss-paket" name="paket_id" class="ss-input">
                    <?php
                    $opt_paket = $conn->query("
                        SELECT pp.id, pp.nama, COUNT(DISTINCT so.id) AS total_soal
                        FROM paket_program pp
                        LEFT JOIN paket_kategori pk ON pk.paket_id = pp.id
                        LEFT JOIN soal so ON so.kategori_id = pk.kategori_id
                        GROUP BY pp.id, pp.nama, pp.urutan
                        ORDER BY pp.urutan
                    ");
                    while ($op = $opt_paket->fetch_assoc()):
                    ?>
                    <option value="<?= (int)$op['id'] ?>" <?= (int)($edit['paket_id'] ?? 0) === (int)$op['id'] ? 'selected' : '' ?>>
                      <?= e($op['nama']) ?> (<?= (int)$op['total_soal'] ?> soal)
                    </option>
                    <?php endwhile; ?>
                  </select>
                </div>

                <div class="ss-field">
                  <label class="ss-label" for="ss-mode">Mode</label>
                  <select id="ss-mode" name="mode" class="ss-input">
                    <option value="latihan" <?= ($edit['mode'] ?? '') === 'latihan' ? 'selected' : '' ?>>Latihan (boleh browsing)</option>
                    <option value="ujian"   <?= ($edit['mode'] ?? '') === 'ujian'   ? 'selected' : '' ?>>Ujian (ketat)</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="ss-bagian">
              <div class="ss-bagian-head"><span class="ss-nomor">2</span> Jadwal</div>

              <div class="ss-tiga">
                <div class="ss-field">
                  <label class="ss-label" for="ss-tgl">Tanggal</label>
                  <input id="ss-tgl" type="date" name="tanggal" class="ss-input"
                         value="<?= $edit['tanggal'] ?? date('Y-m-d') ?>" required>
                </div>
                <div class="ss-field">
                  <label class="ss-label" for="ss-mulai">Jam mulai</label>
                  <input id="ss-mulai" type="time" name="jam_mulai" class="ss-input"
                         value="<?= isset($edit['jam_mulai']) ? substr($edit['jam_mulai'], 0, 5) : '00:00' ?>" required>
                </div>
                <div class="ss-field">
                  <label class="ss-label" for="ss-selesai">Jam selesai</label>
                  <input id="ss-selesai" type="time" name="jam_selesai" class="ss-input"
                         value="<?= isset($edit['jam_selesai']) ? substr($edit['jam_selesai'], 0, 5) : '23:59' ?>" required>
                </div>
              </div>
              <p class="ss-catatan-kecil">Jam 00:00 sampai 23:59 berarti sesi terbuka sepanjang hari.</p>
            </div>

            <div class="ss-bagian">
              <div class="ss-bagian-head"><span class="ss-nomor">3</span> Waktu mengerjakan</div>

              <div class="ss-field ss-field-kecil">
                <label class="ss-label" for="ss-durasi">Durasi (menit)</label>
                <input id="ss-durasi" type="number" name="durasi_menit" class="ss-input"
                       min="5" max="1440" placeholder="60"
                       value="<?= $edit['durasi_menit'] ?? 60 ?>" required>
                <p class="ss-catatan-kecil">Berlaku untuk seluruh soal. Saat waktu habis, jawaban dikirim otomatis.</p>
              </div>
            </div>

          </div>

          <aside class="ss-pratinjau" aria-label="Pratinjau">
            <div class="ss-pratinjau-judul">Pratinjau untuk peserta</div>
            <div class="ss-pratinjau-kartu">
              <div class="ss-pr-status" id="pr-status">Aktif</div>
              <div class="ss-pr-nama" id="pr-nama">Nama sesi</div>
              <dl class="ss-pr-daftar">
                <div><dt>Program</dt><dd id="pr-paket">-</dd></div>
                <div><dt>Tanggal</dt><dd id="pr-tgl">-</dd></div>
                <div><dt>Jam</dt><dd id="pr-jam">-</dd></div>
                <div><dt>Durasi</dt><dd id="pr-durasi">-</dd></div>
                <div><dt>Mode</dt><dd id="pr-mode">-</dd></div>
              </dl>
            </div>
            <p class="ss-pr-catatan">
              Sesi tampil ke peserta hanya jika statusnya <strong>Aktif</strong> dan tanggalnya hari ini.
            </p>
          </aside>

        </div>

        <div class="ss-aksi">
          <a href="<?= url('/admin/sesi.php') ?>" class="ss-btn-batal">Batal</a>
          <button type="submit" class="ss-btn-simpan">
            <i class="fas fa-check"></i> Simpan sesi
          </button>
        </div>
      </form>
    </section>

    <script>
    (function () {
      var $ = function (id) { return document.getElementById(id); };
      function formatTgl(v) {
        if (!v) return '-';
        var d = new Date(v + 'T00:00:00');
        return isNaN(d) ? '-' : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
      }
      function perbarui() {
        $('pr-nama').textContent  = $('ss-nama').value.trim() || 'Nama sesi';
        var pilih = $('ss-paket').options[$('ss-paket').selectedIndex];
        $('pr-paket').textContent = pilih ? pilih.textContent.replace(/\s*\(\d+ soal\)/, '').trim() : '-';
        $('pr-tgl').textContent   = formatTgl($('ss-tgl').value);
        var m = $('ss-mulai').value || '--:--', s = $('ss-selesai').value || '--:--';
        $('pr-jam').textContent   = m + ' – ' + s;
        var dur = parseInt($('ss-durasi').value, 10);
        $('pr-durasi').textContent = isNaN(dur) ? '-' : dur + ' menit';
        $('pr-mode').textContent  = $('ss-mode').value === 'ujian' ? 'Ujian (ketat)' : 'Latihan';
      }
      document.querySelectorAll('#ss-form input, #ss-form select').forEach(function (el) {
        el.addEventListener('input', perbarui);
        el.addEventListener('change', perbarui);
      });
      perbarui();
    })();
    </script>
    <?php endif; ?>

    <!-- DAFTAR SESI -->
    <?php if ($list->num_rows > 0): ?>
    <ul class="ps-list">
      <?php
      $hari_ini = date('Y-m-d');
      while ($s = $list->fetch_assoc()):
          $aktif          = $s['status'] === 'aktif';
          $tampil_peserta = ($aktif && $s['tanggal'] === $hari_ini);
          $tujuan         = $aktif ? 'nonaktif' : 'aktif';
      ?>
      <li class="ps-row">
        <div class="ps-row-link ps-row-statis">
          <a href="#" class="kl-status <?= $aktif ? 'berjalan' : 'ss-nonaktif' ?> ss-toggle"
             title="<?= $aktif ? 'Nonaktifkan sesi' : 'Aktifkan sesi' ?>"
             onclick="konfirmasiToggle(<?= (int)$s['id'] ?>, '<?= $tujuan ?>', '<?= htmlspecialchars(addslashes($s['nama_sesi'])) ?>'); return false;">
            <span class="kl-titik"></span><?= $aktif ? 'Aktif' : 'Nonaktif' ?>
          </a>

          <span class="ps-info">
            <span class="ps-nama">
              <?= htmlspecialchars($s['nama_sesi']) ?>
              <?php if ($tampil_peserta): ?>
              <span class="ss-tampil">Tampil ke peserta</span>
              <?php endif; ?>
            </span>
            <span class="ps-chips">
              <span class="ps-chip ps-chip-paket"><?= e($s['paket_nama']) ?></span>
              <span class="ps-chip ss-chip-<?= $s['mode'] ?>"><?= $s['mode'] === 'ujian' ? 'Ujian' : 'Latihan' ?></span>
            </span>
            <span class="ps-meta">
              <?= date('d M Y', strtotime($s['tanggal'])) ?> · <?= substr($s['jam_mulai'],0,5) ?>–<?= substr($s['jam_selesai'],0,5) ?>
              · <?= (int)$s['durasi_menit'] ?> menit
              · <?= number_format((int)$s['total_soal_paket'], 0, ',', '.') ?> soal tersedia
            </span>
          </span>
        </div>

        <div class="ps-aksi">
          <a class="ps-ikon" title="Edit sesi" href="<?= url('/admin/sesi.php?edit=' . (int)$s['id']) ?>">
            <i class="fas fa-pen"></i>
          </a>
          <a class="ps-ikon ps-ikon-hapus" title="Hapus sesi"
             href="<?= url('/admin/sesi.php?hapus=' . (int)$s['id']) ?>"
             onclick="return confirm('Hapus sesi <?= htmlspecialchars(addslashes($s['nama_sesi'])) ?>?')">
            <i class="fas fa-trash"></i>
          </a>
        </div>
      </li>
      <?php endwhile; ?>
    </ul>
    <?php else: ?>
    <div class="empty-state">
      <p><strong>Belum ada sesi ujian</strong></p>
      <p>Klik <strong>Buat sesi baru</strong> di atas untuk membuat jadwal ujian.</p>
    </div>
    <?php endif; ?>

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