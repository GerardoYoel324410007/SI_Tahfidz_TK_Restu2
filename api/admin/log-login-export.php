<?php
/**
 * GET /api/admin/log-login-export.php
 * Export log login ke file CSV.
 * Query params sama dengan log-login.php (tanpa page/per_page).
 * Limit keras 5000 baris.
 */

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

$filters = [
    'tanggal_dari'   => $_GET['tanggal_dari']   ?? date('Y-m-d'),
    'tanggal_sampai' => $_GET['tanggal_sampai'] ?? date('Y-m-d'),
    'role'           => $_GET['role']    ?? '',
    'status'         => $_GET['status']  ?? '',
    'search'         => trim($_GET['search'] ?? ''),
];

foreach (['tanggal_dari', 'tanggal_sampai'] as $key) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
        $filters[$key] = date('Y-m-d');
    }
}

try {
    $model = new LoginLogModel();
    $rows  = $model->getAllForExport($filters);

    $filename = 'log-login_' . $filters['tanggal_dari'] . '_sd_' . $filters['tanggal_sampai'] . '_export-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    header('Pragma: no-cache');

    // BOM untuk Excel agar UTF-8 terbaca dengan benar
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Header CSV
    fputcsv($out, ['No', 'Waktu Login', 'Nama', 'Nomor Identitas', 'Role', 'Status', 'Alasan Gagal', 'IP Address', 'Browser / Device']);

    $roleLabel = [
        'admin'      => 'Admin',
        'guru'       => 'Guru',
        'karyawan'   => 'Karyawan',
        'wali_murid' => 'Wali Murid',
    ];

    $alasanLabel = [
        'password_salah'       => 'Password salah',
        'akun_nonaktif'        => 'Akun nonaktif',
        'user_tidak_ditemukan' => 'User tidak ditemukan',
    ];

    foreach ($rows as $i => $row) {
        fputcsv($out, [
            $i + 1,
            $row['created_at'],
            $row['nama']            ?? '-',
            $row['nomor_identitas'],
            $roleLabel[$row['role'] ?? ''] ?? ($row['role'] ?? '-'),
            ucfirst($row['status']),
            $alasanLabel[$row['alasan_gagal'] ?? ''] ?? ($row['alasan_gagal'] ?? '-'),
            $row['ip_address']  ?? '-',
            $row['user_agent']  ?? '-',
        ]);
    }

    fclose($out);

} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Gagal export: ' . $e->getMessage()]);
}
