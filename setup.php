<?php
/**
 * Script Setup Database
 * Jalankan: php setup.php
 * Atau akses: http://localhost/Sistem_Tahfidz_Restu2/setup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "===========================================\n";
echo " Setup Database - Sistem Tahfidz Restu 2\n";
echo "===========================================\n\n";

// Konfigurasi database
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'tahfidz_restu2';

// 1. Koneksi MySQL tanpa database
echo "[1] Mengoneksi ke MySQL...\n";
try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "    ✓ Koneksi berhasil\n\n";
} catch (PDOException $e) {
    echo "    ✗ Koneksi gagal: " . $e->getMessage() . "\n";
    echo "    Pastikan MySQL sudah berjalan dan konfigurasi benar.\n";
    exit(1);
}

// 2. Buat database
echo "[2] Membuat database '{$dbname}'...\n";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "    ✓ Database siap\n\n";

// 3. Pilih database
$pdo->exec("USE `{$dbname}`");

// 4. Jalankan schema
echo "[3] Menjalankan schema.sql...\n";
$schemaFile = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "    ✗ File schema.sql tidak ditemukan!\n";
    exit(1);
}

$sql = file_get_contents($schemaFile);
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !str_starts_with($s, '--')
);

$tables = 0;
$views = 0;
foreach ($statements as $statement) {
    if (empty($statement)) continue;
    try {
        $pdo->exec($statement);
        if (str_contains($statement, 'CREATE TABLE')) $tables++;
        if (str_contains($statement, 'CREATE OR REPLACE VIEW')) $views++;
    } catch (PDOException $e) {
        // Skip duplicate key errors for INSERT
        if (str_contains($e->getMessage(), 'Duplicate entry')) continue;
        echo "    ⚠ Warning: " . $e->getMessage() . "\n";
    }
}
echo "    ✓ {$tables} tabel, {$views} view berhasil dibuat\n\n";

// 5. Cek data admin
echo "[4] Memeriksa data admin default...\n";
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
$adminCount = $stmt->fetch()['cnt'];
echo "    ✓ {$adminCount} akun admin ditemukan\n\n";

// 6. Cek tabel yang ada
echo "[5] Daftar tabel:\n";
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch()) {
    echo "    - " . reset($row) . "\n";
}

// 7. Cek apakah data guru sudah diimport
echo "\n[6] Memeriksa data master...\n";
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM guru");
$guruCount = $stmt->fetch()['cnt'];
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM siswa");
$siswaCount = $stmt->fetch()['cnt'];
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM kelas");
$kelasCount = $stmt->fetch()['cnt'];

echo "    Guru: {$guruCount}\n";
echo "    Siswa: {$siswaCount}\n";
echo "    Kelas: {$kelasCount}\n";

if ($guruCount == 0) {
    echo "\n    ⚠ Data guru belum diimport. Jalankan:\n";
    echo "      php import/import-guru.php\n";
    echo "      php import/import-siswa.php\n";
}

echo "\n===========================================\n";
echo " Setup Selesai!\n";
echo "===========================================\n";
echo "\n Buka: http://localhost/Sistem_Tahfidz_Restu2/\n";
echo " Login Admin: 000001 / admin123\n\n";
