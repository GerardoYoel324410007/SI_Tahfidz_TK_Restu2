<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * GET /api/wali/pengumuman.php
 * Pengumuman terbaru untuk kelas siswa
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
    $data = $ctrl->pengumuman();
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
