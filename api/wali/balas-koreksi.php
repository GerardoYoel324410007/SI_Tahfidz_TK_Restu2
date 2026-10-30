<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * POST /api/wali/balas-koreksi.php
 * Balasan wali murid ke guru
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/WaliController.php';

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

try {
    $ctrl = new WaliController();
    $data = $ctrl->balasKoreksi($input);
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Balasan berhasil dikirim.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
