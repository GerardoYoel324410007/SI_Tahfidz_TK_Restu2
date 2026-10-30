<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * POST /api/auth/ganti-password.php
 * Ganti password user yang sedang login
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/UserModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

Auth::init();
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid.']);
    exit;
}

$passwordLama = $input['password_lama'] ?? '';
$passwordBaru = $input['password_baru'] ?? '';
$konfirmasi = $input['konfirmasi'] ?? '';

if (empty($passwordLama) || empty($passwordBaru) || empty($konfirmasi)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Semua field wajib diisi.']);
    exit;
}

if (strlen($passwordBaru) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal 6 karakter.']);
    exit;
}

if ($passwordBaru !== $konfirmasi) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Konfirmasi password tidak cocok.']);
    exit;
}

try {
    $user = Auth::getCurrentUser();
    $userModel = new UserModel();
    $userData = $userModel->findById($user['user_id']);

    if (!$userData || !password_verify($passwordLama, $userData['password_hash'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password lama salah.']);
        exit;
    }

    $newHash = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 12]);
    $userModel->updatePassword($user['user_id'], $newHash);

    echo json_encode(['status' => 'success', 'message' => 'Password berhasil diubah.']);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
}
