<?php
require_once __DIR__ . '/../../config/cors.php';
/**
 * API Endpoint: Logout
 * POST /api/auth/logout.php
 *
 * Response:
 * { "status": "success" }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::init();
session_unset();
session_destroy();
echo json_encode(['status' => 'success']);
