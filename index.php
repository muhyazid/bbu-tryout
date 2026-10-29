<?php
require_once 'config.php';

// Kalau sudah login, langsung redirect
if (isset($_SESSION['admin_id'])) redirect('/admin/dashboard.php');
if (isset($_SESSION['peserta_id'])) redirect('/peserta/dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Cek admin dulu
    $stmt = $conn->prepare("SELECT id, nama, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $input);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($pass, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_nama'] = $admin['nama'];
        redirect('/admin/dashboard.php');
    }

    // Cek peserta
    $stmt2 = $conn->prepare("
        SELECT p.id, p.nama, p.password, p.paket_id, pp.nama AS paket_nama
        FROM peserta p
        JOIN paket_program pp ON p.paket_id = pp.id
        WHERE p.nomor_peserta = ?
    ");
    $stmt2->bind_param("s", $input);
    $stmt2->execute();
    $peserta = $stmt2->get_result()->fetch_assoc();

    if ($peserta && password_verify($pass, $peserta['password'])) {
        $_SESSION['peserta_id']         = $peserta['id'];
        $_SESSION['peserta_nama']       = $peserta['nama'];
        $_SESSION['peserta_paket_id']   = $peserta['paket_id'];
        $_SESSION['peserta_paket_nama'] = $peserta['paket_nama'];
        redirect('/peserta/dashboard.php');
    }

    $error = 'Username / nomor peserta atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#15384F">
    <title>BBU Tryout</title>
    <link rel="stylesheet" href="<?= url('/assets/css/login.css') ?>">
</head>
<body class="login-page">
<main class="login-shell">
    <section class="brand-panel" aria-labelledby="brand-title">
        <div class="brand-content">
            <div class="brand-logo-frame">
                <img src="<?= url('/assets/img/logo-bbu1.png') ?>" alt="Logo Bharuna Bhakti Utama" class="brand-logo">
            </div>

            <p class="brand-eyebrow">LEMBAGA DIKLAT REOR</p>
            <h1 id="brand-title" class="brand-institution">REOR BHARUNA BHAKTI UTAMA</h1>
            <div class="brand-divider" aria-hidden="true"></div>
            <p class="brand-description">
                Sistem ujian berbasis komputer untuk latihan dan evaluasi peserta diklat radio maritim.
            </p>
        </div>
    </section>

    <section class="form-panel" aria-labelledby="login-title">
        <div class="form-inner">
            <div class="form-heading">
                <h2 id="login-title">Mulai TryOut</h2>
                <p class="form-description">Masukkan username dan password yang telah diberikan oleh panitia tryout.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message" role="alert">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16.2h.01"/></svg>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="login-form" autocomplete="on">
                <div class="form-field">
                    <label for="username">Username / Nomor Peserta</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.3"/><path d="M5.5 19c.5-3.1 2.9-4.8 6.5-4.8s6 1.7 6.5 4.8"/></svg>
                        <input
                            id="username"
                            type="text"
                            name="username"
                            placeholder="Masukkan username atau nomor peserta"
                            value="<?= e($input ?? '') ?>"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5.5" y="10" width="13" height="10" rx="1.7"/><path d="M8.5 10V7.2a3.5 3.5 0 0 1 7 0V10M12 14v2.3"/></svg>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="login-button">
                    <span>Mulai Ujian</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 12h14M12.5 6l6 6-6 6"/></svg>
                </button>
            </form>

            <div class="login-assistance">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.7 9.2a2.4 2.4 0 1 1 4.2 1.6c-1.2 1.1-1.9 1.3-1.9 2.7M12 16.8h.01"/></svg>
                <p>Pastikan akun yang digunakan sesuai dengan data dari panitia tryout.</p>
            </div>
        </div>
    </section>
</main>
</body>
</html>
