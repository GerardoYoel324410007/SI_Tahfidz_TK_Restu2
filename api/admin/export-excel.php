<?php
/**
 * GET /api/admin/export-excel.php
 * Export data Guru / Siswa dari database ke file Excel
 * 
 * File ini mendukung round-trip edit:
 *   Export → Edit di Excel → Import kembali → UPDATE data
 * 
 * Kolom ID (kolom A) disertakan agar saat re-import sistem mengenali
 * bahwa ini adalah UPDATE, bukan INSERT baru.
 * 
 * Query params:
 *   type       = guru | siswa  (wajib)
 *   kelas_id   = int           (opsional, filter siswa per kelas)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$type = $_GET['type'] ?? '';
if (!in_array($type, ['guru', 'siswa'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter type harus guru atau siswa.']);
    exit;
}

$db = getDB();

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();

// ── Warna & Style ─────────────────────────────────────────────────────────
$headerStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '004323']],
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'AAAAAA']]],
];
$idColStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE']],
    'font' => ['bold' => true, 'color' => ['rgb' => '888888'], 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$infoStyle = [
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']],
    'font' => ['bold' => true, 'color' => ['rgb' => '5D4037']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
];
$rowEven = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAF9']]];
$rowOdd  = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']]];

$tanggalExport = date('d-m-Y H:i');

if ($type === 'guru') {
    $sheet->setTitle('Export Guru');

    // Ambil data guru dari DB
    $stmt = $db->query("
        SELECT
            g.id            AS guru_id,
            u.nomor_identitas,
            g.nama_lengkap,
            g.jabatan,
            g.status,
            g.nuptk,
            g.tempat_lahir,
            g.tanggal_lahir,
            g.pendidikan,
            g.no_telepon,
            g.alamat,
            u.is_active,
            k.nama_kelas
        FROM guru g
        JOIN users u ON u.id = g.user_id
        LEFT JOIN kelas k ON k.guru_id = g.id AND k.jenjang != 'TPA'
        ORDER BY g.nama_lengkap ASC
    ");
    $rows = $stmt->fetchAll();

    // Baris 1: Info
    $lastCol = 'M';
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', "EXPORT DATA GURU — Diekspor pada: {$tanggalExport} | Total: " . count($rows) . " guru | Jangan ubah kolom ID (A), hanya untuk referensi update");
    $sheet->getStyle('A1')->applyFromArray($infoStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Baris 2: Header
    $headers = [
        'A' => '[ID Sistem]',
        'B' => 'Nomor Identitas',
        'C' => 'Nama Lengkap *',
        'D' => 'Jabatan / Status',
        'E' => 'Status Kepegawaian',
        'F' => 'NUPTK / NIK',
        'G' => 'Tempat Lahir',
        'H' => 'Tanggal Lahir',
        'I' => 'Pendidikan',
        'J' => 'No Telepon',
        'K' => 'Alamat',
        'L' => 'Kelas Saat Ini',
        'M' => 'Status Akun',
    ];
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '2', $label);
    }
    $sheet->getStyle('A2:' . $lastCol . '2')->applyFromArray($headerStyle);
    // Kolom ID beda warna
    $sheet->getStyle('A2')->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '666666']],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(2)->setRowHeight(28);

    // Isi data
    foreach ($rows as $i => $row) {
        $r = $i + 3;
        $ttl = '';
        if (!empty($row['tempat_lahir'])) {
            $ttl = $row['tempat_lahir'];
            if (!empty($row['tanggal_lahir'])) {
                $ttl .= ', ' . date('d-m-Y', strtotime($row['tanggal_lahir']));
            }
        }
        $data = [
            'A' => $row['guru_id'],
            'B' => $row['nomor_identitas'],
            'C' => $row['nama_lengkap'],
            'D' => $row['jabatan'],
            'E' => $row['status'],
            'F' => $row['nuptk'],
            'G' => $row['tempat_lahir'],
            'H' => !empty($row['tanggal_lahir']) ? date('d-m-Y', strtotime($row['tanggal_lahir'])) : '',
            'I' => $row['pendidikan'],
            'J' => $row['no_telepon'],
            'K' => $row['alamat'],
            'L' => $row['nama_kelas'] ?? '-',
            'M' => $row['is_active'] ? 'Aktif' : 'Nonaktif',
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValue($col . $r, $val ?? '');
        }
        // Warna baris selang-seling
        $style = ($i % 2 === 0) ? $rowEven : $rowOdd;
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($style);
        // Kolom ID abu-abu
        $sheet->getStyle('A' . $r)->applyFromArray($idColStyle);
    }

    // Lebar kolom
    $widths = ['A'=>8, 'B'=>20, 'C'=>30, 'D'=>22, 'E'=>18, 'F'=>20, 'G'=>18, 'H'=>14, 'I'=>12, 'J'=>18, 'K'=>35, 'L'=>18, 'M'=>14];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    $sheet->freezePane('A3');

} else {
    // ── EXPORT SISWA ──────────────────────────────────────────────────────
    $sheet->setTitle('Export Siswa');

    $kelasId = $_GET['kelas_id'] ?? '';
    $whereKelas = '';
    $params = [];
    if (!empty($kelasId) && is_numeric($kelasId)) {
        $whereKelas = 'WHERE s.kelas_id = :kelas_id';
        $params[':kelas_id'] = (int)$kelasId;
    }

    $stmt = $db->prepare("
        SELECT
            s.id            AS siswa_id,
            s.nis,
            s.nama_lengkap,
            s.nama_panggilan,
            s.jenis_kelamin,
            k.nama_kelas,
            k.id            AS kelas_id,
            s.nama_wali,
            u.is_active
        FROM siswa s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        {$whereKelas}
        ORDER BY k.nama_kelas ASC, s.nama_lengkap ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $lastCol = 'I';
    $sheet->mergeCells('A1:' . $lastCol . '1');
    $sheet->setCellValue('A1', "EXPORT DATA SISWA — Diekspor pada: {$tanggalExport} | Total: " . count($rows) . " siswa | Jangan ubah kolom ID (A), hanya untuk referensi update");
    $sheet->getStyle('A1')->applyFromArray($infoStyle);
    $sheet->getRowDimension(1)->setRowHeight(30);

    $headers = [
        'A' => '[ID Sistem]',
        'B' => 'NIS / No Induk *',
        'C' => 'Nama Lengkap *',
        'D' => 'Nama Panggilan',
        'E' => 'L/P *',
        'F' => 'Kelas *',
        'G' => 'Nama Wali',
        'H' => 'Status Akun',
        'I' => 'ID Kelas (Sistem)',
    ];
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '2', $label);
    }
    $sheet->getStyle('A2:' . $lastCol . '2')->applyFromArray($headerStyle);
    $sheet->getStyle('A2')->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '666666']],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('I2')->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '666666']],
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    ]);
    $sheet->getRowDimension(2)->setRowHeight(28);

    foreach ($rows as $i => $row) {
        $r = $i + 3;
        $data = [
            'A' => $row['siswa_id'],
            'B' => $row['nis'],
            'C' => $row['nama_lengkap'],
            'D' => $row['nama_panggilan'],
            'E' => $row['jenis_kelamin'],
            'F' => $row['nama_kelas'],
            'G' => $row['nama_wali'],
            'H' => $row['is_active'] ? 'Aktif' : 'Nonaktif',
            'I' => $row['kelas_id'],
        ];
        foreach ($data as $col => $val) {
            $sheet->setCellValue($col . $r, $val ?? '');
        }
        $style = ($i % 2 === 0) ? $rowEven : $rowOdd;
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($style);
        $sheet->getStyle('A' . $r)->applyFromArray($idColStyle);
        $sheet->getStyle('I' . $r)->applyFromArray($idColStyle);
    }

    $widths = ['A'=>8, 'B'=>18, 'C'=>30, 'D'=>20, 'E'=>8, 'F'=>20, 'G'=>25, 'H'=>14, 'I'=>14];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    $sheet->freezePane('A3');
}

// ── Output ─────────────────────────────────────────────────────────────────
$filename = 'export_' . $type . '_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
