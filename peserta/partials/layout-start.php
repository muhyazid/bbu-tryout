<?php
/**
 * Awal layout peserta.
 *   $page_title — judul tab browser
 *   $body_class — (opsional) kelas tambahan pada <body>
 */
$page_title = $page_title ?? 'BBU Tryout';
$body_class = trim('peserta-page ' . ($body_class ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include __DIR__ . '/head.php'; ?>
</head>
<body class="<?= e($body_class) ?>">
<?php include __DIR__ . '/header.php'; ?>
<main class="peserta-main">
