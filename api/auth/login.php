<?php
/**
 * API Endpoint: Login
 * POST /api/auth/login.php
 */

// Suppress warnings yang mengganggu output JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../config/cors.php';



require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/LoginLogModel.php';

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Request body tidak valid.']);
    exit;
}

$nomor_identitas = trim($input['nomor_identitas'] ?? '');
$password = $input['password'] ?? '';

// Validasi input
if (empty($nomor_identitas) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nomor identitas dan password wajib diisi.']);
    exit;
}

// Validasi format: maks 50 karakter
if (strlen($nomor_identitas) > 50) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nomor identitas maksimal 50 karakter.']);
    exit;
}

// Validasi password minimal 6 karakter
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter.']);
    exit;
}

// Rate limiting berbasis file (Dinonaktifkan untuk mempermudah pengujian/tidak ada limit)
// require_once __DIR__ . '/../../config/rate_limiter.php';
// $rateLimitKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
// $rateCheck = RateLimiter::check($rateLimitKey, 5, 900);
// if (!$rateCheck['allowed']) {
//     http_response_code(429);
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . ceil($rateCheck['retry_after'] / 60) . ' menit.'
//     ]);
//     exit;
// }

// Proses login
$result = Auth::login($nomor_identitas, $password);

if ($result['status'] === 'error') {
    http_response_code(401);
} else {
    // Reset rate limit pada login berhasil jika kelas dan key tersedia (mencegah fatal error saat dinonaktifkan)
    if (class_exists('RateLimiter') && isset($rateLimitKey)) {
        RateLimiter::reset($rateLimitKey);
    }
}

echo json_encode($result);
