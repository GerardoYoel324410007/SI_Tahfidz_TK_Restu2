<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * GET /api/wali/detail-koreksi.php?setoran_id=X
 * Detail koreksi + catatan guru + detail kriteria
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/WaliController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $ctrl = new WaliController();
    $data = $ctrl->detailKoreksi();
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    $code = str_contains($e->getMessage(), 'tidak ditemukan') ? 404 : 400;
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
