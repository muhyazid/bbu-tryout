<?php
require_once __DIR__ . '/icons.php';
$admin_page = $admin_page ?? '';

$nav_grouped = [
    'Konten Ujian' => [
        'soal'  => ['label' => 'Bank Soal',    'href' => 'soal.php',  'icon' => 'soal'],
        'paket' => ['label' => 'Kelola Paket', 'href' => 'paket.php', 'icon' => 'sesi'],
    ],
    'Peserta' => [
        'peserta'  => ['label' => 'Peserta',         'href' => 'peserta.php',  'icon' => 'peserta'],
        'kelompok' => ['label' => 'Kelola Kelompok', 'href' => 'kelompok.php', 'icon' => 'peserta'],
    ],
    'Pelaksanaan Ujian' => [
        'sesi'  => ['label' => 'Sesi Ujian', 'href' => 'sesi.php',  'icon' => 'sesi'],
        'hasil' => ['label' => 'Rekap Nilai','href' => 'hasil.php', 'icon' => 'hasil'],
    ],
    'Monitoring' => [
        'log' => ['label' => 'Log Aktivitas', 'href' => 'log.php', 'icon' => 'log'],
    ],
];
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="sidebar-mark">BBU</span>
        <div>
            <strong class="sidebar-title">BBU Tryout</strong>
            <span class="sidebar-sub">Panel Admin</span>
        </div>
    </div>
    <nav>
        <a href="dashboard.php" class="<?= $admin_page === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon"><?= admin_icon('dashboard') ?></span>
            Dashboard
        </a>

        <?php foreach ($nav_grouped as $section_label => $items): ?>
        <div class="nav-section-label"><?= htmlspecialchars($section_label) ?></div>
        <?php foreach ($items as $key => $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="<?= $admin_page === $key ? 'active' : '' ?>">
            <span class="nav-icon"><?= admin_icon($item['icon']) ?></span>
            <?= htmlspecialchars($item['label']) ?>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= url('/logout.php') ?>">
            <span class="nav-icon"><?= admin_icon('logout') ?></span>
            Keluar
        </a>
    </div>
</aside>