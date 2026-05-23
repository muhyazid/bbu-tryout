<?php
session_start();

$host     = 'localhost';
$dbname   = 'bbu_tryout';
$username = 'root';
$password = ''; // Isi password MySQL kamu

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8");

function redirect($url) { header("Location: $url"); exit(); }
function cekAdmin()   { if (!isset($_SESSION['admin_id']))   redirect('/bbu-tryout/index.php'); }
function cekPeserta() { if (!isset($_SESSION['peserta_id'])) redirect('/bbu-tryout/index.php'); }
?>