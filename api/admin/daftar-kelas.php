<?php
/**
 * GET /api/admin/daftar-kelas.php
 * List semua kelas (kecuali TPA secara default)
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/KelasModel.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    Auth::requireAnyRole(['admin', 'guru']);
    $kelasModel = new KelasModel();
    $includeTpa = isset($_GET['include_tpa']) && $_GET['include_tpa'] === '1';
    $data = $kelasModel->getAll(!$includeTpa);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
