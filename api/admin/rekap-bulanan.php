<?php
/**
 * GET /api/admin/rekap-bulanan.php?bulan=X&tahun=Y[&kelas_id=N]
 * Rekap setoran per siswa untuk bulan & tahun tertentu.
 * Optional: kelas_id untuk filter per kelas.
 * Persentase ketuntasan dihitung dari: diterima / total_setoran × 100
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

try {
    Auth::requireRole('admin');
    $db = getDB();

    $bulan    = max(1, min(12, (int) ($_GET['bulan']  ?? date('n'))));
    $tahun    = max(2020, min(2099, (int) ($_GET['tahun'] ?? date('Y'))));
    $kelasId  = isset($_GET['kelas_id']) && (int) $_GET['kelas_id'] > 0
                ? (int) $_GET['kelas_id']
                : null;

    // Build WHERE filter kelas opsional
    $whereKelas = $kelasId ? 'AND k.id = :kelas_id' : '';

    $params = [':bulan' => $bulan, ':tahun' => $tahun];
    if ($kelasId) $params[':kelas_id'] = $kelasId;

    $stmt = $db->prepare("
        SELECT
            k.kode_kelas,
            k.nama_kelas,
            k.jenjang,
            s.nis,
            s.nama_lengkap,
            s.nama_panggilan,
            COUNT(DISTINCT st.id)                                           AS total_setoran,
            COUNT(DISTINCT CASE WHEN st.status = 'diterima' THEN st.id END) AS diterima,
            COUNT(DISTINCT CASE WHEN st.status = 'menunggu' THEN st.id END) AS menunggu,
            COUNT(DISTINCT CASE WHEN st.status = 'perbaiki' THEN st.id END) AS perbaiki
        FROM kelas k
        INNER JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
        LEFT JOIN setoran st ON st.siswa_id = s.id
            AND MONTH(st.waktu_kirim) = :bulan
            AND YEAR(st.waktu_kirim)  = :tahun
        WHERE k.is_active = 1
          {$whereKelas}
        GROUP BY k.id, k.kode_kelas, k.nama_kelas, k.jenjang,
                 s.id, s.nis, s.nama_lengkap, s.nama_panggilan
        ORDER BY k.urutan ASC, k.nama_kelas ASC, s.nama_lengkap ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by kelas
    $grouped = [];
    foreach ($rows as $row) {
        $kode = $row['kode_kelas'];
        if (!isset($grouped[$kode])) {
            $grouped[$kode] = [
                'kode_kelas'       => $row['kode_kelas'],
                'nama_kelas'       => $row['nama_kelas'],
                'jenjang'          => $row['jenjang'],
                'total_siswa'      => 0,
                'total_setoran'    => 0,
                'total_diterima'   => 0,
                'total_menunggu'   => 0,
                'total_perbaiki'   => 0,
                'siswa'            => []
            ];
        }

        $tot = (int) $row['total_setoran'];
        $dit = (int) $row['diterima'];

        $grouped[$kode]['total_siswa']++;
        $grouped[$kode]['total_setoran']  += $tot;
        $grouped[$kode]['total_diterima'] += $dit;
        $grouped[$kode]['total_menunggu'] += (int) $row['menunggu'];
        $grouped[$kode]['total_perbaiki'] += (int) $row['perbaiki'];

        $grouped[$kode]['siswa'][] = [
            'nis'             => $row['nis'],
            'nama_lengkap'    => $row['nama_lengkap'],
            'nama_panggilan'  => $row['nama_panggilan'],
            'total_setoran'   => $tot,
            'diterima'        => $dit,
            'menunggu'        => (int) $row['menunggu'],
            'perbaiki'        => (int) $row['perbaiki'],
            'persen_ketuntasan' => $tot > 0 ? round(($dit / $tot) * 100) : 0,
        ];
    }

    // Hitung persen ketuntasan per kelas
    $kelasList = array_values($grouped);
    foreach ($kelasList as &$k) {
        $k['persen_ketuntasan'] = $k['total_setoran'] > 0
            ? round(($k['total_diterima'] / $k['total_setoran']) * 100)
            : 0;
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'bulan'  => $bulan,
            'tahun'  => $tahun,
            'kelas'  => $kelasList
        ]
    ]);
} catch (\Exception $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
