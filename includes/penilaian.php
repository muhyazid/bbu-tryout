<?php
const KKM = 70;
const TOLERANSI_DETIK = 10; // jeda jaringan saat submit mendekati batas waktu

/** Apakah waktu ujian sudah habis, dihitung dari server. */
function waktu_habis(array $hasil, int $durasi_menit): bool
{
    $terpakai = time() - strtotime($hasil['waktu_mulai']) - (int) $hasil['total_durasi_pause'];
    return $terpakai > ($durasi_menit * 60 + TOLERANSI_DETIK);
}

/**
 * Simpan satu jawaban. Hanya menerima soal yang ada di paket peserta
 * dan pilihan yang memang tersedia untuk soal itu.
 * Hasil: true jika tersimpan, false jika ditolak.
 */
function simpan_jawaban_server(mysqli $conn, int $peserta_id, int $sesi_id, int $soal_id, ?string $pilihan): bool
{
    $st = $conn->prepare("
        SELECT s.jawaban_benar, s.pilihan_a, s.pilihan_b, s.pilihan_c, s.pilihan_d, s.pilihan_e
        FROM soal s
        JOIN paket_kategori pk ON pk.kategori_id = s.kategori_id
        JOIN peserta p         ON p.paket_id     = pk.paket_id
        WHERE s.id = ? AND p.id = ?
    ");
    $st->bind_param("ii", $soal_id, $peserta_id);
    $st->execute();
    $soal = $st->get_result()->fetch_assoc();
    if (!$soal) return false;

    if ($pilihan !== null && !array_key_exists($pilihan, opsi_soal($soal))) return false;

    $is_benar = ($pilihan !== null && $pilihan === $soal['jawaban_benar']) ? 1 : 0;

    $cek = $conn->prepare("SELECT id FROM jawaban WHERE peserta_id=? AND sesi_id=? AND soal_id=?");
    $cek->bind_param("iii", $peserta_id, $sesi_id, $soal_id);
    $cek->execute();
    $ada = $cek->get_result()->fetch_assoc();

    if ($ada) {
        $upd = $conn->prepare("UPDATE jawaban SET jawaban_dipilih=?, is_benar=? WHERE id=?");
        $upd->bind_param("sii", $pilihan, $is_benar, $ada['id']);
        return $upd->execute();
    }
    $ins = $conn->prepare("INSERT INTO jawaban (peserta_id, sesi_id, soal_id, jawaban_dipilih, is_benar) VALUES (?,?,?,?,?)");
    $ins->bind_param("iiisi", $peserta_id, $sesi_id, $soal_id, $pilihan, $is_benar);
    return $ins->execute();
}

/**
 * Hitung hasil dari tabel jawaban, bukan dari POST.
 * Semua soal di paket dihitung, yang tidak punya baris jawaban dianggap salah.
 */
function hitung_hasil(mysqli $conn, int $peserta_id, int $sesi_id, array $soal_ids): array
{
    if (empty($soal_ids)) {
        return ['total' => 0, 'benar' => 0, 'salah' => 0, 'skor' => 0, 'per_kategori' => []];
    }
    $in = implode(',', array_map('intval', $soal_ids));
    $res = $conn->query("
        SELECT s.id, s.bobot, s.kategori_id, COALESCE(j.is_benar, 0) AS is_benar
        FROM soal s
        LEFT JOIN jawaban j ON j.soal_id = s.id AND j.peserta_id = $peserta_id AND j.sesi_id = $sesi_id
        WHERE s.id IN ($in)
    ");

    $total = 0; $benar = 0; $bobot_maks = 0; $bobot_dapat = 0; $per = [];
    while ($r = $res->fetch_assoc()) {
        $b = (int) $r['bobot'];
        $k = (int) $r['kategori_id'];
        $total++;
        $bobot_maks += $b;
        if (!isset($per[$k])) $per[$k] = ['didapat' => 0, 'maksimal' => 0];
        $per[$k]['maksimal'] += $b;
        if ($r['is_benar']) {
            $benar++;
            $bobot_dapat += $b;
            $per[$k]['didapat'] += $b;
        }
    }
    return [
        'total'        => $total,
        'benar'        => $benar,
        'salah'        => $total - $benar,
        'skor'         => $bobot_maks > 0 ? (int) round($bobot_dapat / $bobot_maks * 100) : 0,
        'per_kategori' => $per,
    ];
}