<?php
/**
 * DELETE /api/admin/hapus-pengguna.php?id=X&type=guru|siswa
 * Soft delete pengguna (set is_active = 0)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $ctrl = new AdminController();
    $data = $ctrl->hapusPengguna();
    echo json_encode(['status' => 'success', 'data' => $data, 'message' => 'Pengguna berhasil dinonaktifkan.']);
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
