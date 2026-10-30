<?php
/**
 * POST /api/wali/kirim-setoran.php
 * Upload audio hafalan siswa
 * Content-Type: multipart/form-data
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/WaliController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

if (empty($_FILES['file_audio'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File audio wajib diunggah.']);
    exit;
}

try {
    $ctrl = new WaliController();
    $data = $ctrl->kirimSetoran($_FILES);
    http_response_code(201);
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Setoran berhasil dikirim.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
