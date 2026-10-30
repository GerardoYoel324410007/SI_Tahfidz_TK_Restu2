<?php
/**
 * GET /api/admin/daftar-siswa.php?kelas_id=X&search=Y
 * List siswa per kelas dengan search
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/SiswaModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    Auth::requireAnyRole(['admin', 'guru']);
    $siswaModel = new SiswaModel();
    $kelasId = !empty($_GET['kelas_id']) ? (int) $_GET['kelas_id'] : null;
    $search = $_GET['search'] ?? null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(10000, max(1, (int) ($_GET['per_page'] ?? 20)));
    
    $data = $siswaModel->getAll($kelasId, $search, $page, $perPage);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
