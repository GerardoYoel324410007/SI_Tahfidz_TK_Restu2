<?php
/**
 * POST /api/guru/presensi-input.php
 * Bulk input presensi harian kelas tahfidz (Senin-Kamis)
 */
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/PresensiController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ctrl  = new PresensiController();
    $data  = $ctrl->inputPresensi($input);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
