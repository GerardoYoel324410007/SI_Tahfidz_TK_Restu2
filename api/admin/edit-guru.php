<?php
/**
 * PUT /api/admin/edit-guru.php?id=X
 * Update data guru
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    $ctrl = new AdminController();
    $data = $ctrl->editGuru($input);
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Data guru berhasil diperbarui.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
