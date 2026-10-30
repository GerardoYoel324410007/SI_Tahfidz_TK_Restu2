<?php
/**
 * GET /api/admin/download-template.php
 * Download file template Excel (.xlsx) untuk import data Guru / Siswa
 *
 * Versi 2.1: Template dinamis, validasi dropdown, sheet panduan
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$type = $_GET['type'] ?? '';
if (!in_array($type, ['guru', 'siswa'])) {
    http_response_code(400);
    echo "Tipe template tidak valid. Gunakan type=guru atau type=siswa.";
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

// Increase memory & time limit untuk proses spreadsheet
ini_set('memory_limit', '256M');
set_time_limit(120);

// ── Ambil daftar kelas aktif dari database ─────────────────────────────────
$db = getDB();
$kelasList = [];
try {
    $stmtKelas = $db->query("SELECT id, kode_kelas, nama_kelas FROM kelas WHERE is_active = 1 ORDER BY nama_kelas ASC");
    while ($k = $stmtKelas->fetch(PDO::FETCH_ASSOC)) {
        $kelasList[] = $k;
    }
} catch (\Exception $e) {
    // DB error: lanjutkan dengan kelas kosong, pakai fallback
}

$spreadsheet = new Spreadsheet();

// ── Helper: apply header style ─────────────────────────────────────────────
function applyHeaderStyle($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '004323'],
        ],
        'font' => [
            'bold'  => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size'  => 11,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'AAAAAA'],
            ],
        ],
    ]);
}

function applyWarningStyle($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFF8E1'],
        ],
        'font' => [
            'bold'  => true,
            'size'  => 10,
            'color' => ['rgb' => '5D4037'],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
        ],
    ]);
}

function applySampleStyle($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'fill' => [
            'fillType'   => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F1F8E9'],
        ],
        'font' => [
            'italic' => true,
            'color'  => ['rgb' => '558B2F'],
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'CCCCCC'],
            ],
        ],
    ]);
}

// ── Buat Data Validation helper ─────────────────────────────────────────────
/**
 * Pasang DataValidation LIST pada satu kolom, baris $startRow s.d. $endRow.
 * Pakai sqref (range) agar lebih efisien daripada loop per-cell.
 */
function addListValidation($sheet, $col, $startRow, $endRow, $formula, $promptMsg = '', $allowBlank = true, $errorStyle = DataValidation::STYLE_INFORMATION) {
    $range = $col . $startRow . ':' . $col . $endRow;
    // PhpSpreadsheet: pasang validation lewat getCell range
    $validation = $sheet->getDataValidation($range);
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle($errorStyle);
    $validation->setAllowBlank($allowBlank);
    $validation->setShowDropDown(false); // false = tampilkan dropdown arrow di Excel
    $validation->setFormula1($formula);
    if ($promptMsg) {
        $validation->setShowInputMessage(true);
        $validation->setPrompt($promptMsg);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// SHEET PANDUAN (Sheet Index 1)
// ══════════════════════════════════════════════════════════════════════════════
$guideSheet = $spreadsheet->createSheet(1);
$guideSheet->setTitle('Panduan Pengisian');

if ($type === 'guru') {
    $panduan = [
        ['PANDUAN PENGISIAN TEMPLATE IMPORT DATA GURU'],
        [''],
        ['No', 'Nama Kolom', 'Keterangan', 'Contoh Isian', 'Wajib?'],
        [1,  'No',                     'Nomor urut (boleh dikosongkan)',                           '1',                       'Tidak'],
        [2,  'Nama Lengkap *',         'Nama guru beserta gelar akademik',                         'Ahmad Fauzi, S.Pd',       'YA'],
        [3,  'Status *',               'Pilih dari dropdown: Guru / Karyawan / dll',               'Guru',                    'YA'],
        [4,  'NUPTK / NIK',           'NUPTK 16 digit, atau NIK KTP sebagai alternatif',          '1234567890123456',        'Tidak'],
        [5,  'Tempat, Tanggal Lahir', 'Format: Kota, DD-MM-YYYY atau Kota, DD Bulan YYYY',        'Malang, 12-12-1990',      'Tidak'],
        [6,  'Pendidikan',            'Pilih dari dropdown jenjang pendidikan terakhir',           'S1',                      'Tidak'],
        [7,  'Golongan',              'Golongan ASN (isi - jika tidak ada)',                       '-',                       'Tidak'],
        [8,  'Jabatan',               'Jabatan di sekolah',                                        'Wali Kelas TKA A1',       'Tidak'],
        [9,  'TMT',                   'Tanggal Mulai Tugas',                                       '01-07-2020',              'Tidak'],
        [10, 'Sertifikasi',           'Status sertifikasi (Sudah/Belum)',                          'Belum',                   'Tidak'],
        [11, 'Alamat',                'Alamat lengkap tempat tinggal',                             'Jl. Mawar No.12, Malang', 'Tidak'],
        [12, 'No Telepon',            'Nomor WhatsApp aktif, format 08xxx atau 628xxx',            '081234567890',            'Tidak'],
        [''],
        ['CATATAN PENTING:'],
        ['• Jika NUPTK/NIK kosong, sistem akan membuat nomor identitas otomatis (GURxxxxxx)'],
        ['• Password default saat import: 12345678'],
        ['• Baris dengan nama kosong dilewati otomatis'],
        ['• Duplikat berdasarkan NUPTK/NIK dilewati (bisa force update saat preview)'],
    ];
} else {
    $panduan = [
        ['PANDUAN PENGISIAN TEMPLATE IMPORT DATA SISWA'],
        [''],
        ['No', 'Nama Kolom', 'Keterangan', 'Contoh Isian', 'Wajib?'],
        [1, 'No',             'Nomor urut (boleh dikosongkan)',               '1',              'Tidak'],
        [2, 'NIS / No Induk *', 'Nomor Induk Siswa, HARUS UNIK, jadi username', '10214',         'YA'],
        [3, 'Nama Lengkap *', 'Nama lengkap siswa',                          'Abdullah Faqih', 'YA'],
        [4, 'Nama Panggilan', 'Nama sehari-hari/panggilan siswa',             'Faqih',          'Tidak'],
        [5, 'L/P *',          'Pilih dari dropdown: L atau P',                'L',              'YA'],
        [6, 'Kelas *',        'Pilih dari dropdown kelas yang tersedia',      'TKA A1',         'YA'],
        [7, 'Nama Wali',      'Nama orang tua / wali murid',                  'Bapak Fauzan',   'Tidak'],
        [''],
        ['CATATAN PENTING:'],
        ['• NIS digunakan sebagai username login wali murid'],
        ['• Password default saat import: NIS itu sendiri'],
        ['• Duplikat berdasarkan NIS dilewati'],
        ['• Pastikan kelas yang diisi SESUAI pilihan dropdown kolom F'],
    ];
}

foreach ($panduan as $ri => $row) {
    foreach ((array)$row as $ci => $val) {
        $guideSheet->setCellValueByColumnAndRow($ci + 1, $ri + 1, $val ?? '');
    }
}

// Style baris judul & header tabel
$guideSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '004323']],
]);
$guideSheet->getStyle('A3:E3')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '004323']],
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
]);

foreach ($guideSheet->getColumnIterator('A', 'E') as $col) {
    $guideSheet->getColumnDimension($col->getColumnIndex())->setAutoSize(true);
}

// ══════════════════════════════════════════════════════════════════════════════
// SHEET DATA UTAMA (Sheet Index 0)
// ══════════════════════════════════════════════════════════════════════════════
$spreadsheet->setActiveSheetIndex(0);
$sheet = $spreadsheet->getActiveSheet();

$DATA_ROWS = 300; // Jumlah baris data yang disiapkan (bukan 500 agar tidak berat)
$dataStart = 3;   // Data dimulai baris ke-3
$dataEnd   = $dataStart + $DATA_ROWS - 1;

if ($type === 'guru') {
    $sheet->setTitle('Import Guru');
    $lastCol = 'L';

    // Baris 1: Instruksi
    $sheet->mergeCells('A1:L1');
    $sheet->setCellValue('A1', 'PETUNJUK: Isi data mulai baris ke-3. Baris 1 & 2 JANGAN dihapus. Kolom bertanda * wajib diisi. Lihat sheet "Panduan Pengisian" untuk keterangan kolom.');
    applyWarningStyle($sheet, 'A1');
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Baris 2: Header
    $headers = ['A'=>'No', 'B'=>'Nama Lengkap *', 'C'=>'Status *', 'D'=>'NUPTK / NIK',
                'E'=>'Tempat, Tanggal Lahir', 'F'=>'Pendidikan', 'G'=>'Golongan',
                'H'=>'Jabatan', 'I'=>'TMT', 'J'=>'Sertifikasi', 'K'=>'Alamat', 'L'=>'No Telepon'];
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '2', $label);
    }
    applyHeaderStyle($sheet, 'A2:L2');
    $sheet->getRowDimension(2)->setRowHeight(28);

    // Baris 3: Contoh data (italic hijau)
    $sample = [1, 'Ahmad Fauzi, S.Pd', 'Guru', '1234567890123456', 'Malang, 12-12-1990',
               'S1', '-', 'Wali Kelas TKA A1', '-', 'Belum', 'Jl. Mawar No. 12, Malang', '081234567890'];
    foreach ($sample as $ci => $val) {
        $sheet->setCellValueByColumnAndRow($ci + 1, 3, $val);
    }
    applySampleStyle($sheet, 'A3:L3');

    // DataValidation kolom C (Status) — WAJIB
    addListValidation(
        $sheet, 'C', $dataStart, $dataEnd,
        '"Guru,Karyawan,Kepala Sekolah,Guru TPA"',
        'Pilih status kepegawaian',
        false,
        DataValidation::STYLE_INFORMATION
    );

    // DataValidation kolom F (Pendidikan) — opsional
    addListValidation(
        $sheet, 'F', $dataStart, $dataEnd,
        '"S1,S2,S3,D4,D3,SMA/SMK,MA"',
        'Pilih jenjang pendidikan',
        true,
        DataValidation::STYLE_INFORMATION
    );

    // Lebar kolom
    $colWidths = ['A'=>6, 'B'=>30, 'C'=>16, 'D'=>20, 'E'=>28, 'F'=>14,
                  'G'=>12, 'H'=>26, 'I'=>14, 'J'=>14, 'K'=>36, 'L'=>18];
    foreach ($colWidths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }

} else {
    // ── TEMPLATE SISWA ──────────────────────────────────────────────────────
    $sheet->setTitle('Import Siswa');
    $lastCol = 'G';

    // Baris 1: Instruksi
    $sheet->mergeCells('A1:G1');
    $sheet->setCellValue('A1', 'PETUNJUK: Isi data mulai baris ke-3. Baris 1 & 2 JANGAN dihapus. Kolom bertanda * wajib diisi. Gunakan dropdown untuk kolom L/P dan Kelas.');
    applyWarningStyle($sheet, 'A1');
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Baris 2: Header
    $headers = ['A'=>'No', 'B'=>'NIS / No Induk *', 'C'=>'Nama Lengkap *',
                'D'=>'Nama Panggilan', 'E'=>'L/P *', 'F'=>'Kelas *', 'G'=>'Nama Wali'];
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '2', $label);
    }
    applyHeaderStyle($sheet, 'A2:G2');
    $sheet->getRowDimension(2)->setRowHeight(28);

    // Baris 3: Contoh data
    $contohKelas = !empty($kelasList) ? $kelasList[0]['nama_kelas'] : 'TKA A1';
    $sample = [1, '10214', 'Abdullah Faqih', 'Faqih', 'L', $contohKelas, 'Bapak Fauzan'];
    foreach ($sample as $ci => $val) {
        $sheet->setCellValueByColumnAndRow($ci + 1, 3, $val);
    }
    applySampleStyle($sheet, 'A3:G3');

    // DataValidation kolom E (L/P) — WAJIB
    addListValidation(
        $sheet, 'E', $dataStart, $dataEnd,
        '"L,P"',
        'L = Laki-laki, P = Perempuan',
        false,
        DataValidation::STYLE_STOP
    );

    // DataValidation kolom F (Kelas)
    if (!empty($kelasList)) {
        // Buat sheet referensi tersembunyi untuk daftar kelas
        $refSheet = $spreadsheet->createSheet(2);
        $refSheet->setTitle('_kelas_ref');
        $refSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);

        foreach ($kelasList as $ri => $kelas) {
            $refSheet->setCellValueByColumnAndRow(1, $ri + 1, $kelas['nama_kelas']);
        }

        // Kembali ke sheet utama
        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();

        $lastKelasRow = count($kelasList);
        $kelasFormula = "'_kelas_ref'!\$A\$1:\$A\$" . $lastKelasRow;

        addListValidation(
            $sheet, 'F', $dataStart, $dataEnd,
            $kelasFormula,
            'Pilih kelas dari daftar yang tersedia',
            false,
            DataValidation::STYLE_INFORMATION
        );
    } else {
        // Fallback hardcode jika DB kosong
        addListValidation(
            $sheet, 'F', $dataStart, $dataEnd,
            '"TKA A1,TKA A2,TKA A3,TKA A4,TKB B1,TKB B2,TKB B3,TKB B4,KB Melati,KB Mawar,KB Teratai,KB Sakura,TPA"',
            'Pilih kelas',
            true,
            DataValidation::STYLE_INFORMATION
        );
    }

    // Lebar kolom
    $colWidths = ['A'=>6, 'B'=>18, 'C'=>30, 'D'=>20, 'E'=>8, 'F'=>22, 'G'=>26];
    foreach ($colWidths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
}

// Freeze pane agar baris header tetap terlihat saat scroll
$sheet->freezePane('A' . $dataStart);

// Pastikan sheet aktif adalah sheet data (index 0)
$spreadsheet->setActiveSheetIndex(0);

// ── Output file ────────────────────────────────────────────────────────────
// Bersihkan output buffer sebelum mengirim file
if (ob_get_level()) {
    ob_end_clean();
}

$filename = 'template_import_' . $type . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
