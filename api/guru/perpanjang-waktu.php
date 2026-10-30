<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * POST /api/guru/perpanjang-waktu.php
 * Perpanjang deadline siswa
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/GuruController.php';

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
    $ctrl = new GuruController();
    $data = $ctrl->perpanjangWaktu($input);
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Perpanjangan berhasil disimpan.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
