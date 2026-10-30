<?php
/**
 * POST /api/auth/ajukan-ganti-password.php
 * User mengajukan permintaan ganti password — tidak langsung berlaku,
 * harus disetujui admin terlebih dahulu.
 *
 * Body JSON:
 *   password_baru   string  wajib, min 8 karakter
 *   konfirmasi      string  wajib, harus sama dengan password_baru
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/UserModel.php';
require_once __DIR__ . '/../../models/PermintaanPasswordModel.php';
require_once __DIR__ . '/../../models/NotifikasiModel.php';

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

// Admin tidak menggunakan alur ini — admin reset langsung via /api/admin/reset-password.php
$currentUser = Auth::getCurrentUser();
if ($currentUser['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin menggunakan endpoint reset langsung.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid (JSON diperlukan).']);
    exit;
}

$passwordBaru = $input['password_baru'] ?? '';
$konfirmasi   = $input['konfirmasi']    ?? '';

// Validasi
if (empty($passwordBaru) || empty($konfirmasi)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password baru dan konfirmasi wajib diisi.']);
    exit;
}
if (strlen($passwordBaru) < 8) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.']);
    exit;
}
if ($passwordBaru !== $konfirmasi) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Konfirmasi password tidak cocok.']);
    exit;
}

try {
    $userId = (int) $currentUser['user_id'];
    $ppModel = new PermintaanPasswordModel();

    // Cek apakah sudah ada permintaan aktif
    $aktif = $ppModel->getAktifByUserId($userId);
    if ($aktif) {
        http_response_code(409);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Anda sudah memiliki permintaan yang sedang menunggu persetujuan admin. Tunggu hingga diproses.',
        ]);
        exit;
    }

    // Hash password baru di server (admin tidak pernah melihat plaintext)
    $hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 10]);

    // Simpan permintaan
    $ppModel->create($userId, $hashBaru);

    // Kirim notifikasi ke semua admin
    $db = getDB();
    $stmtAdmin = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    $adminIds  = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);

    $namaUser      = $currentUser['nama'] ?: $currentUser['nomor_identitas'];
    $notifModel    = new NotifikasiModel();
    foreach ($adminIds as $adminId) {
        $notifModel->create(
            (int) $adminId,
            'Permintaan Ganti Password',
            "{$namaUser} mengajukan permintaan ganti password. Harap tinjau di halaman Manajemen Pengguna."
        );
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Permintaan ganti password berhasil dikirim. Mohon tunggu persetujuan admin.',
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server. Silakan coba lagi.']);
}
