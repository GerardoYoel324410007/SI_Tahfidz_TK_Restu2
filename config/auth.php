<?php
/**
 * Konfigurasi Autentikasi dan Manajemen Sesi
 * Sistem Informasi Tahfidz KB-BA-TPA "Restu 2"
 */

/**
 * Helper function: mendapatkan base path aplikasi
 */
if (!function_exists('getBasePath')) {
    function getBasePath() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $segments = explode('/', trim($scriptName, '/'));
        $baseSegments = [];
        $reserved = ['views', 'api', 'assets', 'uploads', 'database', 'config', 'controllers', 'models', 'portal', 'vendor'];
        
        foreach ($segments as $segment) {
            if (in_array($segment, $reserved) || str_ends_with($segment, '.php')) {
                break;
            }
            $baseSegments[] = $segment;
        }
        
        return empty($baseSegments) ? '' : '/' . implode('/', $baseSegments);
    }
}

if (!class_exists('Auth')) {
class Auth {


    /**
     * Inisialisasi session dengan pengamanan
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            // Session timeout: 8 jam
            ini_set('session.gc_maxlifetime', 28800);
            ini_set('session.cookie_lifetime', 28800);

            // Pengamanan cookie
            $isSecure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
            session_set_cookie_params([
                'lifetime' => 28800,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }
    }

    /**
     * Regenerate session ID (panggil setelah login berhasil)
     */
    public static function regenerateSession() {
        self::init();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Cek apakah user sudah login
     */
    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }

    /**
     * Cek apakah user memiliki role tertentu
     */
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] === $role;
    }

    /**
     * Proteksi halaman: redirect jika bukan role yang sesuai
     */
    public static function requireRole($role) {
        self::init();

        if (!self::isLoggedIn()) {
            header('Location: ' . getBasePath() . '/views/shared/login.html');
            exit;
        }

        if ($_SESSION['role'] !== $role) {
            // Redirect ke halaman sesuai role
            self::redirectByRole();
            exit;
        }
    }

    /**
     * Proteksi halaman: redirect jika bukan salah satu dari role yang diizinkan
     */
    public static function requireAnyRole(array $roles) {
        self::init();

        if (!self::isLoggedIn()) {
            header('Location: ' . getBasePath() . '/views/shared/login.html');
            exit;
        }

        if (!in_array($_SESSION['role'], $roles)) {
            // Redirect ke halaman sesuai role
            self::redirectByRole();
            exit;
        }
    }

    /**
     * Proteksi halaman: redirect jika belum login
     */
    public static function requireLogin() {
        self::init();

        if (!self::isLoggedIn()) {
            header('Location: ' . getBasePath() . '/views/shared/login.html');
            exit;
        }
    }

    /**
     * Redirect ke halaman sesuai role
     */
    public static function redirectByRole() {
        $basePath = getBasePath();
        
        if (!self::isLoggedIn()) {
            header('Location: ' . $basePath . '/views/shared/login.html');
            exit;
        }

        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: ' . $basePath . '/views/admin/dashboard.html');
                break;
            case 'guru':
            case 'karyawan':
                header('Location: ' . $basePath . '/views/guru/beranda.html');
                break;
            case 'wali_murid':
                header('Location: ' . $basePath . '/views/wali_murid/beranda.html');
                break;
            default:
                self::logout();
                break;
        }
        exit;
    }

    /**
     * Login user
     */
    public static function login($nomor_identitas, $password) {
        $db = getDB();

        // Cari user berdasarkan nomor identitas
        $stmt = $db->prepare("
            SELECT u.id, u.nomor_identitas, u.password_hash, u.role, u.is_active,
                   g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   s.nama_lengkap AS nama_siswa, s.id AS siswa_id, s.kelas_id
            FROM users u
            LEFT JOIN guru g ON g.user_id = u.id
            LEFT JOIN siswa s ON s.user_id = u.id
            WHERE u.nomor_identitas = :nomor AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':nomor' => $nomor_identitas]);
        $user = $stmt->fetch();

        if (!$user) {
            // Log: user tidak ditemukan
            if (class_exists('LoginLogModel')) {
                (new LoginLogModel())->record($nomor_identitas, 'gagal', null, null, null, 'user_tidak_ditemukan');
            }
            return ['status' => 'error', 'message' => 'Nomor identitas tidak terdaftar atau akun nonaktif.'];
        }

        // Verifikasi password
        if (!password_verify($password, $user['password_hash'])) {
            // Log: password salah
            if (class_exists('LoginLogModel')) {
                (new LoginLogModel())->record($nomor_identitas, 'gagal', (int)$user['id'], $user['role'], null, 'password_salah');
            }
            return ['status' => 'error', 'message' => 'Password salah.'];
        }

        // Regenerate session ID
        self::regenerateSession();

        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nomor_identitas'] = $user['nomor_identitas'];

        // Set data tambahan berdasarkan role
        if ($user['role'] === 'guru' || $user['role'] === 'karyawan') {
            $_SESSION['nama'] = $user['nama_guru'];
            $_SESSION['guru_id'] = $user['guru_id'];
        } elseif ($user['role'] === 'wali_murid') {
            $_SESSION['nama'] = $user['nama_siswa'];
            $_SESSION['siswa_id'] = $user['siswa_id'];
            $_SESSION['kelas_id'] = $user['kelas_id'];
        } else {
            $_SESSION['nama'] = 'Administrator';
        }

        // Update last_login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);

        // Log: login berhasil
        if (class_exists('LoginLogModel')) {
            $namaLog = $_SESSION['nama'] ?? null;
            (new LoginLogModel())->record($nomor_identitas, 'berhasil', (int)$user['id'], $user['role'], $namaLog);
        }

        // Tentukan redirect URL (relative path)
        $redirect_map = [
            'admin' => '/views/admin/dashboard.html',
            'guru' => '/views/guru/beranda.html',
            'karyawan' => '/views/guru/beranda.html',
            'wali_murid' => '/views/wali_murid/beranda.html'
        ];

        return [
            'status'               => 'success',
            'role'                 => $user['role'],
            'nama'                 => $_SESSION['nama'],
            'redirect_url'         => $redirect_map[$user['role']] ?? '/views/shared/login.html',
        ];
    }

    /**
     * Logout user
     */
    public static function logout() {
        self::init();
        session_unset();
        session_destroy();
        $basePath = getBasePath();
        header('Location: ' . $basePath . '/views/shared/login.html');
        exit;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        self::init();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validasi CSRF token
     */
    public static function validateCSRFToken($token) {
        self::init();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Mendapatkan data user yang sedang login
     */
    public static function getCurrentUser() {
        self::init();
        if (!self::isLoggedIn()) return null;

        return [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'],
            'nama' => $_SESSION['nama'] ?? '',
            'nomor_identitas' => $_SESSION['nomor_identitas'] ?? '',
            'guru_id' => $_SESSION['guru_id'] ?? null,
            'siswa_id' => $_SESSION['siswa_id'] ?? null,
            'kelas_id' => $_SESSION['kelas_id'] ?? null,
        ];
    }
}
} // end class_exists('Auth')
