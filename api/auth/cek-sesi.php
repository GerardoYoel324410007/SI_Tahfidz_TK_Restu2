<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * API Endpoint: Cek Sesi
 * GET /api/auth/cek-sesi.php
 *
 * Response:
 * { "logged_in": true, "role": "admin", "user_id": 1 }
 * { "logged_in": false }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$user = Auth::getCurrentUser();

if ($user) {
    echo json_encode([
        'logged_in' => true,
        'role' => $user['role'],
        'user_id' => $user['user_id'],
        'nama' => $user['nama']
    ]);
} else {
    echo json_encode(['logged_in' => false]);
}
