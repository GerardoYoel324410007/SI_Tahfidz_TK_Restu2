<?php
/**
 * Model: LoginLog
 * Audit trail semua percobaan login ke sistem.
 * Tabel ini bersifat APPEND-ONLY — tidak ada UPDATE/DELETE dari API manapun.
 */

require_once __DIR__ . '/BaseModel.php';

class LoginLogModel extends BaseModel {

    /**
     * Ambil IP address klien, perhatikan reverse proxy.
     */
    public static function getClientIp(): string {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if (!empty($val)) {
                // X-Forwarded-For bisa berisi daftar IP; ambil yang pertama
                $ip = trim(explode(',', $val)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return 'unknown';
    }

    /**
     * Ambil User-Agent dan potong di 255 karakter.
     */
    public static function getUserAgent(): string {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }

    /**
     * Catat satu percobaan login.
     *
     * @param string      $nomorIdentitas  Nomor identitas yang dimasukkan user
     * @param string      $status          'berhasil' | 'gagal'
     * @param int|null    $userId          ID user jika ditemukan
     * @param string|null $role            Role user (hanya jika berhasil)
     * @param string|null $nama            Nama user (hanya jika berhasil)
     * @param string|null $alasanGagal     'password_salah' | 'akun_nonaktif' | 'user_tidak_ditemukan'
     * @param string|null $ipAddress       Diambil otomatis jika null
     * @param string|null $userAgent       Diambil otomatis jika null
     */
    public function record(
        string  $nomorIdentitas,
        string  $status,
        ?int    $userId     = null,
        ?string $role       = null,
        ?string $nama       = null,
        ?string $alasanGagal = null,
        ?string $ipAddress  = null,
        ?string $userAgent  = null
    ): int {
        return $this->insert('login_log', [
            'user_id'         => $userId,
            'nomor_identitas' => substr($nomorIdentitas, 0, 50),
            'role'            => $role,
            'nama'            => $nama ? substr($nama, 0, 100) : null,
            'status'          => $status,
            'alasan_gagal'    => $alasanGagal,
            'ip_address'      => $ipAddress  ?? self::getClientIp(),
            'user_agent'      => $userAgent  ?? self::getUserAgent(),
        ]);
    }

    /**
     * Ambil daftar log dengan filter dan paginasi.
     *
     * @param array $filters  Keys: tanggal_dari, tanggal_sampai, role, status, search
     * @param int   $page
     * @param int   $perPage
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 50): array {
        [$where, $params] = $this->buildWhere($filters);

        $offset = ($page - 1) * $perPage;
        $total  = $this->findScalar("SELECT COUNT(*) FROM login_log WHERE {$where}", $params, 0);

        $data = $this->findAll("
            SELECT id, user_id, nomor_identitas, role, nama, status, alasan_gagal, ip_address, user_agent, created_at
            FROM login_log
            WHERE {$where}
            ORDER BY created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        return [
            'data'       => $data,
            'pagination' => [
                'total'       => (int) $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * Ringkasan statistik untuk rentang tanggal atau hari ini.
     */
    public function getSummary(array $filters = []): array {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->findOne("
            SELECT
                COUNT(*)                                                AS total,
                SUM(status = 'berhasil')                               AS berhasil,
                SUM(status = 'gagal')                                  AS gagal,
                COUNT(DISTINCT CASE WHEN status='berhasil' THEN nomor_identitas END) AS unik_user
            FROM login_log
            WHERE {$where}
        ", $params);

        return [
            'total'      => (int) ($row['total']      ?? 0),
            'berhasil'   => (int) ($row['berhasil']   ?? 0),
            'gagal'      => (int) ($row['gagal']       ?? 0),
            'unik_user'  => (int) ($row['unik_user']  ?? 0),
        ];
    }

    /**
     * Semua log (tanpa paginasi) untuk export CSV.
     * Limit keras 5000 baris untuk menghindari memory exhaustion.
     */
    public function getAllForExport(array $filters = []): array {
        [$where, $params] = $this->buildWhere($filters);
        return $this->findAll("
            SELECT id, nomor_identitas, nama, role, status, alasan_gagal, ip_address, user_agent, created_at
            FROM login_log
            WHERE {$where}
            ORDER BY created_at DESC
            LIMIT 5000
        ", $params);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildWhere(array $filters): array {
        $conds  = ['1=1'];
        $params = [];

        // Filter tanggal
        $dari    = $filters['tanggal_dari']    ?? date('Y-m-d');
        $sampai  = $filters['tanggal_sampai']  ?? date('Y-m-d');
        $conds[] = 'DATE(created_at) >= :dari';
        $conds[] = 'DATE(created_at) <= :sampai';
        $params[':dari']   = $dari;
        $params[':sampai'] = $sampai;

        // Filter role
        if (!empty($filters['role'])) {
            $conds[] = 'role = :role';
            $params[':role'] = $filters['role'];
        }

        // Filter status
        if (!empty($filters['status']) && in_array($filters['status'], ['berhasil', 'gagal'])) {
            $conds[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        // Pencarian nama atau nomor identitas
        // PDO tidak mengizinkan named parameter yang sama digunakan lebih dari sekali;
        // gunakan :search1 dan :search2 sebagai workaround.
        if (!empty($filters['search'])) {
            $conds[] = '(nomor_identitas LIKE :search1 OR nama LIKE :search2)';
            $val = '%' . $filters['search'] . '%';
            $params[':search1'] = $val;
            $params[':search2'] = $val;
        }

        return [implode(' AND ', $conds), $params];
    }
}
