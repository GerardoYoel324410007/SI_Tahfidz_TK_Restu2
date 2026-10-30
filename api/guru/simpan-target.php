<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * POST /api/guru/simpan-target.php
 * Buat tugas hafalan baru + upload audio panduan
 * Content-Type: multipart/form-data atau application/json
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

// Support both JSON dan multipart/form-data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'multipart/form-data')) {
    $input = $_POST;
    $files = $_FILES;
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $files = [];
}

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid.']);
    exit;
}

try {
    $ctrl = new GuruController();
    $data = $ctrl->simpanTarget($input, $files);
    http_response_code(201);
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Tugas hafalan berhasil dibuat.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
