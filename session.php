<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect(string $url): void {
    header("Location: $url");
    exit();
}

function cekAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        redirect('/bbu-tryout/index.php');
    }
}

function cekPeserta() {
    if (!isset($_SESSION['peserta_id'])) {
        redirect('/bbu-tryout/index.php');
    }
}
