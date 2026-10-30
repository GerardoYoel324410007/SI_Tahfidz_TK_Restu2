<?php
/**
 * CORS Helper — Sistem Tahfidz Restu 2
 * Include file ini di SEMUA endpoint PHP API yang butuh sesi dan akses dari React frontend.
 * 
 * Cara pakai: require_once __DIR__ . '/../config/cors.php';
 */

date_default_timezone_set('Asia/Jakarta');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (!empty($origin)) {
    $allowed_origins = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost',
        'http://localhost:5173',
    ];
    
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    } else {
        // Fallback selama development
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

// Jangan set Content-Type JSON untuk endpoint download file
// (download-template.php dan export-excel.php mengirim header Content-Type sendiri)
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isDownloadEndpoint = in_array($currentScript, ['download-template.php', 'export-excel.php']);

if (!$isDownloadEndpoint) {
    header('Content-Type: application/json');
}
header('X-Content-Type-Options: nosniff');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
