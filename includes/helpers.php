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
