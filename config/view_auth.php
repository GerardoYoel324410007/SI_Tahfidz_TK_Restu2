<?php
/**
 * Auth Check untuk View/HTML Pages
 * Include file ini di awal setiap view PHP untuk proteksi akses
 *
 * Usage:
 *   <?php require_once __DIR__ . '/../../config/view_auth.php'; ?>
 *   <?php requireRole('admin'); ?>
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

/**
 * Proteksi halaman: redirect jika belum login
 */
function requireLogin() {
    Auth::init();
    if (!Auth::isLoggedIn()) {
        header('Location: /views/shared/login.html');
        exit;
    }
}

/**
 * Proteksi halaman: redirect jika bukan role yang sesuai
 */
function requireRole(string $role) {
    Auth::init();
    if (!Auth::isLoggedIn()) {
        header('Location: /views/shared/login.html');
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        Auth::redirectByRole();
        exit;
    }
}

/**
 * Mendapatkan data user yang sedang login
 */
function getCurrentUser() {
    return Auth::getCurrentUser();
}

/**
 * Generate CSRF token untuk form
 */
function csrfToken() {
    return Auth::generateCSRFToken();
}

/**
 * Generate CSRF hidden input field
 */
function csrfField() {
    $token = csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
