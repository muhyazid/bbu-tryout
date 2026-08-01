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
    $stmt2 = $conn->prepare("SELECT id, nama, password, jenis FROM peserta WHERE nomor_peserta = ?");
    $stmt2->bind_param("s", $input);
    $stmt2->execute();
    $peserta = $stmt2->get_result()->fetch_assoc();

    if ($peserta && password_verify($pass, $peserta['password'])) {
        $_SESSION['peserta_id']   = $peserta['id'];
        $_SESSION['peserta_nama'] = $peserta['nama'];
        $_SESSION['peserta_jenis']= $peserta['jenis'];
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
    <title>BBU Tryout — Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 40px 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a3c6e;
        }

        .logo p {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }

        .badge {
            display: inline-block;
            background: #e8f0fe;
            color: #1a3c6e;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #444;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            margin-bottom: 16px;
            transition: border-color 0.2s;
            outline: none;
        }

        input:focus { border-color: #1a3c6e; }

        button {
            width: 100%;
            padding: 11px;
            background: #1a3c6e;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover { background: #15305a; }

        .error {
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #f5c6c6;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #aaa;
        }
    </style>
</head>
<body>

<div class="card">
    <div class="logo">
        <span class="badge">SOU & GMDSS</span>
        <h1>BBU Tryout</h1>
        <p>Bharuna Bhakti Utama — Sistem Ujian Lokal</p>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Username / Nomor Peserta</label>
        <input type="text" name="username" placeholder="Masukkan username" required autofocus>

        <label>Password</label>
        <input type="password" name="password" placeholder="Masukkan password" required>

        <button type="submit">Masuk</button>
    </form>

    <div class="footer">Admin login menggunakan username · Peserta menggunakan nomor peserta</div>
</div>

</body>
</html>