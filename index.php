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
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <title>BBU Tryout - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            min-height: 100vh;
            background: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            padding: 20px;
        }

        body.login-bg-diagonal {
            background-color: #F1F5F9;
            background-image: repeating-linear-gradient(
                -45deg,
                transparent,
                transparent 10px,
                #E2E8F0 10px,
                #E2E8F0 11px
            );
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 36px 36px 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 24px rgba(15,30,60,0.08);
            border: 1px solid #E2E8F0;
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo img {
            width: 128px;
            height: auto;
            margin: 0 auto 16px;
            display: block;
        }

        .logo h1 {
            font-size: 20px;
            font-weight: 700;
            color: #0F1E3C;
            letter-spacing: -0.2px;
        }

        .logo p {
            font-size: 12.5px;
            color: #64748B;
            margin-top: 4px;
        }

        .badge {
            display: inline-block;
            background: #EFF6FF;
            color: #1D4ED8;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #E2E8F0;
            border-radius: 8px;
            font-size: 14px;
            color: #0F172A;
            margin-bottom: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input:focus {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
        }

        button {
            width: 100%;
            padding: 11px;
            background: #0F1E3C;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover { background: #162846; }

        .error {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .footer {
            text-align: center;
            margin-top: 22px;
            font-size: 11.5px;
            color: #94A3B8;
        }
    </style>
</head>
<body class="login-bg-diagonal ">

<div class="card">
    <div class="logo">
        <img src="<?= url('/assets/img/logo-bbu1.png') ?>" alt="Logo Bharuna Bhakti Utama">
        <h1>BBU Tryout</h1>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username / Nomor Peserta</label>
        <input type="text" name="username" placeholder="Masukkan username" required autofocus>

        <label>Password</label>
        <input type="password" name="password" placeholder="Masukkan password" required>

        <button type="submit">Masuk</button>
    </form>
</div>

</body>
</html>