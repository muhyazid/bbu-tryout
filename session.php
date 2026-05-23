<?php
/**
 * Session PHP & pengecekan login.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function cekAdmin(): void
{
    if (!isset($_SESSION['admin_id'])) {
        redirect('/index.php');
    }
}

function cekPeserta(): void
{
    if (!isset($_SESSION['peserta_id'])) {
        redirect('/index.php');
    }
}
