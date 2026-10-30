<?php
/**
 * GET /api/auth/status-permintaan-password.php
 * Ambil status permintaan ganti password terbaru milik user yang sedang login.
 * Digunakan frontend untuk menampilkan status di halaman beranda/profil.
 *
 * Response:
 *   { status: 'success', data: { permintaan: {...} | null } }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/PermintaanPasswordModel.php';

Auth::init();
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $currentUser = Auth::getCurrentUser();
    $userId      = (int) $currentUser['user_id'];

    $ppModel    = new PermintaanPasswordModel();
    $permintaan = $ppModel->getTerbaruByUserId($userId);

    // Hapus hash dari response (tidak perlu dan sensitif)
    if ($permintaan) {
        unset($permintaan['password_hash_baru']);
    }

    echo json_encode([
        'status' => 'success',
        'data'   => ['permintaan' => $permintaan],
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
