<?php
/**
 * POST /api/admin/preview-import.php
 * Parse file Excel untuk preview — TIDAK menyimpan ke database
 * 
 * Mengembalikan array baris yang sudah divalidasi beserta status per-baris:
 *   - ok        : siap diimport
 *   - duplicate : nomor identitas sudah ada di DB
 *   - error     : data tidak valid / field wajib kosong
 * 
 * Mendukung dua format file:
 *   1. Template baru (format kolom eksplisit, mulai baris 3)
 *   2. File export dari sistem (ada kolom [ID Sistem] di kolom A)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/_import_helper.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$type = $_GET['type'] ?? $_POST['type'] ?? '';
if (!in_array($type, ['guru', 'siswa'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter type harus guru atau siswa.']);
    exit;
}

if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File Excel wajib diunggah.']);
    exit;
}

// Validasi ukuran & tipe file (termasuk MIME check)
validateExcelUpload($_FILES['excel_file'], 10);

$tmpPath = $_FILES['excel_file']['tmp_name'];

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($tmpPath);

    // Temukan sheet target (lewati sheet panduan & referensi)
    $targetSheet = findTargetSheet($spreadsheet);

    $allRows = $targetSheet->toArray(null, true, true, false);
    $db = getDB();

    // Deteksi format file menggunakan helper bersama
    ['isExportFormat' => $isExportFormat, 'dataStartRow' => $dataStartRow] = detectExcelFormat($allRows);

    // Ambil kelas dari DB untuk mapping nama → id
    $kelasMap = [];
    $stmtKelas = $db->query("SELECT id, kode_kelas, nama_kelas FROM kelas WHERE is_active = 1");
    while ($k = $stmtKelas->fetch()) {
        $kelasMap[strtoupper(trim($k['nama_kelas']))] = $k['id'];
        $kelasMap[strtoupper(trim($k['kode_kelas']))] = $k['id'];
    }

    $resultRows = [];
    $summary = ['total' => 0, 'ok' => 0, 'duplicate' => 0, 'error' => 0, 'update' => 0];

    if ($type === 'guru') {
        // ── PARSE GURU ────────────────────────────────────────────────────
        for ($i = $dataStartRow; $i < count($allRows); $i++) {
            $row = $allRows[$i];

            // Jika format export: kolom A = ID, kolom B = nomor_identitas, C = nama, dst
            // Jika format template: kolom A = No, B = Nama, C = Status, D = NUPTK, dst
            if ($isExportFormat) {
                $sysId       = trim($row[0] ?? '');
                $noIdentitas = trim($row[1] ?? '');
                $namaLengkap = trim($row[2] ?? '');
                $jabatan     = trim($row[3] ?? '');
                $statusGuru  = trim($row[4] ?? '');
                $nuptk       = trim($row[5] ?? '');
                $tempatLahir = trim($row[6] ?? '');
                $tglLahir    = trim($row[7] ?? '');
                $pendidikan  = trim($row[8] ?? '');
                $noTelepon   = trim($row[9] ?? '');
                $alamat      = trim($row[10] ?? '');
                $ttlRaw      = $tempatLahir . (!empty($tglLahir) ? ', ' . $tglLahir : '');
                $nuptkOrNik  = $nuptk ?: $noIdentitas;
            } else {
                // Format template baru
                $sysId       = '';
                $namaLengkap = trim($row[1] ?? '');
                $statusGuru  = trim($row[2] ?? '');
                $nuptkOrNik  = trim($row[3] ?? '');
                $ttlRaw      = trim($row[4] ?? '');
                $pendidikan  = trim($row[5] ?? '');
                $jabatan     = trim($row[7] ?? '');
                $noTelepon   = trim($row[11] ?? '');
                $alamat      = trim($row[10] ?? '');
                $nuptk       = $nuptkOrNik;
                $noIdentitas = $nuptkOrNik;
            }

            // Skip baris kosong
            if (empty($namaLengkap)) continue;
            // Skip baris header
            if (strtolower($namaLengkap) === 'nama lengkap' || strtolower($namaLengkap) === 'nama lengkap *') continue;

            $summary['total']++;

            // Tentukan nomor identitas yang akan digunakan untuk cek duplikat
            $nomorCek = $nuptkOrNik;
            if (empty($nomorCek) || $nomorCek === '-' || strtolower($nomorCek) === 'nan') {
                $nomorCek = ''; // kosong, akan auto-generate saat import
            }

            // Parse TTL
            $tempatLahirParsed = null;
            $tanggalLahirParsed = null;
            if (!$isExportFormat && !empty($ttlRaw)) {
                $parts = explode(',', $ttlRaw);
                if (count($parts) >= 2) {
                    $tempatLahirParsed = trim($parts[0]);
                    $tglStr = trim(end($parts));
                    $tglParsed = date('Y-m-d', strtotime($tglStr));
                    if ($tglParsed && $tglParsed !== '1970-01-01') {
                        $tanggalLahirParsed = $tglParsed;
                    }
                } else {
                    $tempatLahirParsed = $ttlRaw;
                }
            } elseif ($isExportFormat) {
                $tempatLahirParsed = $tempatLahir;
                if (!empty($tglLahir)) {
                    $parsed = date('Y-m-d', strtotime($tglLahir));
                    $tanggalLahirParsed = ($parsed && $parsed !== '1970-01-01') ? $parsed : null;
                }
            }

            // Cek status baris
            $rowStatus  = 'ok';
            $rowMessage = 'Siap diimport';
            $existingId = null;

            if (!empty($sysId) && is_numeric($sysId)) {
                // Format export: cek apakah ID ini ada di DB
                $cekId = $db->prepare("SELECT id FROM guru WHERE id = :id LIMIT 1");
                $cekId->execute([':id' => (int)$sysId]);
                if ($cekId->fetch()) {
                    $rowStatus  = 'update';
                    $rowMessage = 'Akan diperbarui (UPDATE)';
                    $existingId = (int)$sysId;
                    $summary['update']++;
                }
            } elseif (!empty($nomorCek)) {
                $cekDup = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                $cekDup->execute([':nomor' => $nomorCek]);
                if ($cekDup->fetch()) {
                    $rowStatus  = 'duplicate';
                    $rowMessage = 'Nomor identitas sudah ada di sistem';
                    $summary['duplicate']++;
                }
            }

            if ($rowStatus === 'ok') {
                $summary['ok']++;
            }

            $resultRows[] = [
                '_row'            => $i + 1,
                '_status'         => $rowStatus,
                '_message'        => $rowMessage,
                '_existing_id'    => $existingId,
                '_action'         => 'import', // bisa diubah: import | skip | update
                'sys_id'          => $sysId,
                'nama_lengkap'    => $namaLengkap,
                'status_guru'     => $statusGuru ?: 'Guru',
                'nuptk'           => $nuptk,
                'nomor_identitas' => $noIdentitas,
                'tempat_lahir'    => $tempatLahirParsed,
                'tanggal_lahir'   => $tanggalLahirParsed,
                'ttl_display'     => $ttlRaw,
                'pendidikan'      => $pendidikan,
                'jabatan'         => $jabatan,
                'no_telepon'      => $noTelepon,
                'alamat'          => $alamat,
            ];
        }

    } else {
        // ── PARSE SISWA ───────────────────────────────────────────────────
        for ($i = $dataStartRow; $i < count($allRows); $i++) {
            $row = $allRows[$i];

            if ($isExportFormat) {
                // Format export: A=ID, B=NIS, C=Nama, D=Panggilan, E=JK, F=Kelas, G=Wali, H=Status, I=KelasID
                $sysId        = trim($row[0] ?? '');
                $nis          = trim($row[1] ?? '');
                $namaLengkap  = trim($row[2] ?? '');
                $namaPanggilan = trim($row[3] ?? '');
                $jenisKelamin = strtoupper(trim($row[4] ?? ''));
                $kelasNama    = trim($row[5] ?? '');
                $namaWali     = trim($row[6] ?? '');
                $kelasIdRaw   = trim($row[8] ?? '');
            } else {
                // Format template baru: A=No, B=NIS, C=Nama, D=Panggilan, E=JK, F=Kelas, G=Wali
                $sysId        = '';
                $nis          = trim($row[1] ?? '');
                $namaLengkap  = trim($row[2] ?? '');
                $namaPanggilan = trim($row[3] ?? '');
                $jenisKelamin = strtoupper(trim($row[4] ?? ''));
                $kelasNama    = trim($row[5] ?? '');
                $namaWali     = trim($row[6] ?? '');
                $kelasIdRaw   = '';
            }

            // Skip baris kosong / header
            if (empty($nis) || empty($namaLengkap)) continue;
            if (strtolower($nis) === 'nis' || strtolower($nis) === 'no induk' || strtolower($nis) === 'nis / no induk *') continue;

            $summary['total']++;

            // Resolve kelas ID
            $kelasId = null;
            if (!empty($kelasIdRaw) && is_numeric($kelasIdRaw)) {
                $kelasId = (int)$kelasIdRaw;
            } elseif (!empty($kelasNama)) {
                $kelasId = $kelasMap[strtoupper(trim($kelasNama))] ?? null;
            }

            // Validasi field wajib
            $errors = [];
            if (empty($nis))         $errors[] = 'NIS kosong';
            if (empty($namaLengkap)) $errors[] = 'Nama kosong';
            if (!in_array($jenisKelamin, ['L', 'P'])) {
                $jenisKelamin = 'L'; // default
            }
            if (!$kelasId) $errors[] = 'Kelas tidak ditemukan: "' . $kelasNama . '"';

            $rowStatus  = 'ok';
            $rowMessage = 'Siap diimport';
            $existingId = null;

            if (!empty($errors)) {
                $rowStatus  = 'error';
                $rowMessage = implode('; ', $errors);
                $summary['error']++;
            } elseif (!empty($sysId) && is_numeric($sysId)) {
                $cekId = $db->prepare("SELECT id FROM siswa WHERE id = :id LIMIT 1");
                $cekId->execute([':id' => (int)$sysId]);
                if ($cekId->fetch()) {
                    $rowStatus  = 'update';
                    $rowMessage = 'Akan diperbarui (UPDATE)';
                    $existingId = (int)$sysId;
                    $summary['update']++;
                }
            } else {
                $cekDup = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                $cekDup->execute([':nomor' => $nis]);
                if ($cekDup->fetch()) {
                    $rowStatus  = 'duplicate';
                    $rowMessage = 'NIS sudah ada di sistem';
                    $summary['duplicate']++;
                }
            }

            if ($rowStatus === 'ok') {
                $summary['ok']++;
            }

            $resultRows[] = [
                '_row'          => $i + 1,
                '_status'       => $rowStatus,
                '_message'      => $rowMessage,
                '_existing_id'  => $existingId,
                '_action'       => 'import',
                'sys_id'        => $sysId,
                'nis'           => $nis,
                'nama_lengkap'  => $namaLengkap,
                'nama_panggilan'=> $namaPanggilan,
                'jenis_kelamin' => $jenisKelamin,
                'kelas_nama'    => $kelasNama,
                'kelas_id'      => $kelasId,
                'nama_wali'     => $namaWali,
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'data'   => [
            'rows'    => $resultRows,
            'summary' => $summary,
            'type'    => $type,
        ],
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file Excel: ' . $e->getMessage()]);
}
