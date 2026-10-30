<?php
/**
 * POST /api/admin/import-excel.php
 * Import data Guru / Siswa ke database
 *
 * Mendukung dua mode:
 *
 * MODE 1 — Upload file langsung (mode=file, default):
 *   multipart/form-data dengan field excel_file
 *   Kompatibel dengan format lama dan format template baru
 *
 * MODE 2 — Konfirmasi dari preview (mode=confirm):
 *   application/json atau form field rows_json berisi array baris
 *   yang sudah diedit + field _action per baris (import|skip|update)
 *
 * Parameter GET/POST:
 *   type   = guru | siswa   (wajib)
 *   mode   = file | confirm (default: file)
 *
 * Revisi v2:
 *   - Validasi MIME type & ukuran file (fix #3, #4)
 *   - mt_rand diganti generateGuruNomor() deterministik (fix #1)
 *   - UPDATE di mode file dicatat sebagai $updated (fix #2)
 *   - bcrypt cost turun ke 10 untuk performa batch (fix #6)
 *   - $errors dan $updated dikembalikan di semua response (fix #8)
 *   - Skip dengan pesan detail (fix #7)
 *   - Validasi kelas_id saat UPDATE siswa (fix #9)
 *   - Logika deteksi format dipindah ke _import_helper.php (fix #5)
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
    echo json_encode(['status' => 'error', 'message' => 'Tipe import (guru/siswa) tidak valid.']);
    exit;
}

// Deteksi mode
$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'file';
$db   = getDB();

// ══════════════════════════════════════════════════════════════════════════════
// MODE 2: KONFIRMASI DARI PREVIEW (terima JSON rows)
// ══════════════════════════════════════════════════════════════════════════════
if ($mode === 'confirm') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);

    // Bisa juga dari form POST
    if (!$payload && !empty($_POST['rows_json'])) {
        $payload = json_decode($_POST['rows_json'], true);
    }

    if (empty($payload['rows']) || !is_array($payload['rows'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data rows kosong atau tidak valid.']);
        exit;
    }

    $rows        = $payload['rows'];
    $imported    = 0;
    $updated     = 0;
    $skipped     = 0;
    $errors      = [];
    $autoGuruIdx = 0; // untuk generateGuruNomor()

    $db->beginTransaction();

    try {
        foreach ($rows as $idx => $row) {
            $action = $row['_action'] ?? 'import';

            if ($action === 'skip') {
                $skipped++;
                continue;
            }

            if ($type === 'guru') {
                $namaLengkap = trim($row['nama_lengkap'] ?? '');
                if (empty($namaLengkap)) { $skipped++; continue; }

                $statusGuru  = trim($row['status_guru'] ?? 'Guru');
                $nuptk       = trim($row['nuptk'] ?? '');
                $tempatLahir = trim($row['tempat_lahir'] ?? '');
                $tglLahirStr = trim($row['tanggal_lahir'] ?? '');
                $pendidikan  = trim($row['pendidikan'] ?? '');
                $jabatan     = trim($row['jabatan'] ?? '');
                $noTelepon   = trim($row['no_telepon'] ?? '');
                $alamat      = trim($row['alamat'] ?? '');
                $sysId       = isset($row['sys_id']) && is_numeric($row['sys_id']) ? (int)$row['sys_id'] : null;

                // Parse tanggal lahir
                $tanggalLahir = null;
                if (!empty($tglLahirStr)) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglLahirStr)) {
                        $tanggalLahir = $tglLahirStr;
                    } else {
                        $parsed = date('Y-m-d', strtotime($tglLahirStr));
                        $tanggalLahir = ($parsed && $parsed !== '1970-01-01') ? $parsed : null;
                    }
                }

                // Tentukan nomor identitas (deterministik, bukan mt_rand)
                $nomorIdentitas = !empty($nuptk) && $nuptk !== '-' ? $nuptk : null;
                if (!$nomorIdentitas) {
                    $nomorIdentitas = generateGuruNomor($db, $autoGuruIdx);
                }

                $role = (strtolower($statusGuru) === 'karyawan') ? 'karyawan' : 'guru';

                if ($action === 'update' && $sysId) {
                    // UPDATE data guru yang sudah ada
                    $stmtUpd = $db->prepare("
                        UPDATE guru SET
                            nama_lengkap  = :nama,
                            jabatan       = :jabatan,
                            status        = :status,
                            nuptk         = :nuptk,
                            pendidikan    = :pendidikan,
                            tempat_lahir  = :tempat_lahir,
                            tanggal_lahir = :tanggal_lahir,
                            no_telepon    = :no_telepon,
                            alamat        = :alamat
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':nama'          => $namaLengkap,
                        ':jabatan'       => $jabatan ?: null,
                        ':status'        => $statusGuru,
                        ':nuptk'         => $nuptk ?: null,
                        ':pendidikan'    => $pendidikan ?: null,
                        ':tempat_lahir'  => $tempatLahir ?: null,
                        ':tanggal_lahir' => $tanggalLahir,
                        ':no_telepon'    => $noTelepon ?: null,
                        ':alamat'        => $alamat ?: null,
                        ':id'            => $sysId,
                    ]);
                    $updated++;

                } else {
                    // INSERT baru — cek duplikat dulu
                    $cek = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                    $cek->execute([':nomor' => $nomorIdentitas]);
                    if ($cek->fetch()) {
                        $skipped++;
                        $errors[] = "Baris " . ($idx + 1) . ": nomor identitas '$nomorIdentitas' sudah ada, dilewati.";
                        continue;
                    }

                    $passHash = password_hash('12345678', PASSWORD_BCRYPT, ['cost' => 10]);
                    $stmtUser = $db->prepare("
                        INSERT INTO users (nomor_identitas, password_hash, role, is_active)
                        VALUES (:nomor, :hash, :role, 1)
                    ");
                    $stmtUser->execute([':nomor' => $nomorIdentitas, ':hash' => $passHash, ':role' => $role]);
                    $userId = $db->lastInsertId();

                    $stmtGuru = $db->prepare("
                        INSERT INTO guru (user_id, nuptk, nama_lengkap, jabatan, status, pendidikan,
                                          tempat_lahir, tanggal_lahir, no_telepon, alamat)
                        VALUES (:user_id, :nuptk, :nama, :jabatan, :status, :pendidikan,
                                :tempat_lahir, :tanggal_lahir, :no_telepon, :alamat)
                    ");
                    $stmtGuru->execute([
                        ':user_id'       => $userId,
                        ':nuptk'         => (!empty($nuptk) && $nuptk !== '-') ? $nuptk : null,
                        ':nama'          => $namaLengkap,
                        ':jabatan'       => $jabatan ?: null,
                        ':status'        => $statusGuru,
                        ':pendidikan'    => $pendidikan ?: null,
                        ':tempat_lahir'  => $tempatLahir ?: null,
                        ':tanggal_lahir' => $tanggalLahir,
                        ':no_telepon'    => $noTelepon ?: null,
                        ':alamat'        => $alamat ?: null,
                    ]);
                    $imported++;
                }

            } else {
                // ── SISWA ────────────────────────────────────────────────
                $nis           = trim($row['nis'] ?? '');
                $namaLengkap   = trim($row['nama_lengkap'] ?? '');
                $namaPanggilan = trim($row['nama_panggilan'] ?? '');
                $jenisKelamin  = strtoupper(trim($row['jenis_kelamin'] ?? 'L'));
                $kelasId       = isset($row['kelas_id']) ? (int)$row['kelas_id'] : null;
                $namaWali      = trim($row['nama_wali'] ?? '');
                $sysId         = isset($row['sys_id']) && is_numeric($row['sys_id']) ? (int)$row['sys_id'] : null;

                if (empty($nis) || empty($namaLengkap)) { $skipped++; continue; }
                if (!in_array($jenisKelamin, ['L', 'P'])) $jenisKelamin = 'L';

                if ($action === 'update' && $sysId) {
                    // Validasi kelas_id ke DB sebelum UPDATE (cegah orphan FK)
                    if ($kelasId) {
                        $cekKelas = $db->prepare("SELECT id FROM kelas WHERE id = :id AND is_active = 1 LIMIT 1");
                        $cekKelas->execute([':id' => $kelasId]);
                        if (!$cekKelas->fetch()) {
                            $skipped++;
                            $errors[] = "Baris " . ($idx + 1) . ": Siswa '$namaLengkap' — kelas_id '$kelasId' tidak ditemukan/tidak aktif, dilewati.";
                            continue;
                        }
                    }

                    $stmtUpd = $db->prepare("
                        UPDATE siswa SET
                            nis            = :nis,
                            nama_lengkap   = :nama,
                            nama_panggilan = :panggilan,
                            jenis_kelamin  = :jk,
                            kelas_id       = :kelas_id,
                            nama_wali      = :wali
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':nis'       => $nis,
                        ':nama'      => $namaLengkap,
                        ':panggilan' => $namaPanggilan ?: null,
                        ':jk'        => $jenisKelamin,
                        ':kelas_id'  => $kelasId,
                        ':wali'      => $namaWali ?: null,
                        ':id'        => $sysId,
                    ]);
                    $updated++;

                } else {
                    $cek = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                    $cek->execute([':nomor' => $nis]);
                    if ($cek->fetch()) {
                        $skipped++;
                        $errors[] = "Baris " . ($idx + 1) . ": NIS '$nis' sudah ada, dilewati.";
                        continue;
                    }

                    $passHash = password_hash($nis, PASSWORD_BCRYPT, ['cost' => 10]);
                    $stmtUser = $db->prepare("
                        INSERT INTO users (nomor_identitas, password_hash, role, is_active)
                        VALUES (:nomor, :hash, 'wali_murid', 1)
                    ");
                    $stmtUser->execute([':nomor' => $nis, ':hash' => $passHash]);
                    $userId = $db->lastInsertId();

                    $stmtSiswa = $db->prepare("
                        INSERT INTO siswa (user_id, nis, nama_lengkap, nama_panggilan,
                                           jenis_kelamin, kelas_id, nama_wali, is_active)
                        VALUES (:user_id, :nis, :nama, :panggilan, :jk, :kelas_id, :wali, 1)
                    ");
                    $stmtSiswa->execute([
                        ':user_id'   => $userId,
                        ':nis'       => $nis,
                        ':nama'      => $namaLengkap,
                        ':panggilan' => $namaPanggilan ?: null,
                        ':jk'        => $jenisKelamin,
                        ':kelas_id'  => $kelasId,
                        ':wali'      => $namaWali ?: null,
                    ]);
                    $imported++;
                }
            }
        }

        $db->commit();

        echo json_encode([
            'status'  => 'success',
            'message' => 'Import selesai.',
            'data'    => [
                'imported' => $imported,
                'updated'  => $updated,
                'skipped'  => $skipped,
                'errors'   => $errors,
            ],
        ]);

    } catch (\Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// MODE 1: UPLOAD FILE LANGSUNG (backward-compatible)
// ══════════════════════════════════════════════════════════════════════════════

if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'File Excel wajib diunggah.']);
    exit;
}

// Validasi ukuran & tipe file (fix #3, #4)
validateExcelUpload($_FILES['excel_file'], 10);

$tmpPath = $_FILES['excel_file']['tmp_name'];

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($tmpPath);
    $targetSheet = findTargetSheet($spreadsheet);
    $rows        = $targetSheet->toArray(null, true, true, false);

    $imported    = 0;
    $updated     = 0;
    $skipped     = 0;
    $errors      = [];
    $autoGuruIdx = 0;
    $db->beginTransaction();

    // Deteksi format menggunakan helper (fix #5)
    ['isExportFormat' => $isExportFormat, 'dataStartRow' => $dataStartRow] = detectExcelFormat($rows);

    if ($type === 'guru') {
        for ($i = $dataStartRow; $i < count($rows); $i++) {
            $row = $rows[$i];

            if ($isExportFormat) {
                $sysId       = trim($row[0] ?? '');
                $namaLengkap = trim($row[2] ?? '');
                $statusGuru  = trim($row[4] ?? 'Guru');
                $nuptk       = trim($row[5] ?? '');
                $tempatLahir = trim($row[6] ?? '');
                $tglLahirStr = trim($row[7] ?? '');
                $pendidikan  = trim($row[8] ?? '');
                $noTelepon   = trim($row[9] ?? '');
                $alamat      = trim($row[10] ?? '');
                $jabatan     = trim($row[3] ?? '');
                $nuptkOrNik  = $nuptk;
            } else {
                $sysId       = '';
                $namaLengkap = trim($row[1] ?? '');
                $statusGuru  = trim($row[2] ?? 'Guru');
                $nuptkOrNik  = trim($row[3] ?? '');
                $ttlRaw      = trim($row[4] ?? '');
                $pendidikan  = trim($row[5] ?? '');
                $jabatan     = trim($row[7] ?? '');
                $noTelepon   = trim($row[11] ?? '');
                $alamat      = trim($row[10] ?? '');
                $nuptk       = $nuptkOrNik;
                $tempatLahir = '';
                $tglLahirStr = '';
            }

            if (empty(trim($namaLengkap))) continue;
            if (strtolower($namaLengkap) === 'nama lengkap' || strtolower($namaLengkap) === 'nama lengkap *') continue;

            // Parse TTL
            $tanggalLahir = null;
            if (!$isExportFormat && !empty($ttlRaw)) {
                $parts = explode(',', $ttlRaw);
                if (count($parts) >= 2) {
                    $tempatLahir = trim($parts[0]);
                    $tglStr = trim(end($parts));
                    $parsed = date('Y-m-d', strtotime($tglStr));
                    if ($parsed && $parsed !== '1970-01-01') $tanggalLahir = $parsed;
                } else {
                    $tempatLahir = $ttlRaw;
                }
            } elseif ($isExportFormat && !empty($tglLahirStr)) {
                $parsed = date('Y-m-d', strtotime($tglLahirStr));
                $tanggalLahir = ($parsed && $parsed !== '1970-01-01') ? $parsed : null;
            }

            // Nomor identitas deterministik (fix #1)
            $nomorIdentitas = !empty($nuptkOrNik) && $nuptkOrNik !== '-' && strtolower($nuptkOrNik) !== 'nan'
                ? $nuptkOrNik
                : generateGuruNomor($db, $autoGuruIdx);

            $role = (strtolower($statusGuru) === 'karyawan') ? 'karyawan' : 'guru';

            // Mode update jika ada sysId valid
            if (!empty($sysId) && is_numeric($sysId)) {
                $cekId = $db->prepare("SELECT id FROM guru WHERE id = :id LIMIT 1");
                $cekId->execute([':id' => (int)$sysId]);
                if ($cekId->fetch()) {
                    $stmtUpd = $db->prepare("
                        UPDATE guru SET
                            nama_lengkap  = :nama, jabatan = :jabatan, status = :status,
                            nuptk = :nuptk, pendidikan = :pendidikan,
                            tempat_lahir = :tempat_lahir, tanggal_lahir = :tanggal_lahir,
                            no_telepon = :no_telepon, alamat = :alamat
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':nama'          => $namaLengkap, ':jabatan'       => $jabatan ?: null,
                        ':status'        => $statusGuru,  ':nuptk'         => $nuptk ?: null,
                        ':pendidikan'    => $pendidikan ?: null, ':tempat_lahir' => $tempatLahir ?: null,
                        ':tanggal_lahir' => $tanggalLahir, ':no_telepon'   => $noTelepon ?: null,
                        ':alamat'        => $alamat ?: null, ':id'          => (int)$sysId,
                    ]);
                    $updated++; // fix #2: was $imported++
                    continue;
                }
            }

            $cek = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
            $cek->execute([':nomor' => $nomorIdentitas]);
            if ($cek->fetch()) {
                $skipped++;
                $errors[] = "Baris $i: nomor identitas '$nomorIdentitas' sudah ada, dilewati.";
                continue;
            }

            $passHash = password_hash('12345678', PASSWORD_BCRYPT, ['cost' => 10]); // fix #6
            $db->prepare("INSERT INTO users (nomor_identitas, password_hash, role, is_active) VALUES (:nomor, :hash, :role, 1)")
               ->execute([':nomor' => $nomorIdentitas, ':hash' => $passHash, ':role' => $role]);
            $userId = $db->lastInsertId();

            $db->prepare("
                INSERT INTO guru (user_id, nuptk, nama_lengkap, jabatan, status, pendidikan,
                                  tempat_lahir, tanggal_lahir, no_telepon, alamat)
                VALUES (:uid, :nuptk, :nama, :jabatan, :status, :pendidikan, :tl, :td, :tel, :al)
            ")->execute([
                ':uid'       => $userId, ':nuptk'     => $nuptk !== '-' ? $nuptk : null,
                ':nama'      => $namaLengkap, ':jabatan' => $jabatan ?: null,
                ':status'    => $statusGuru, ':pendidikan' => $pendidikan ?: null,
                ':tl'        => $tempatLahir ?: null, ':td' => $tanggalLahir,
                ':tel'       => $noTelepon ?: null, ':al' => $alamat ?: null,
            ]);
            $imported++;
        }

    } else {
        // ── IMPORT SISWA ─────────────────────────────────────────────────
        $stmtKelas = $db->query("SELECT id, kode_kelas, nama_kelas FROM kelas WHERE is_active = 1");
        $kelasMap  = [];
        while ($k = $stmtKelas->fetch()) {
            $kelasMap[strtoupper(trim($k['nama_kelas']))] = $k['id'];
            $kelasMap[strtoupper(trim($k['kode_kelas']))] = $k['id'];
        }

        // Mapping lama (format excel Bustanul Athfal)
        $legacyMapping = [
            'KELOMPOK BERMAIN MELATI'  => 'KB-1', 'KELOMPOK BERMAIN MAWAR'   => 'KB-2',
            'KELOMPOK BERMAIN TERATAI' => 'KB-3', 'KELOMPOK BERMAIN SAKURA'  => 'KB-4',
            'BUSTANUL ATHFAL A1' => 'TKA-1', 'BUSTANUL ATHFAL A2' => 'TKA-2',
            'BUSTANUL ATHFAL A3' => 'TKA-3', 'BUSTANUL ATHFAL A4' => 'TKA-4',
            'BUSTANUL ATHFAL B1' => 'TKB-1', 'BUSTANUL ATHFAL B2' => 'TKB-2',
            'BUSTANUL ATHFAL B3' => 'TKB-3', 'BUSTANUL ATHFAL B4' => 'TKB-4',
            'TAMAN PENITIPAN ANAK' => 'TPA', 'KB MELATI' => 'KB-1',
            'KB MAWAR' => 'KB-2', 'KB TERATAI' => 'KB-3', 'KB SAKURA' => 'KB-4',
        ];

        $currentKelasId = null;

        if ($isExportFormat) {
            // Format export: setiap baris punya kolom kelas & ID
            for ($i = $dataStartRow; $i < count($rows); $i++) {
                $row          = $rows[$i];
                $sysId        = trim($row[0] ?? '');
                $nis          = trim($row[1] ?? '');
                $namaLengkap  = trim($row[2] ?? '');
                $namaPanggilan = trim($row[3] ?? '');
                $jk           = strtoupper(trim($row[4] ?? 'L'));
                $kelasNama    = trim($row[5] ?? '');
                $namaWali     = trim($row[6] ?? '');
                $kelasIdRaw   = trim($row[8] ?? '');

                if (empty($nis) || empty($namaLengkap)) continue;
                if (strtolower($nis) === 'nis' || strtolower($nis) === 'nis / no induk *') continue;
                if (!in_array($jk, ['L', 'P'])) $jk = 'L';

                // Resolve kelas_id dengan validasi DB (fix #9)
                $kelasId = null;
                if (!empty($kelasIdRaw) && is_numeric($kelasIdRaw)) {
                    $cekKelas = $db->prepare("SELECT id FROM kelas WHERE id = :id AND is_active = 1 LIMIT 1");
                    $cekKelas->execute([':id' => (int)$kelasIdRaw]);
                    $kelasId = $cekKelas->fetch() ? (int)$kelasIdRaw : null;
                }
                if (!$kelasId && !empty($kelasNama)) {
                    $kelasId = $kelasMap[strtoupper(trim($kelasNama))] ?? null;
                }

                if (!empty($sysId) && is_numeric($sysId)) {
                    $cekId = $db->prepare("SELECT id FROM siswa WHERE id = :id LIMIT 1");
                    $cekId->execute([':id' => (int)$sysId]);
                    if ($cekId->fetch()) {
                        $db->prepare("UPDATE siswa SET nis=:nis, nama_lengkap=:nama, nama_panggilan=:pgl,
                                      jenis_kelamin=:jk, kelas_id=:kid, nama_wali=:wali WHERE id=:id")
                           ->execute([
                               ':nis'  => $nis,  ':nama' => $namaLengkap,
                               ':pgl'  => $namaPanggilan ?: null, ':jk' => $jk,
                               ':kid'  => $kelasId, ':wali' => $namaWali ?: null,
                               ':id'   => (int)$sysId,
                           ]);
                        $updated++;
                        continue;
                    }
                }

                $cek = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                $cek->execute([':nomor' => $nis]);
                if ($cek->fetch()) {
                    $skipped++;
                    $errors[] = "Baris $i: NIS '$nis' sudah ada, dilewati."; // fix #7
                    continue;
                }

                $passHash = password_hash($nis, PASSWORD_BCRYPT, ['cost' => 10]); // fix #6
                $db->prepare("INSERT INTO users (nomor_identitas, password_hash, role, is_active) VALUES (:nomor, :hash, 'wali_murid', 1)")
                   ->execute([':nomor' => $nis, ':hash' => $passHash]);
                $userId = $db->lastInsertId();

                $db->prepare("INSERT INTO siswa (user_id, nis, nama_lengkap, nama_panggilan,
                               jenis_kelamin, kelas_id, nama_wali, is_active)
                               VALUES (:uid, :nis, :nama, :pgl, :jk, :kid, :wali, 1)")
                   ->execute([
                       ':uid'  => $userId, ':nis'  => $nis, ':nama' => $namaLengkap,
                       ':pgl'  => $namaPanggilan ?: null, ':jk' => $jk,
                       ':kid'  => $kelasId, ':wali' => $namaWali ?: null,
                   ]);
                $imported++;
            }

        } else {
            // Format template baru (kolom F = kelas) atau format lama (baris kelas sebagai pemisah)
            for ($i = $dataStartRow; $i < count($rows); $i++) {
                $row  = $rows[$i];
                $colA = strtoupper(trim($row[0] ?? ''));
                $colB = strtoupper(trim($row[1] ?? ''));
                $colF = trim($row[5] ?? ''); // kolom Kelas di template baru

                // Cek apakah baris ini adalah penanda kelas (format lama)
                $foundKelas = false;
                foreach ($legacyMapping as $pattern => $kode) {
                    if (strpos($colA, $pattern) !== false || strpos($colB, $pattern) !== false) {
                        $currentKelasId = $kelasMap[strtoupper($kode)] ?? null;
                        $foundKelas = true;
                        break;
                    }
                }
                if ($foundKelas) continue;

                $nis         = trim($row[1] ?? '');
                $namaLengkap = trim($row[2] ?? '');
                if (empty($nis) || empty($namaLengkap)) continue;
                if (strtolower($nis) === 'no induk' || strtolower($nis) === 'nis' || strtolower($nis) === 'nis / no induk *') continue;

                $namaPanggilan = trim($row[3] ?? '');
                $jk = strtoupper(trim($row[4] ?? 'L'));
                if (!in_array($jk, ['L', 'P'])) $jk = 'L';
                $namaWali = trim($row[6] ?? '');

                // Resolusi kelas: template baru pakai kolom F, format lama pakai currentKelasId
                $kelasId = null;
                if (!empty($colF)) {
                    $kelasId = $kelasMap[strtoupper(trim($colF))] ?? null;
                }
                if (!$kelasId) $kelasId = $currentKelasId;
                if (!$kelasId) {
                    $skipped++;
                    $errors[] = "Baris $i: Siswa '$namaLengkap' — kelas '$colF' tidak ditemukan, dilewati."; // fix #7
                    continue;
                }

                $cek = $db->prepare("SELECT id FROM users WHERE nomor_identitas = :nomor LIMIT 1");
                $cek->execute([':nomor' => $nis]);
                if ($cek->fetch()) {
                    $skipped++;
                    $errors[] = "Baris $i: NIS '$nis' sudah ada, dilewati."; // fix #7
                    continue;
                }

                $passHash = password_hash($nis, PASSWORD_BCRYPT, ['cost' => 10]); // fix #6
                $db->prepare("INSERT INTO users (nomor_identitas, password_hash, role, is_active) VALUES (:nomor, :hash, 'wali_murid', 1)")
                   ->execute([':nomor' => $nis, ':hash' => $passHash]);
                $userId = $db->lastInsertId();

                $db->prepare("INSERT INTO siswa (user_id, nis, nama_lengkap, nama_panggilan,
                               jenis_kelamin, kelas_id, nama_wali, is_active)
                               VALUES (:uid, :nis, :nama, :pgl, :jk, :kid, :wali, 1)")
                   ->execute([
                       ':uid'  => $userId, ':nis'  => $nis, ':nama' => $namaLengkap,
                       ':pgl'  => $namaPanggilan ?: null, ':jk' => $jk,
                       ':kid'  => $kelasId, ':wali' => $namaWali ?: null,
                   ]);
                $imported++;
            }
        }
    }

    $db->commit();

    // fix #8: response seragam dengan mode confirm (ada updated + errors)
    echo json_encode([
        'status'  => 'success',
        'message' => 'Import Excel berhasil.',
        'data'    => [
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ],
    ]);

} catch (\Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
