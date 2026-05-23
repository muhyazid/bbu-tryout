<?php
$host     = '127.0.0.1';
$dbname   = 'bbu_tryout';
$username = 'root';
$password = '';
$port     = 3306;

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    http_response_code(503);
    die("<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'>
        <title>Database tidak tersedia</title>
        <style>body{font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 20px;color:#333}
        h2{color:#c0392b}code{background:#f5f5f5;padding:2px 6px;border-radius:4px}</style></head><body>
        <h2>Database tidak tersedia</h2>
        <p>Koneksi ke MySQL gagal. Pastikan <strong>MySQL</strong> di XAMPP Control Panel sudah di-<em>Start</em>,
        lalu muat ulang halaman ini.</p>
        <p><a href='/bbu-tryout/index.php'>Kembali ke halaman login</a></p>
        </body></html>");
}

$conn->set_charset('utf8');
