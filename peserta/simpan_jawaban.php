<?php
require_once dirname(__DIR__) . '/config.php';
cekPeserta();

header('Content-Type: application/json');

$peserta_id = $_SESSION['peserta_id'];
$data = json_decode(file_get_contents('php://input'), true);

$sesi_id = (int) ($data['sesi_id'] ?? 0);
$soal_id = (int) ($data['soal_id'] ?? 0);
$jawaban = $data['jawaban'] ?? null;

if ($sesi_id <= 0 || $soal_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Data tidak lengkap']);
    exit;
}

if ($jawaban !== null && !in_array($jawaban, OPSI_HURUF, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Jawaban tidak valid']);
    exit;
}

// ── VALIDASI IDOR #1 ─────────────────────────────────────────
// Pastikan sesi_id ini BENAR MILIK PAKET peserta yang sedang login
// (bukan sekadar percaya sesi_id yang dikirim dari browser)
$cek_sesi = $conn->prepare("
    SELECT s.id FROM sesi_ujian s
    JOIN peserta p ON p.paket_id = s.paket_id
    WHERE s.id = ? AND p.id = ? AND s.status = 'aktif'
");
$cek_sesi->bind_param("ii", $sesi_id, $peserta_id);
$cek_sesi->execute();
if ($cek_sesi->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sesi ini tidak valid untuk akun Anda']);
    exit;
}

// ── VALIDASI IDOR #2 ─────────────────────────────────────────
// Pastikan soal_id ini BENAR bagian dari paket_kategori milik paket peserta,
// bukan soal_id sembarangan yang dipalsukan dari DevTools
$stmt = $conn->prepare("
    SELECT s.jawaban_benar, s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d, s.pilihan_e
    FROM soal s
    JOIN kategori k ON s.kategori_id = k.id
    JOIN paket_kategori pk ON pk.kategori_id = k.id
    JOIN peserta p ON p.paket_id = pk.paket_id
    WHERE s.id = ? AND p.id = ?
");
$stmt->bind_param("ii", $soal_id, $peserta_id);
$stmt->execute();
$soal = $stmt->get_result()->fetch_assoc();

if (!$soal) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Soal ini tidak valid untuk paket Anda']);
    exit;
}
// Pilihan harus benar-benar ada untuk soal ini (soal ABCD tidak boleh menerima E)
if ($jawaban !== null && !array_key_exists($jawaban, opsi_soal($soal))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Pilihan tidak tersedia untuk soal ini']);
    exit;
}

// ── VALIDASI REPLAY / STATUS UJIAN ──────────────────────────
// Pastikan ujian peserta ini belum berstatus 'selesai' atau 'dijeda'
// Mencegah jawaban diubah setelah submit final, atau saat sedang dijeda
$cek_status = $conn->prepare("SELECT status_pengerjaan FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
$cek_status->bind_param("ii", $peserta_id, $sesi_id);
$cek_status->execute();
$status_row = $cek_status->get_result()->fetch_assoc();

if (!$status_row || $status_row['status_pengerjaan'] === 'selesai') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ujian sudah selesai, jawaban tidak bisa diubah']);
    exit;
}

if ($status_row['status_pengerjaan'] === 'dijeda') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ujian sedang dijeda']);
    exit;
}

$is_benar = ($jawaban !== null && $jawaban === $soal['jawaban_benar']) ? 1 : 0;

require_once dirname(__DIR__) . '/includes/penilaian.php';
// ... setelah $peserta_id, $sesi_id, dan $data sudah diambil ...

$sesi = validasi_sesi_peserta($conn, $sesi_id, (int) $_SESSION['peserta_paket_id']);
if (!$sesi) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sesi tidak valid']);
    exit;
}

$cek = $conn->prepare("SELECT * FROM hasil_ujian WHERE peserta_id=? AND sesi_id=?");
$cek->bind_param("ii", $peserta_id, $sesi_id);
$cek->execute();
$hasil = $cek->get_result()->fetch_assoc();

if (!$hasil || $hasil['status_pengerjaan'] !== 'sedang_dikerjakan' || waktu_habis($hasil, (int) $sesi['durasi_menit'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ujian tidak sedang berjalan']);
    exit;
}