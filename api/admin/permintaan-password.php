<?php
/**
 * /api/admin/permintaan-password.php
 *
 * GET  → Daftar permintaan ganti password (dengan filter status & paginasi)
 * POST → Proses permintaan: approve atau reject
 *
 * GET params:
 *   status   = menunggu | disetujui | ditolak | (kosong = semua)
 *   page     = int (default 1)
 *   per_page = int (default 20)
 *
 * POST body JSON:
 *   action  = 'approve' | 'reject'  (wajib)
 *   id      = int  (wajib, ID permintaan)
 *   alasan  = string  (wajib jika action=reject)
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/PermintaanPasswordModel.php';
require_once __DIR__ . '/../../models/NotifikasiModel.php';

Auth::requireRole('admin');
$adminUser = Auth::getCurrentUser();
$adminId   = (int) $adminUser['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: ambil daftar permintaan ──────────────────────────────────────────────
if ($method === 'GET') {
    $status  = $_GET['status']   ?? '';
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 20)));

    // Validasi nilai status
    $validStatus = ['', 'menunggu', 'disetujui', 'ditolak'];
    if (!in_array($status, $validStatus)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nilai status tidak valid.']);
        exit;
    }

    try {
        $ppModel = new PermintaanPasswordModel();
        $result  = $ppModel->getAll($status, $page, $perPage);
        $pending = $ppModel->countPending();

        // Bersihkan hash dari output
        foreach ($result['data'] as &$row) {
            unset($row['password_hash_baru']);
        }
        unset($row);

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'permintaan'   => $result['data'],
                'pagination'   => $result['pagination'],
                'total_pending' => $pending,
            ],
        ]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil data: ' . $e->getMessage()]);
    }
    exit;
}

// ── POST: approve / reject ────────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid.']);
        exit;
    }

    $action = trim($input['action'] ?? '');
    $id     = isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0;

    if (!in_array($action, ['approve', 'reject']) || $id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Parameter action dan id wajib dan valid.']);
        exit;
    }

    try {
        $ppModel    = new PermintaanPasswordModel();
        $notifModel = new NotifikasiModel();

        // Ambil data permintaan untuk keperluan notifikasi
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT pp.user_id,
                   COALESCE(g.nama_lengkap, s.nama_lengkap, 'Pengguna') AS nama_user
            FROM permintaan_password pp
            JOIN  users u ON u.id = pp.user_id
            LEFT JOIN guru  g ON g.user_id = u.id
            LEFT JOIN siswa s ON s.user_id = u.id
            WHERE pp.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $ppData = $stmt->fetch();

        if (!$ppData) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Permintaan tidak ditemukan.']);
            exit;
        }

        $targetUserId = (int) $ppData['user_id'];

        if ($action === 'approve') {
            $ppModel->approve($id, $adminId);

            // Notifikasi ke user
            $notifModel->create(
                $targetUserId,
                'Password Berhasil Diubah',
                'Permintaan ganti password Anda telah disetujui oleh Admin. Password baru Anda sudah aktif.'
            );

            echo json_encode(['status' => 'success', 'message' => 'Permintaan disetujui dan password berhasil diubah.']);

        } else {
            // reject — alasan wajib
            $alasan = trim($input['alasan'] ?? '');
            if (empty($alasan)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Alasan penolakan wajib diisi.']);
                exit;
            }

            $ppModel->reject($id, $adminId, $alasan);

            // Notifikasi ke user
            $notifModel->create(
                $targetUserId,
                'Permintaan Password Ditolak',
                "Permintaan ganti password Anda ditolak Admin dengan alasan: {$alasan}. Anda dapat mengajukan permintaan baru."
            );

            echo json_encode(['status' => 'success', 'message' => 'Permintaan telah ditolak.']);
        }

    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal memproses: ' . $e->getMessage()]);
    }
    exit;
}

// Method lain tidak didukung
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
