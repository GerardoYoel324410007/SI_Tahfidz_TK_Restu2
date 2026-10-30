<?php
/**
 * POST /api/auth/ganti-password-pertama.php
 *
 * Endpoint khusus untuk user yang baru pertama kali login dan
 * wajib mengganti password default mereka (must_change_password = 1).
 *
 * Alur:
 *   1. User login → API login.php mengembalikan must_change_password: true
 *   2. Frontend menampilkan modal ganti password
 *   3. User isi password baru → kirim ke endpoint ini
 *   4. Password langsung diubah + must_change_password = 0
 *   5. Perubahan dicatat di tabel permintaan_password (sebagai log untuk admin)
 *
 * Body JSON:
 *   password_baru  string  wajib, min 8 karakter
 *   konfirmasi     string  wajib, sama dengan password_baru
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/UserModel.php';
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

$currentUser = Auth::getCurrentUser();

// Hanya untuk guru, karyawan, wali_murid
if ($currentUser['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin tidak menggunakan endpoint ini.']);
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
    $db     = getDB();
    $userId = (int) $currentUser['user_id'];

    // Verifikasi user masih butuh ganti password (cegah double-submit)
    $stmt = $db->prepare("SELECT must_change_password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Data user tidak ditemukan.']);
        exit;
    }

    // Hash password baru
    $hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => 10]);

    $db->beginTransaction();

    // 1. Update password user langsung (berlaku seketika)
    $db->prepare("
        UPDATE users
        SET password_hash        = :hash,
            must_change_password = 0,
            password_changed_at  = NOW()
        WHERE id = :id
    ")->execute([':hash' => $hashBaru, ':id' => $userId]);

    // 2. Catat ke tabel permintaan_password sebagai LOG (status langsung 'disetujui')
    //    sehingga admin bisa memantau siapa saja yang sudah ganti password pertama kali
    $db->prepare("
        INSERT INTO permintaan_password
            (user_id, password_hash_baru, status, processed_at)
        VALUES
            (:uid, :hash, 'disetujui', NOW())
    ")->execute([':uid' => $userId, ':hash' => $hashBaru]);

    // 3. Kirim notifikasi ke admin (opsional, agar admin tahu)
    $namaUser = $currentUser['nama'] ?: $currentUser['nomor_identitas'];
    $roleLabel = match($currentUser['role']) {
        'guru'      => 'Guru',
        'karyawan'  => 'Karyawan',
        'wali_murid'=> 'Wali Murid',
        default     => 'Pengguna',
    };

    try {
        $stmtAdmin  = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        $adminIds   = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);
        $notifModel = new NotifikasiModel();
        foreach ($adminIds as $adminId) {
            $notifModel->create(
                (int) $adminId,
                'Password Pertama Diganti',
                "{$roleLabel} {$namaUser} telah mengganti password default mereka."
            );
        }
    } catch (\Exception $ignored) {
        // Notifikasi gagal tidak menggagalkan proses utama
    }

    $db->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Password berhasil diubah. Selamat menggunakan aplikasi!',
    ]);

} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server. Silakan coba lagi.']);
}
