<?php
require_once dirname(__DIR__) . '/config.php';
cekPeserta();

header('Content-Type: application/json');

$peserta_id = $_SESSION['peserta_id'];
$data = json_decode(file_get_contents('php://input'), true);

$sesi_id = (int) ($data['sesi_id'] ?? 0);
$soal_id = (int) ($data['soal_id'] ?? 0);
$jawaban = $data['jawaban'] ?? null; // 'A', 'B', 'C', 'D', atau null (batal pilih)

if ($sesi_id <= 0 || $soal_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

// Ambil kunci jawaban benar untuk hitung is_benar
$stmt = $conn->prepare("SELECT jawaban_benar FROM soal WHERE id=?");
$stmt->bind_param("i", $soal_id);
$stmt->execute();
$soal = $stmt->get_result()->fetch_assoc();

if (!$soal) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Soal tidak ditemukan']);
    exit;
}

$is_benar = ($jawaban !== null && $jawaban === $soal['jawaban_benar']) ? 1 : 0;

// Cek apakah sudah ada baris jawaban untuk kombinasi ini (upsert manual)
$cek = $conn->prepare("SELECT id FROM jawaban WHERE peserta_id=? AND sesi_id=? AND soal_id=?");
$cek->bind_param("iii", $peserta_id, $sesi_id, $soal_id);
$cek->execute();
$existing = $cek->get_result()->fetch_assoc();

if ($existing) {
    // Sudah pernah dijawab sebelumnya → update
    $upd = $conn->prepare("UPDATE jawaban SET jawaban_dipilih=?, is_benar=? WHERE id=?");
    $upd->bind_param("sii", $jawaban, $is_benar, $existing['id']);
    $ok = $upd->execute();
} else {
    // Belum pernah dijawab → insert baru
    $ins = $conn->prepare("INSERT INTO jawaban (peserta_id,sesi_id,soal_id,jawaban_dipilih,is_benar) VALUES (?,?,?,?,?)");
    $ins->bind_param("iiisi", $peserta_id, $sesi_id, $soal_id, $jawaban, $is_benar);
    $ok = $ins->execute();
}

echo json_encode(['ok' => (bool) $ok]);