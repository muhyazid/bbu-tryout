<?php
/**
 * Fungsi bantu umum.
 */

function url(string $path = ''): string
{
    return APP_BASE . ($path === '' || $path[0] === '/' ? $path : '/' . $path);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate password acak yang mudah dibaca manusia (untuk dicetak di kartu).
 * Hindari karakter yang gampang tertukar saat dibaca (0/O, 1/l/I).
 */
function generate_password_acak(int $panjang = 8): string
{
    $karakter = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // tanpa 0,O,1,I,L
    $hasil = '';
    for ($i = 0; $i < $panjang; $i++) {
        $hasil .= $karakter[random_int(0, strlen($karakter) - 1)];
    }
    return $hasil;
}

/**
 * Hitung sisa waktu ujian (dalam detik) berdasarkan waktu_mulai dan
 * total_durasi_pause yang tersimpan di database — bukan timer JS murni.
 */
function hitung_sisa_waktu_detik(array $hasil_ujian_row, int $durasi_menit_total): int
{
    if (empty($hasil_ujian_row['waktu_mulai'])) {
        return $durasi_menit_total * 60; // belum pernah mulai, waktu penuh
    }

    $durasi_total_detik = $durasi_menit_total * 60;
    $waktu_mulai_ts     = strtotime($hasil_ujian_row['waktu_mulai']);
    $waktu_terpakai     = time() - $waktu_mulai_ts - (int)$hasil_ujian_row['total_durasi_pause'];

    $sisa = $durasi_total_detik - $waktu_terpakai;
    return max(0, $sisa); // tidak boleh negatif
}

const OPSI_HURUF = ['A', 'B', 'C', 'D', 'E'];

/**
 * Pilihan yang benar-benar terisi untuk satu soal.
 * Hasil contoh: ['A' => 'teks', 'B' => 'teks', 'C' => 'teks', 'D' => 'teks']
 */
function opsi_soal(array $soal): array
{
    $hasil = [];
    foreach (OPSI_HURUF as $h) {
        $kolom = 'pilihan_' . strtolower($h);
        if (isset($soal[$kolom]) && trim((string) $soal[$kolom]) !== '') {
            $hasil[$h] = $soal[$kolom];
        }
    }
    return $hasil;
}