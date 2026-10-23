<?php
require_once dirname(__DIR__) . '/config.php';
cekAdmin();

// ── SIMPAN MATERI PER PAKET ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_paket'])) {
    $paket_id = (int) ($_POST['paket_id'] ?? 0);

    $cek_paket = $conn->prepare("SELECT nama FROM paket_program WHERE id = ?");
    $cek_paket->bind_param("i", $paket_id);
    $cek_paket->execute();
    $paket_row = $cek_paket->get_result()->fetch_assoc();

    if (!$paket_row) {
        $_SESSION['flash_tipe'] = 'error';
        $_SESSION['flash_msg']  = 'Paket tidak ditemukan.';
        redirect('/admin/paket.php');
    }

    // Hanya terima ID materi yang benar-benar ada di Bank Soal
    $valid = [];
    $res_valid = $conn->query("SELECT id FROM kategori");
    while ($v = $res_valid->fetch_assoc()) {
        $valid[(int) $v['id']] = true;
    }

    $pilih = [];
    foreach (($_POST['kategori'] ?? []) as $k) {
        $k = (int) $k;
        if (isset($valid[$k])) {
            $pilih[$k] = true;
        }
    }
    $pilih = array_keys($pilih);

    $conn->begin_transaction();
    try {
        $del = $conn->prepare("DELETE FROM paket_kategori WHERE paket_id = ?");
        $del->bind_param("i", $paket_id);
        if (!$del->execute()) {
            throw new RuntimeException('Gagal menghapus mapping lama.');
        }

        if (!empty($pilih)) {
            $ins = $conn->prepare("INSERT INTO paket_kategori (paket_id, kategori_id) VALUES (?, ?)");
            foreach ($pilih as $kid) {
                $ins->bind_param("ii", $paket_id, $kid);
                if (!$ins->execute()) {
                    throw new RuntimeException('Gagal menyimpan materi ' . $kid . '.');
                }
            }
        }

        $conn->commit();
        $_SESSION['flash_tipe'] = 'success';
        $_SESSION['flash_msg']  = 'Materi paket ' . $paket_row['nama'] . ' berhasil disimpan (' . count($pilih) . ' materi).';
    } catch (Throwable $ex) {
        $conn->rollback();
        error_log('Gagal simpan paket ' . $paket_id . ': ' . $ex->getMessage());
        $_SESSION['flash_tipe'] = 'error';
        $_SESSION['flash_msg']  = 'Perubahan gagal disimpan dan tidak ada yang berubah. Silakan coba lagi.';
    }

    redirect('/admin/paket.php?paket=' . $paket_id);
}

// ── FLASH MESSAGE ─────────────────────────────────────────
$msg        = $_SESSION['flash_msg'] ?? '';
$flash_tipe = $_SESSION['flash_tipe'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipe']);

// ── DATA ──────────────────────────────────────────────────
$paket_list = [];
$res = $conn->query("
    SELECT pp.id, pp.nama, pp.urutan,
           COUNT(pk.id) AS jml_materi
    FROM paket_program pp
    LEFT JOIN paket_kategori pk ON pk.paket_id = pp.id
    GROUP BY pp.id, pp.nama, pp.urutan
    ORDER BY pp.urutan
");
if ($res === false) {
    error_log('Query daftar paket gagal: ' . $conn->error);
} else {
    while ($row = $res->fetch_assoc()) {
        $paket_list[] = $row;
    }
}

$semua_kat = [];
$res = $conn->query("
    SELECT k.id, k.nama, COUNT(s.id) AS jml_soal
    FROM kategori k
    LEFT JOIN soal s ON s.kategori_id = k.id
    GROUP BY k.id, k.nama
    ORDER BY k.nama
");
while ($row = $res->fetch_assoc()) {
    $semua_kat[] = $row;
}

$map_paket = [];
$res = $conn->query("SELECT paket_id, kategori_id FROM paket_kategori");
while ($row = $res->fetch_assoc()) {
    $map_paket[(int) $row['paket_id']][(int) $row['kategori_id']] = true;
}

$paket_aktif_id = (int) ($_GET['paket'] ?? 0);
if (!$paket_aktif_id && !empty($paket_list)) {
    $paket_aktif_id = (int) $paket_list[0]['id'];
}

$paket_aktif = null;
foreach ($paket_list as $p) {
    if ((int) $p['id'] === $paket_aktif_id) {
        $paket_aktif = $p;
        break;
    }
}
if (!$paket_aktif && !empty($paket_list)) {
    $paket_aktif    = $paket_list[0];
    $paket_aktif_id = (int) $paket_aktif['id'];
}

$terpilih = $map_paket[$paket_aktif_id] ?? [];

$stat_materi = 0;
$stat_soal   = 0;
foreach ($semua_kat as $k) {
    if (isset($terpilih[(int) $k['id']])) {
        $stat_materi++;
        $stat_soal += (int) $k['jml_soal'];
    }
}

$admin_page        = 'paket';
$page_title        = 'Kelola Paket — BBU Tryout';
$topbar_breadcrumb = 'Kelola Paket';
$topbar_title      = 'Kelola Paket Program';

include __DIR__ . '/partials/layout-start.php';
?>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $flash_tipe === 'error' ? 'error' : 'success' ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($paket_list)): ?>
    <div class="empty-state">
      <p><strong>Belum ada paket program</strong></p>
      <p>Data paket program belum tersedia di database.</p>
    </div>
    <?php else: ?>

    <div class="pk-hero">
      <div class="pk-hero-teks">
        <h2>Kelola Paket</h2>
        <p>Tentukan materi yang termasuk tiap paket. Peserta mengerjakan semua soal dari materi di paketnya.</p>
      </div>
    </div>

    <nav class="pk-tabs" aria-label="Pilih paket">
      <?php foreach ($paket_list as $p):
          $aktif   = (int) $p['id'] === $paket_aktif_id;
          $inisial = mb_strtoupper(mb_substr(trim($p['nama']), 0, 2));
          $warna   = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'][(int) $p['id'] % 6];
      ?>
      <a href="<?= url('/admin/paket.php?paket=' . (int) $p['id']) ?>"
         class="pk-tab<?= $aktif ? ' aktif' : '' ?>"
         data-pindah
         <?= $aktif ? 'aria-current="page"' : '' ?>>
        <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
        <span class="pk-tab-teks">
          <span class="pk-tab-nama"><?= e($p['nama']) ?></span>
          <span class="pk-tab-info"><?= (int) $p['jml_materi'] ?> materi</span>
        </span>
      </a>
      <?php endforeach; ?>
    </nav>

    <section class="pk-panel" id="paket-card" data-nama="<?= e($paket_aktif['nama']) ?>">

      <header class="pk-panel-head">
        <div class="pk-panel-judul">
          <h2><?= e($paket_aktif['nama']) ?></h2>
          <p>Materi yang termasuk dalam paket ini</p>
        </div>
        <div class="pk-aksi">
          <button type="button" class="btn-admin-primary pk-btn" id="btn-edit">
            <i class="fas fa-pen"></i> Ubah materi
          </button>
          <button type="button" class="btn-secondary pk-btn" id="btn-batal" hidden>Batal</button>
          <button type="button" class="btn-admin-primary pk-btn" id="btn-simpan" hidden>Simpan perubahan</button>
        </div>
      </header>

      <div class="pk-ringkas">
        <span class="pk-ringkas-item"><strong id="stat-materi"><?= $stat_materi ?></strong> materi</span>
        <span class="pk-ringkas-item"><strong id="stat-soal"><?= number_format($stat_soal, 0, ',', '.') ?></strong> soal</span>
      </div>

      <div class="pk-warning" id="paket-warning" hidden>
        Perubahan materi berlaku untuk seluruh peserta di paket ini. Peserta yang sudah mulai ujian tetap memakai susunan soal saat mereka mulai.
      </div>

      <?php if (!empty($semua_kat)): ?>

      <div class="pk-toolbar">
        <div class="pk-cari">
          <i class="fas fa-search" aria-hidden="true"></i>
          <input type="search" id="paket-cari" placeholder="Cari nama materi..." autocomplete="off">
        </div>
      </div>

      <form method="POST" id="form-paket">
        <input type="hidden" name="paket_id" value="<?= $paket_aktif_id ?>">

        <ul class="pk-list">
          <?php foreach ($semua_kat as $k):
              $kid     = (int) $k['id'];
              $ada     = isset($terpilih[$kid]);
              $inisial = mb_strtoupper(mb_substr(trim($k['nama']), 0, 2));
              $warna   = ['#2563EB', '#059669', '#D97706', '#7C3AED', '#0891B2', '#DB2777'][$kid % 6];
          ?>
          <li class="pk-row paket-row<?= $ada ? ' terpilih' : '' ?>" data-cari="<?= e(mb_strtolower($k['nama'])) ?>">
            <label class="pk-row-label">
              <input type="checkbox" class="pk-check" name="kategori[]" value="<?= $kid ?>"
                     data-nama="<?= e($k['nama']) ?>"
                     data-soal="<?= (int) $k['jml_soal'] ?>"
                     <?= $ada ? 'checked' : '' ?> disabled>
              <span class="pk-avatar" style="--warna: <?= $warna ?>"><?= e($inisial) ?></span>
              <span class="pk-row-info">
                <span class="pk-row-nama"><?= e($k['nama']) ?></span>
                <span class="pk-row-soal"><?= (int) $k['jml_soal'] ?> soal</span>
              </span>
              <span class="pk-row-tag">Termasuk</span>
            </label>
          </li>
          <?php endforeach; ?>
        </ul>

        <div class="pk-kosong" id="paket-kosong" hidden>
          Paket ini belum memiliki materi. Klik "Ubah materi" untuk memilih.
        </div>
      </form>

      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada materi di Bank Soal</strong></p>
        <p>Tambahkan materi terlebih dahulu di menu <a href="<?= url('/admin/soal.php') ?>" class="btn-link">Bank Soal</a>.</p>
      </div>
      <?php endif; ?>

    </section>

    <!-- MODAL KONFIRMASI -->
    <div class="modal-overlay" id="modal-konfirmasi" role="dialog" aria-modal="true" aria-labelledby="konf-judul">
      <div class="modal-box">
        <div class="modal-header">
          <h3 id="konf-judul">Simpan perubahan materi?</h3>
          <button type="button" class="modal-close" data-tutup aria-label="Tutup">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p class="konf-intro" id="konf-intro"></p>

          <div class="konf-blok" id="konf-tambah-blok" hidden>
            <div class="konf-judul tambah">Ditambahkan <span id="konf-tambah-n"></span></div>
            <ul id="konf-tambah"></ul>
          </div>

          <div class="konf-blok" id="konf-hapus-blok" hidden>
            <div class="konf-judul hapus">Dihapus <span id="konf-hapus-n"></span></div>
            <ul id="konf-hapus"></ul>
          </div>

          <p class="konf-error" id="konf-error" hidden></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" data-tutup>Batal</button>
          <button type="submit" form="form-paket" name="simpan_paket" class="btn-admin-primary" id="konf-ya">
            Ya, simpan perubahan
          </button>
        </div>
      </div>
    </div>

    <script>
    (function () {
      const card      = document.getElementById('paket-card');
      const form      = document.getElementById('form-paket');
      if (!card || !form) return;

      const rows      = Array.from(form.querySelectorAll('input[type="checkbox"]'));
      const btnEdit   = document.getElementById('btn-edit');
      const btnBatal  = document.getElementById('btn-batal');
      const btnSimpan = document.getElementById('btn-simpan');
      const warn      = document.getElementById('paket-warning');
      const cari      = document.getElementById('paket-cari');
      const kosong    = document.getElementById('paket-kosong');
      const sMateri   = document.getElementById('stat-materi');
      const sSoal     = document.getElementById('stat-soal');
      const modal     = document.getElementById('modal-konfirmasi');
      const konfYa    = document.getElementById('konf-ya');
      const konfErr   = document.getElementById('konf-error');
      const paketNama = card.dataset.nama;

      let editing = false;
      let sudahKirim = false;

      // Kondisi awal dari server, untuk menghitung selisih
      const awal = {};
      rows.forEach(cb => { awal[cb.value] = cb.defaultChecked; });

      function hitung() {
        let n = 0, s = 0;
        rows.forEach(cb => {
          cb.closest('.paket-row').classList.toggle('terpilih', cb.checked);
          if (cb.checked) {
            n++;
            s += parseInt(cb.dataset.soal, 10) || 0;
          }
        });
        sMateri.textContent = n;
        sSoal.textContent = s.toLocaleString('id-ID');
        kosong.hidden = editing || n > 0;
        return n;
      }

      function berubah() {
        return rows.some(cb => cb.checked !== awal[cb.value]);
      }

      function setEdit(on) {
        editing = on;
        card.classList.toggle('is-editing', on);
        rows.forEach(cb => { cb.disabled = !on; });
        btnEdit.hidden   = on;
        btnBatal.hidden  = !on;
        btnSimpan.hidden = !on;
        warn.hidden      = !on;
        if (!on) cari.value = '';
        rows.forEach(cb => { cb.closest('.paket-row').hidden = false; });
        hitung();
      }

      function isiList(idUl, daftar) {
        const ul = document.getElementById(idUl);
        ul.textContent = '';
        daftar.forEach(nama => {
          const li = document.createElement('li');
          li.textContent = nama;
          ul.appendChild(li);
        });
      }

      function bukaKonfirmasi() {
        const tambah = [], hapus = [];
        rows.forEach(cb => {
          const nama = cb.dataset.nama;
          if (cb.checked && !awal[cb.value]) tambah.push(nama);
          if (!cb.checked && awal[cb.value]) hapus.push(nama);
        });

        const n = hitung();
        document.getElementById('konf-intro').textContent =
          'Paket ' + paketNama + ' akan memakai ' + n + ' materi.';

        isiList('konf-tambah', tambah);
        document.getElementById('konf-tambah-n').textContent = '(' + tambah.length + ')';
        document.getElementById('konf-tambah-blok').hidden = tambah.length === 0;

        isiList('konf-hapus', hapus);
        document.getElementById('konf-hapus-n').textContent = '(' + hapus.length + ')';
        document.getElementById('konf-hapus-blok').hidden = hapus.length === 0;

        if (n === 0) {
          konfErr.textContent = 'Paket harus memiliki minimal satu materi.';
          konfErr.hidden = false;
          konfYa.disabled = true;
        } else if (!berubah()) {
          konfErr.textContent = 'Tidak ada perubahan yang perlu disimpan.';
          konfErr.hidden = false;
          konfYa.disabled = true;
        } else {
          konfErr.hidden = true;
          konfYa.disabled = false;
        }

        modal.classList.add('show');
        modal.querySelector('.btn-secondary').focus();
      }

      function tutupKonfirmasi() {
        modal.classList.remove('show');
      }

      // ── Event ──
      btnEdit.addEventListener('click', () => setEdit(true));

      btnBatal.addEventListener('click', () => {
        rows.forEach(cb => { cb.checked = cb.defaultChecked; });
        setEdit(false);
      });

      btnSimpan.addEventListener('click', bukaKonfirmasi);

      rows.forEach(cb => cb.addEventListener('change', hitung));

      cari.addEventListener('input', () => {
        const q = cari.value.trim().toLowerCase();
        rows.forEach(cb => {
          const li = cb.closest('.paket-row');
          li.hidden = q !== '' && !li.dataset.cari.includes(q);
        });
      });

      modal.querySelectorAll('[data-tutup]').forEach(b => b.addEventListener('click', tutupKonfirmasi));
      modal.addEventListener('click', e => { if (e.target === modal) tutupKonfirmasi(); });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('show')) tutupKonfirmasi();
      });

      form.addEventListener('submit', e => {
        if (sudahKirim) { e.preventDefault(); return; }
        sudahKirim = true;
        konfYa.textContent = 'Menyimpan…';
      });

      // Cegah kehilangan perubahan
      window.addEventListener('beforeunload', e => {
        if (editing && berubah()) {
          e.preventDefault();
          e.returnValue = '';
        }
      });
      document.querySelectorAll('[data-pindah]').forEach(a => {
        a.addEventListener('click', ev => {
          if (editing && berubah() && !confirm('Perubahan yang belum disimpan akan hilang. Lanjutkan?')) {
            ev.preventDefault();
          }
        });
      });

      setEdit(false);
    })();
    </script>

    <?php endif; ?>

<?php include __DIR__ . '/partials/layout-end.php'; ?>