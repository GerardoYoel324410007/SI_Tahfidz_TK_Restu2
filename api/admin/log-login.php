<?php
/**
 * GET /api/admin/log-login.php
 * Ambil log aktivitas login dengan filter dan paginasi.
 * Hanya bisa diakses oleh admin.
 *
 * Query params:
 *   tanggal_dari   Y-m-d  (default: hari ini)
 *   tanggal_sampai Y-m-d  (default: hari ini)
 *   role           guru | wali_murid | karyawan | admin | (kosong=semua)
 *   status         berhasil | gagal | (kosong=semua)
 *   search         string (cocokkan nama atau nomor identitas)
 *   page           int    (default 1)
 *   per_page       int    (default 50, max 200)
 *
 * Response JSON:
 *   { status, data: { logs, summary, pagination } }
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../models/LoginLogModel.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Sanitasi input
$filters = [
    'tanggal_dari'    => $_GET['tanggal_dari']   ?? date('Y-m-d'),
    'tanggal_sampai'  => $_GET['tanggal_sampai'] ?? date('Y-m-d'),
    'role'            => $_GET['role']    ?? '',
    'status'          => $_GET['status']  ?? '',
    'search'          => trim($_GET['search'] ?? ''),
];

// Validasi format tanggal
foreach (['tanggal_dari', 'tanggal_sampai'] as $key) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
        $filters[$key] = date('Y-m-d');
    }
}

// Pastikan dari <= sampai
if ($filters['tanggal_dari'] > $filters['tanggal_sampai']) {
    $filters['tanggal_sampai'] = $filters['tanggal_dari'];
}

$validRoles = ['', 'admin', 'guru', 'karyawan', 'wali_murid'];
if (!in_array($filters['role'], $validRoles)) $filters['role'] = '';

$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(200, max(10, (int) ($_GET['per_page'] ?? 50)));

try {
    $model   = new LoginLogModel();
    $result  = $model->getAll($filters, $page, $perPage);
    $summary = $model->getSummary($filters);

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'logs'       => $result['data'],
            'summary'    => $summary,
            'pagination' => $result['pagination'],
        ],
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengambil log: ' . $e->getMessage()]);
}
