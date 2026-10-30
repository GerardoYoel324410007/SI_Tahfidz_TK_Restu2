<?php
/**
 * Entry Point Utama - Sistem Informasi Tahfidz
 * Redirect berdasarkan role pengguna
 */

session_start();

// Deteksi base path dari URL request
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(dirname($scriptName), '/');

// Jika belum login, redirect ke halaman login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . $basePath . '/views/shared/login.html');
    exit;
}

// Redirect berdasarkan role
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: ' . $basePath . '/views/admin/dashboard.html');
        break;
    case 'guru':
        header('Location: ' . $basePath . '/views/guru/beranda.html');
        break;
    case 'wali_murid':
        header('Location: ' . $basePath . '/views/wali_murid/beranda.html');
        break;
    default:
        session_destroy();
        header('Location: ' . $basePath . '/views/shared/login.html');
        break;
}
exit;
