<?php
$nama_tampil  = htmlspecialchars((string)($_SESSION['peserta_nama'] ?? ''));
$jenis_tampil = htmlspecialchars((string)($_SESSION['peserta_jenis'] ?? ''));
?>
<header class="peserta-header">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true">BBU</span>
        <div>
            <div class="brand-title">BBU Tryout</div>
            <div class="brand-sub">Program <?= $jenis_tampil ?> — Bharuna Bhakti Utama</div>
        </div>
    </div>
    <div class="user-block">
        <span class="user-name"><?= $nama_tampil ?></span>
        <a href="/bbu-tryout/logout.php" class="btn-logout">
            Keluar
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>
    </div>
</header>
