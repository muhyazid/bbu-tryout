<?php
require_once __DIR__ . '/icons.php';
$admin_page = $admin_page ?? '';

$nav_grouped = [
    'Konten Ujian' => [
        'soal'  => ['label' => 'Bank Soal',    'href' => 'soal.php',  'icon' => 'soal'],
        'paket' => ['label' => 'Kelola Paket', 'href' => 'paket.php', 'icon' => 'file'],
    ],
    'Peserta' => [
        'peserta'  => ['label' => 'Peserta',         'href' => 'peserta.php',  'icon' => 'peserta'],
        'kelompok' => ['label' => 'Kelola Kelompok', 'href' => 'kelompok.php', 'icon' => 'users'],
    ],
    'Pelaksanaan Ujian' => [
        'sesi'  => ['label' => 'Sesi Ujian',  'href' => 'sesi.php',  'icon' => 'sesi'],
        'hasil' => ['label' => 'Rekap Nilai', 'href' => 'hasil.php', 'icon' => 'hasil'],
    ],
    'Monitoring' => [
        'log' => ['label' => 'Log Aktivitas', 'href' => 'log.php', 'icon' => 'log'],
    ],
];
?>
<style>
  .sidebar {
    --sb-bg-1:#1a3354; --sb-bg-2:#1e3a5f; --sb-text:#cbd5e0; --sb-muted:rgba(255,255,255,.45);
    --sb-hover:rgba(255,255,255,.08); --sb-active:rgba(255,255,255,.14); --sb-accent:#63b3ed;
    width:260px; flex-shrink:0;
    position:sticky; top:0; height:100vh; overflow-y:auto;
    display:flex; flex-direction:column;
    background:linear-gradient(180deg,var(--sb-bg-1) 0%,var(--sb-bg-2) 100%);
    color:var(--sb-text); padding:20px 14px;
    font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.2) transparent;
  }

  /* Brand */
  .sidebar .sb-brand { display:flex; align-items:center; gap:12px; padding:4px 8px 20px; text-decoration:none; color:#fff; }
  .sidebar .sb-mark {
    width:40px; height:40px; border-radius:11px; flex-shrink:0; display:grid; place-items:center;
    background:linear-gradient(135deg,#63b3ed,#4299e1); color:#fff;
    font-weight:800; font-size:13px; letter-spacing:.5px; box-shadow:0 4px 12px rgba(66,153,225,.35);
  }
  .sidebar .sb-brand-title { display:block; font-size:15px; font-weight:700; line-height:1.2; }
  .sidebar .sb-brand-sub { display:block; font-size:12px; color:var(--sb-muted); margin-top:2px; }

  /* Navigasi */
  .sidebar nav { display:flex; flex-direction:column; gap:2px; }
  .sidebar .sb-group { font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase;
                       color:var(--sb-muted); padding:18px 12px 6px; }
  .sidebar .sb-link {
    position:relative; display:flex; align-items:center; gap:12px;
    padding:10px 12px; border-radius:10px; color:var(--sb-text);
    text-decoration:none; font-size:14px; font-weight:500; transition:background .15s, color .15s;
  }
  .sidebar .sb-link:hover { background:var(--sb-hover); color:#fff; }
  .sidebar .sb-link.active { background:var(--sb-active); color:#fff; font-weight:600; }
  .sidebar .sb-link.active::before {
    content:""; position:absolute; left:-14px; top:50%; transform:translateY(-50%);
    width:4px; height:60%; border-radius:0 4px 4px 0; background:var(--sb-accent);
  }
  .sidebar .sb-icon { width:20px; height:20px; display:grid; place-items:center; flex-shrink:0; opacity:.85; }
  .sidebar .sb-link.active .sb-icon { opacity:1; color:var(--sb-accent); }
  .sidebar .sb-icon svg { width:18px; height:18px; }

  /* Footer */
  .sidebar .sb-footer { margin-top:auto; padding-top:14px; border-top:1px solid rgba(255,255,255,.1); }
  .sidebar .sb-logout { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px;
                        color:var(--sb-text); text-decoration:none; font-size:14px; font-weight:500; transition:background .15s,color .15s; }
  .sidebar .sb-logout:hover { background:rgba(229,62,62,.15); color:#feb2b2; }

  /* Responsif: sidebar menyempit jadi ikon saja di layar kecil */
  @media (max-width:900px) {
    .sidebar { width:72px; padding:18px 10px; }
    .sidebar .sb-brand-text, .sidebar .sb-group, .sidebar .sb-text { display:none; }
    .sidebar .sb-brand { justify-content:center; padding:4px 0 18px; }
    .sidebar .sb-link, .sidebar .sb-logout { justify-content:center; padding:12px 0; }
    .sidebar .sb-link.active::before { left:-10px; }
  }
</style>

<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <span class="sb-mark">BBU</span>
        <span class="sb-brand-text">
            <span class="sb-brand-title">BBU Tryout</span>
            <span class="sb-brand-sub">Panel Admin</span>
        </span>
    </a>

    <nav>
        <a href="dashboard.php" class="sb-link <?= $admin_page === 'dashboard' ? 'active' : '' ?>" title="Dashboard">
            <span class="sb-icon"><?= admin_icon('dashboard') ?></span>
            <span class="sb-text">Dashboard</span>
        </a>

        <?php foreach ($nav_grouped as $section_label => $items): ?>
            <div class="sb-group"><?= htmlspecialchars($section_label) ?></div>
            <?php foreach ($items as $key => $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="sb-link <?= $admin_page === $key ? 'active' : '' ?>"
                   title="<?= htmlspecialchars($item['label']) ?>">
                    <span class="sb-icon"><?= admin_icon($item['icon']) ?></span>
                    <span class="sb-text"><?= htmlspecialchars($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sb-footer">
        <a href="<?= url('/logout.php') ?>" class="sb-logout" title="Keluar">
            <span class="sb-icon"><?= admin_icon('logout') ?></span>
            <span class="sb-text">Keluar</span>
        </a>
    </div>
</aside>