<?php
/**
 * Logika sesi ujian untuk peserta.
 * Sesi tampil jika: status aktif, program cocok, tanggal = hari ini.
 * Batas jam dipakai sebagai info, bukan penyembunyikan sesi (agar tidak "hilang" saat testing).
 */

function normalisasi_jam(string $jam): string
{
    $jam = trim($jam);
    if (strlen($jam) === 5) {
        return $jam . ':00';
    }
    return substr($jam, 0, 8);
}

/**
 * @return array<string, mixed>|null
 */
function cari_sesi_aktif_peserta(mysqli $conn, string $jenis): ?array
{
    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT * FROM sesi_ujian
        WHERE status = 'aktif'
          AND jenis = ?
          AND tanggal = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param('ss', $jenis, $today);
    $stmt->execute();
    $sesi = $stmt->get_result()->fetch_assoc();
    if (!$sesi) {
        return null;
    }

    $now     = normalisasi_jam(date('H:i:s'));
    $mulai   = normalisasi_jam((string) $sesi['jam_mulai']);
    $selesai = normalisasi_jam((string) $sesi['jam_selesai']);

    $sesi['dalam_jadwal'] = ($now >= $mulai && $now <= $selesai);
    $sesi['info_jadwal']  = null;

    if (!$sesi['dalam_jadwal']) {
        if ($now < $mulai) {
            $sesi['info_jadwal'] = 'Jadwal mulai pukul ' . substr($mulai, 0, 5)
                . '. Jika pengawas sudah membuka ujian, Anda boleh mulai.';
        } else {
            $sesi['info_jadwal'] = 'Jadwal resmi berakhir pukul ' . substr($selesai, 0, 5)
                . '. Jika masih diizinkan mengerjakan, Anda boleh mulai.';
        }
    }

    return $sesi;
}

/**
 * @return array<string, mixed>|null
 */
function validasi_sesi_peserta(mysqli $conn, int $sesi_id, string $jenis): ?array
{
    $stmt = $conn->prepare("
        SELECT * FROM sesi_ujian
        WHERE id = ?
          AND status = 'aktif'
          AND jenis = ?
          AND tanggal = CURDATE()
        LIMIT 1
    ");
    $stmt->bind_param('is', $sesi_id, $jenis);
    $stmt->execute();
    $sesi = $stmt->get_result()->fetch_assoc();

    return $sesi ?: null;
}
