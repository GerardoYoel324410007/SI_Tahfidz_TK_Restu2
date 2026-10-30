<?php
/**
 * POST /api/admin/reset-password.php
 * Admin mereset password user secara langsung tanpa melalui alur persetujuan.
 * Jika ada permintaan aktif dari user tersebut, akan otomatis dibatalkan.
 *
 * Body JSON:
 *   user_id       int     wajib
 *   password_baru string  wajib, min 8 karakter
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/UserModel.php';
require_once __DIR__ . '/../../models/PermintaanPasswordModel.php';
require_once __DIR__ . '/../../models/NotifikasiModel.php';

Auth::requireRole('admin');
$adminUser = Auth::getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid.']);
    exit;
}

$targetUserId = isset($input['user_id']) && is_numeric($input['user_id']) ? (int) $input['user_id'] : 0;
$passwordBaru = trim($input['password_baru'] ?? '');

// Validasi
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'user_id tidak valid.']);
    exit;
}
if (strlen($passwordBaru) < 8) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.']);
    exit;
}

try {
    $userModel = new UserModel();
    $targetUser = $userModel->findById($targetUserId);

    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User tidak ditemukan.']);
        exit;
    }

    if (!$targetUser['is_active']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Akun user tidak aktif.']);
        exit;
    }

    // Admin tidak bisa mereset password admin lain (keamanan)
    if ($targetUser['role'] === 'admin' && $targetUserId !== (int) $adminUser['user_id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Tidak dapat mereset password admin lain.']);
        exit;
    }

    // Hash dan update password
    $hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 10]);
    $userModel->updatePassword($targetUserId, $hashBaru);

    // Batalkan permintaan aktif jika ada
    $ppModel = new PermintaanPasswordModel();
    $ppModel->batalkanAktif($targetUserId);

    // Notifikasi ke user
    $namaTarget   = $targetUser['nama_guru'] ?? $targetUser['nama_siswa'] ?? 'Pengguna';
    $notifModel   = new NotifikasiModel();
    $notifModel->create(
        $targetUserId,
        'Password Direset oleh Admin',
        'Password akun Anda telah direset oleh Administrator. Silakan login dengan password baru Anda dan segera ganti jika diperlukan.'
    );

    echo json_encode([
        'status'  => 'success',
        'message' => "Password {$namaTarget} berhasil direset.",
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
