<?php
/**
 * Helper functions bersama untuk proses import Excel.
 * Di-include oleh: preview-import.php dan import-excel.php
 */

/**
 * Validasi file upload Excel: ukuran & MIME type.
 * Langsung mengeluarkan JSON error + exit jika tidak valid.
 *
 * @param array $file       Entry dari $_FILES
 * @param int   $maxSizeMb  Batas ukuran dalam MB (default 10)
 */
function validateExcelUpload(array $file, int $maxSizeMb = 10): void
{
    // Cek ukuran
    $maxBytes = $maxSizeMb * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "File terlalu besar. Maksimal {$maxSizeMb} MB."]);
        exit;
    }

    // Cek ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'File harus berformat .xlsx atau .xls.']);
        exit;
    }

    // Cek MIME type dari konten file (bukan header HTTP)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel',                                           // xls
        'application/zip',          // xlsx adalah ZIP secara teknis
        'application/octet-stream', // fallback beberapa sistem
    ];
    if (!in_array($mime, $allowedMimes)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tipe file tidak dikenali. Pastikan file adalah .xlsx atau .xls yang valid.']);
        exit;
    }
}

/**
 * Deteksi format file Excel dari array baris (hasil toArray).
 * Mengembalikan ['isExportFormat' => bool, 'dataStartRow' => int]
 *
 * - isExportFormat = true  -> file hasil Export dari sistem (ada kolom [ID Sistem])
 * - isExportFormat = false -> file template isian baru
 * - dataStartRow           -> indeks baris (0-based) tempat data dimulai
 */
function detectExcelFormat(array $rows): array
{
    $isExportFormat = false;
    $dataStartRow   = 2; // default: baris ke-3 (0-indexed = 2)

    if (isset($rows[1][0])) {
        $h = strtolower(trim($rows[1][0] ?? ''));
        if (str_contains($h, 'id sistem') || str_contains($h, '[id')) {
            $isExportFormat = true;
            $dataStartRow   = 2;
        }
    }
    if (isset($rows[0][0])) {
        $h0 = strtolower(trim($rows[0][0] ?? ''));
        if (str_contains($h0, 'petunjuk') || str_contains($h0, 'export data')) {
            $dataStartRow = 2;
        }
    }

    return ['isExportFormat' => $isExportFormat, 'dataStartRow' => $dataStartRow];
}

/**
 * Temukan sheet target dari Spreadsheet (lewati sheet panduan & referensi tersembunyi).
 */
function findTargetSheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
{
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        $lower = strtolower($sheetName);
        if (str_starts_with($lower, '_') || $lower === 'panduan pengisian') {
            continue;
        }
        return $spreadsheet->getSheetByName($sheetName);
    }
    return $spreadsheet->getActiveSheet();
}

/**
 * Generate nomor identitas guru secara deterministik (aman untuk batch import).
 * Inisialisasi otomatis dari nilai MAX yang ada di DB pada pemanggilan pertama.
 * Memanggil dengan referensi $autoIdx agar counter bertambah antar baris.
 *
 * @param PDO $db
 * @param int &$autoIdx  Counter (pass by reference). Mulai dari 0 (auto-init dari DB).
 * @return string  Contoh: "GUR000042"
 */
function generateGuruNomor(PDO $db, int &$autoIdx): string
{
    if ($autoIdx === 0) {
        // Inisialisasi: ambil angka tertinggi dari nomor GUR* yang sudah ada
        $stmt    = $db->query("SELECT COALESCE(MAX(CAST(SUBSTRING(nomor_identitas, 4) AS UNSIGNED)), 0) FROM users WHERE nomor_identitas LIKE 'GUR%'");
        $autoIdx = (int) $stmt->fetchColumn() + 1;
    }
    $nomor = 'GUR' . str_pad((string)$autoIdx, 6, '0', STR_PAD_LEFT);
    $autoIdx++;
    return $nomor;
}
