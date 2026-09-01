<?php
require_once __DIR__ . '/icons.php';
$admin_page = $admin_page ?? '';
$nav = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard'],
    'soal'      => ['label' => 'Bank Soal', 'href' => 'soal.php',      'icon' => 'soal'],
    'paket'     => ['label' => 'Kelola Paket','href' => 'paket.php',   'icon' => 'sesi'],
    'peserta'   => ['label' => 'Peserta',   'href' => 'peserta.php',   'icon' => 'peserta'],
    'kelompok'  => ['label' => 'Kelola Kelompok','href' => 'kelompok.php', 'icon' => 'peserta'],
    'sesi'      => ['label' => 'Sesi Ujian','href' => 'sesi.php',      'icon' => 'sesi'],
    'hasil'     => ['label' => 'Rekap Nilai','href' => 'hasil.php',    'icon' => 'hasil'],
    'log'       => ['label' => 'Log Aktivitas','href' => 'log.php',    'icon' => 'log'],
];
?>
<aside class="sidebar">
        <div class="sidebar-logo">
            <img src="<?= url('/assets/img/logo-bbu1.png') ?>" alt="Logo Bharuna Bhakti Utama" class="sidebar-mark">
            <div>
                <strong class="sidebar-title">BBU Tryout</strong>
                <span class="sidebar-sub">Panel Admin</span>
            </div>
        </div>
    <nav>
        <?php foreach ($nav as $key => $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="<?= $admin_page === $key ? 'active' : '' ?>">
            <span class="nav-icon"><?= admin_icon($item['icon']) ?></span>
            <?= htmlspecialchars($item['label']) ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= url('/logout.php') ?>">
            <span class="nav-icon"><?= admin_icon('logout') ?></span>
            Keluar
        </a>
    </div>
</aside>
