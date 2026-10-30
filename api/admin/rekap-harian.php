<?php
/**
 * GET /api/admin/rekap-harian.php?tanggal=YYYY-MM-DD&mode=detail
 * Rekap setoran semua kelas untuk tanggal tertentu.
 * mode=ringkasan (default): satu baris per kelas
 * mode=detail             : satu baris per siswa + info surat/ayat
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

    $tanggal = $_GET['tanggal'] ?? date('Y-m-d');
    $mode    = $_GET['mode'] ?? 'ringkasan';

    // Validasi format tanggal
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }

    if ($mode === 'detail') {
        // ---------------------------------------------------------------
        // MODE DETAIL: satu baris per siswa, dengan info surat yang disetorkan
        // ---------------------------------------------------------------
        // Setoran JOIN melalui tugas_hafalan karena nama_surat, ayat ada di sana
        $stmt = $db->prepare("
            SELECT
                k.kode_kelas,
                k.nama_kelas,
                k.jenjang,
                s.nis,
                s.nama_lengkap,
                s.nama_panggilan,
                t.nama_surat,
                t.ayat_dari,
                t.ayat_sampai,
                st.status,
                st.catatan_guru,
                st.waktu_kirim,
                st.waktu_koreksi AS waktu_verifikasi
            FROM kelas k
            INNER JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            LEFT JOIN setoran st ON st.siswa_id = s.id
                AND DATE(st.waktu_kirim) = :tanggal
            LEFT JOIN tugas_hafalan t ON t.id = st.tugas_id
            WHERE k.is_active = 1
            ORDER BY k.urutan ASC, k.nama_kelas ASC, s.nama_lengkap ASC, st.waktu_kirim ASC
        ");
        $stmt->execute([':tanggal' => $tanggal]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cast & format
        foreach ($rows as &$row) {
            $row['waktu_kirim']      = $row['waktu_kirim']      ? date('H:i', strtotime($row['waktu_kirim']))      : '-';
            $row['waktu_verifikasi'] = $row['waktu_verifikasi'] ? date('H:i', strtotime($row['waktu_verifikasi'])) : '-';
            $row['ayat_dari']        = $row['ayat_dari']    ?? '-';
            $row['ayat_sampai']      = $row['ayat_sampai']  ?? '-';
            $row['nama_surat']       = $row['nama_surat']   ?? '-';
            $row['status']           = $row['status']       ?? 'belum_kumpul';
            $row['catatan_guru']     = $row['catatan_guru'] ?? '-';
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'tanggal' => $tanggal,
                'mode'    => 'detail',
                'siswa'   => $rows
            ]
        ]);

    } else {
        // ---------------------------------------------------------------
        // MODE RINGKASAN: satu baris per kelas (default)
        // BUGFIX: persentase dihitung dari 'diterima', BUKAN 'sudah_kumpul'
        // ---------------------------------------------------------------
        $stmt = $db->prepare("
            SELECT
                k.kode_kelas,
                k.nama_kelas,
                k.jenjang,
                COUNT(DISTINCT s.id) AS total_siswa,
                COUNT(DISTINCT CASE WHEN st.id IS NOT NULL THEN s.id END) AS sudah_kumpul,
                COUNT(DISTINCT CASE WHEN st.status = 'diterima'  THEN s.id END) AS diterima,
                COUNT(DISTINCT CASE WHEN st.status = 'menunggu'  THEN s.id END) AS menunggu,
                COUNT(DISTINCT CASE WHEN st.status = 'perbaiki'  THEN s.id END) AS perbaiki,
                COUNT(DISTINCT CASE WHEN st.id IS NULL            THEN s.id END) AS belum_kumpul
            FROM kelas k
            LEFT JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            LEFT JOIN setoran st ON st.siswa_id = s.id
                AND DATE(st.waktu_kirim) = :tanggal
            WHERE k.is_active = 1
            GROUP BY k.id, k.kode_kelas, k.nama_kelas, k.jenjang
            ORDER BY k.urutan ASC, k.nama_kelas ASC
        ");
        $stmt->execute([':tanggal' => $tanggal]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $row['total_siswa']  = (int) $row['total_siswa'];
            $row['sudah_kumpul'] = (int) $row['sudah_kumpul'];
            $row['diterima']     = (int) $row['diterima'];
            $row['menunggu']     = (int) $row['menunggu'];
            $row['perbaiki']     = (int) $row['perbaiki'];
            $row['belum_kumpul'] = (int) $row['belum_kumpul'];
            // BUGFIX: pakai 'diterima' bukan 'sudah_kumpul' agar persentase = yang benar-benar tuntas
            $row['persentase']   = $row['total_siswa'] > 0
                ? round(($row['diterima'] / $row['total_siswa']) * 100) . '%'
                : '0%';
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'tanggal' => $tanggal,
                'mode'    => 'ringkasan',
                'kelas'   => $data
            ]
        ]);
    }

} catch (\Exception $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
