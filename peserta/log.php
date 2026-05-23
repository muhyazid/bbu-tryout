<?php
require_once '../config.php';
if (!isset($_SESSION['peserta_id'])) exit;

$data = json_decode(file_get_contents('php://input'), true);
$pid  = (int)$_SESSION['peserta_id'];
$sid  = (int)($data['sesi_id'] ?? 0);
$jen  = $data['jenis'] ?? 'pindah_tab';

$allowed = ['pindah_tab','keluar_fullscreen','login','logout','submit'];
if (!in_array($jen, $allowed)) exit;

$s = $conn->prepare("INSERT INTO log_aktivitas (peserta_id,sesi_id,jenis) VALUES (?,?,?)");
$s->bind_param("iis", $pid, $sid, $jen);
$s->execute();
echo json_encode(['ok' => true]);