<?php
require_once '../config.php';
cekAdmin();

// Filter
$fp  = (int)($_GET['peserta_id'] ?? 0);
$fj  = $_GET['jenis'] ?? '';
$fs  = (int)($_GET['sesi_id'] ?? 0);

$where = "WHERE 1=1";
if ($fp) $where .= " AND l.peserta_id=$fp";
if ($fj) $where .= " AND l.jenis='$fj'";
if ($fs) $where .= " AND l.sesi_id=$fs";

$logs = $conn->query("
    SELECT l.*, p.nama, p.nomor_peserta, s.nama_sesi
    FROM log_aktivitas l
    LEFT JOIN peserta    p ON l.peserta_id = p.id
    LEFT JOIN sesi_ujian s ON l.sesi_id    = s.id
    $where
    ORDER BY l.waktu DESC
    LIMIT 300
");

// Ringkasan per peserta (pindah tab & keluar fullscreen)
$ringkasan = $conn->query("
    SELECT p.nama, p.nomor_peserta,
           SUM(l.jenis='pindah_tab')         as tab,
           SUM(l.jenis='keluar_fullscreen')   as fs,
           s.nama_sesi
    FROM log_aktivitas l
    JOIN peserta    p ON l.peserta_id = p.id
    JOIN sesi_ujian s ON l.sesi_id    = s.id
    WHERE l.jenis IN ('pindah_tab','keluar_fullscreen')
    GROUP BY l.peserta_id, l.sesi_id
    ORDER BY SUM(l.jenis='pindah_tab') + SUM(l.jenis='keluar_fullscreen') DESC
    LIMIT 20
");

$peserta_opt = $conn->query("SELECT id, nama, nomor_peserta FROM peserta ORDER BY nama");
$sesi_opt    = $conn->query("SELECT id, nama_sesi FROM sesi_ujian ORDER BY tanggal DESC");

$admin_page = 'log';
$page_title = 'Log Aktivitas';

$log_labels = [
    'pindah_tab'        => 'Pindah tab',
    'keluar_fullscreen' => 'Keluar layar penuh',
    'submit'            => 'Submit ujian',
    'login'             => 'Login',
    'logout'            => 'Logout',
];

$extra_head = <<<'HTML'
<style>
.log-tab{background:#fef2f2;color:#b91c1c}
.log-fs{background:#fef3c7;color:#92400e}
.log-submit{background:#e6f4ec;color:#166534}
.log-login{background:#e8f0fe;color:#1a3c6e}
.log-logout{background:#f1f5f9;color:#64748b}
.log-badge{padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600}
</style>
HTML;
$topbar_breadcrumb = 'Log Aktivitas';
$topbar_title      = 'Log Aktivitas';

include __DIR__ . '/partials/layout-start.php';
?>

    <!-- RINGKASAN KECURANGAN -->
    <?php if ($ringkasan->num_rows > 0): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <h3>Peserta perlu perhatian</h3>
        <span style="font-size:12px;color:var(--text-muted);">Diurutkan dari terbanyak</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Peserta</th>
            <th>Sesi</th>
            <th style="text-align:center;">Pindah Tab</th>
            <th style="text-align:center;">Keluar Fullscreen</th>
            <th style="text-align:center;">Total</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($r = $ringkasan->fetch_assoc()):
            $total_sus = $r['tab'] + $r['fs'];
            $warna = $total_sus >= 5 ? 'var(--danger)' : ($total_sus >= 2 ? 'var(--warning)' : 'var(--success)');
        ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($r['nama']) ?></div>
              <code style="font-size:12px;background:#f0f2f5;padding:1px 6px;border-radius:4px;">
                <?= htmlspecialchars($r['nomor_peserta']) ?>
              </code>
            </td>
            <td style="font-size:13px;color:var(--text-muted);">
              <?= htmlspecialchars($r['nama_sesi']) ?>
            </td>
            <td style="text-align:center;">
              <span style="font-size:18px;font-weight:700;color:<?= $r['tab']>0 ? '#c0392b' : 'var(--text-muted)' ?>">
                <?= $r['tab'] ?>x
              </span>
            </td>
            <td style="text-align:center;">
              <span style="font-size:18px;font-weight:700;color:<?= $r['fs']>0 ? '#856404' : 'var(--text-muted)' ?>">
                <?= $r['fs'] ?>x
              </span>
            </td>
            <td style="text-align:center;">
              <span style="font-size:20px;font-weight:800;color:<?= $warna ?>;">
                <?= $total_sus ?>x
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- FILTER -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="padding:16px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Peserta</div>
            <select name="peserta_id" class="form-input" style="width:190px;">
              <option value="">Semua Peserta</option>
              <?php while ($p = $peserta_opt->fetch_assoc()): ?>
              <option value="<?= $p['id'] ?>" <?= $fp==$p['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nama']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Jenis Aktivitas</div>
            <select name="jenis" class="form-input">
              <option value="">Semua</option>
              <option value="pindah_tab"        <?= $fj==='pindah_tab'        ? 'selected' : '' ?>>Pindah tab</option>
              <option value="keluar_fullscreen"  <?= $fj==='keluar_fullscreen'  ? 'selected' : '' ?>>Keluar layar penuh</option>
              <option value="submit"             <?= $fj==='submit'             ? 'selected' : '' ?>>Submit ujian</option>
              <option value="login"              <?= $fj==='login'              ? 'selected' : '' ?>>Login</option>
            </select>
          </div>
          <div>
            <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Sesi</div>
            <select name="sesi_id" class="form-input" style="width:200px;">
              <option value="">Semua Sesi</option>
              <?php while ($so = $sesi_opt->fetch_assoc()): ?>
              <option value="<?= $so['id'] ?>" <?= $fs==$so['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($so['nama_sesi']) ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>
          <button type="submit" class="btn-primary" style="padding:9px 18px;">Filter</button>
          <a href="log.php"
             style="color:var(--text-muted);font-size:13px;text-decoration:none;
                    padding:9px 14px;border:1px solid var(--border);
                    border-radius:8px;background:#f8f9fa;">
            Reset
          </a>
        </form>
      </div>
    </div>

    <!-- TABEL LOG -->
    <div class="card">
      <div class="card-header">
        <h3>Detail log <span class="count-pill"><?= $logs->num_rows ?> entri</span></h3>
        <a href="log.php" class="btn-admin-primary" style="font-size:13px;padding:8px 14px;">
          <i class="fas fa-rotate-right" style="font-size:12px;"></i> Refresh
        </a>
      </div>
      <?php if ($logs->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Peserta</th>
            <th>Aktivitas</th>
            <th>Sesi</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($l = $logs->fetch_assoc()):
            $cls = match($l['jenis']) {
                'pindah_tab'       => 'log-tab',
                'keluar_fullscreen'=> 'log-fs',
                'submit'           => 'log-submit',
                'login'            => 'log-login',
                default            => 'log-logout'
            };
            $label = $log_labels[$l['jenis']] ?? ucfirst(str_replace('_', ' ', $l['jenis']));
        ?>
          <tr>
            <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
              <?= date('d M Y', strtotime($l['waktu'])) ?><br>
              <strong style="color:var(--text);font-size:13px;">
                <?= date('H:i:s', strtotime($l['waktu'])) ?>
              </strong>
            </td>
            <td>
              <div style="font-weight:600;font-size:14px;">
                <?= htmlspecialchars($l['nama'] ?? '—') ?>
              </div>
              <code style="font-size:11px;background:#f0f2f5;
                           padding:1px 6px;border-radius:4px;">
                <?= htmlspecialchars($l['nomor_peserta'] ?? '') ?>
              </code>
            </td>
            <td>
              <span class="log-badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
            </td>
            <td style="font-size:13px;color:var(--text-muted);">
              <?= htmlspecialchars($l['nama_sesi'] ?? '—') ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <p><strong>Belum ada log aktivitas</strong></p>
        <p>Log akan muncul saat peserta mulai mengerjakan ujian.</p>
      </div>
      <?php endif; ?>
    </div>

<?php include __DIR__ . '/partials/layout-end.php'; ?>