<?php
require_once dirname(__DIR__) . '/config.php';
cekPeserta();

header('Content-Type: application/json');

$peserta_id = $_SESSION['peserta_id'];

$stmt = $conn->prepare("
    SELECT kg.status FROM kelompok_anggota ka
    JOIN kelompok_angkatan kg ON ka.kelompok_id = kg.id
    WHERE ka.peserta_id = ?
");
$stmt->bind_param("i", $peserta_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$dijeda = $row && $row['status'] === 'dijeda';

echo json_encode(['dijeda' => $dijeda]);