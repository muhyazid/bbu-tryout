<?php
/**
 * Komponen universal Page Header
 * Variabel yang perlu di-set sebelum include:
 *   $ph_breadcrumb  — array, contoh: ['Dashboard' => 'dashboard.php', 'Rekap Nilai' => null]
 *   $ph_title       — string, judul halaman
 *   $ph_subtitle    — string, deskripsi singkat
 *   $ph_action      — string|null, HTML tombol aksi (opsional)
 */
$ph_breadcrumb = $ph_breadcrumb ?? [];
$ph_title      = $ph_title      ?? '';
$ph_subtitle   = $ph_subtitle   ?? '';
$ph_action     = $ph_action     ?? null;
?>
<div class="ph-wrap">
  <div class="ph-left">

    <?php if (!empty($ph_breadcrumb)): ?>
    <nav class="ph-breadcrumb" aria-label="breadcrumb">
      <?php
        $items = array_keys($ph_breadcrumb);
        $links = array_values($ph_breadcrumb);
        foreach ($items as $i => $label):
          $isLast = ($i === count($items) - 1);
      ?>
        <?php if (!$isLast && $links[$i]): ?>
          <a href="<?= htmlspecialchars($links[$i]) ?>"><?= htmlspecialchars($label) ?></a>
          <span class="ph-sep">›</span>
        <?php else: ?>
          <span class="ph-current"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <h1 class="ph-title"><?= $ph_title ?></h1>

    <?php if ($ph_subtitle): ?>
    <p class="ph-subtitle"><?= $ph_subtitle ?></p>
    <?php endif; ?>

  </div>

  <?php if ($ph_action): ?>
  <div class="ph-right">
    <?= $ph_action ?>
  </div>
  <?php endif; ?>
</div>