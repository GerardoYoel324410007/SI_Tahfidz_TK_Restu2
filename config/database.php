<?php
/**
 * Konfigurasi Koneksi Database - PDO MySQL
 * Sistem Informasi Tahfidz KB-BA-TPA "Restu 2"
 */

date_default_timezone_set('Asia/Jakarta');

if (!class_exists('Database')) {

class Database {
    private static $instance = null;
    private $pdo;

    // Konfigurasi database
    private $host = 'localhost';
    private $dbname = 'tahfidz_restu2';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';

    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            $this->pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            // Log error, jangan tampilkan ke user
            error_log("Database connection failed: " . $e->getMessage());
            die("Koneksi database gagal. Silakan hubungi administrator.");
        }
    }

    // Singleton pattern: hanya 1 instance koneksi
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Mendapatkan PDO instance
    public function getConnection() {
        return $this->pdo;
    }

    // Mencegah clone
    private function __clone() {}

    // Mencegah unserialize
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}

} // end class_exists('Database')

/**
 * Helper function: mendapatkan koneksi database
 * Usage: $db = getDB(); $stmt = $db->prepare("...");
 */
if (!function_exists('getDB')) {
    function getDB() {
        return Database::getInstance()->getConnection();
    }
}
