<?php
/**
 * GET /api/admin/dashboard-stats.php
 * Statistik semua kelas untuk dashboard admin
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    $ctrl = new AdminController();
    $data = $ctrl->dashboardStats();
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (\Exception $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
