<?php
/**
 * Model: PermintaanPassword
 * Mengelola permintaan ganti password yang diajukan user dan disetujui admin.
 */

require_once __DIR__ . '/BaseModel.php';

class PermintaanPasswordModel extends BaseModel {

    /**
     * Buat permintaan baru.
     * Pastikan tidak ada permintaan aktif (status=menunggu) sebelum memanggil ini.
     */
    public function create(int $userId, string $passwordHashBaru): int {
        return $this->insert('permintaan_password', [
            'user_id'            => $userId,
            'password_hash_baru' => $passwordHashBaru,
            'status'             => 'menunggu',
        ]);
    }

    /**
     * Cek apakah user punya permintaan aktif (status = menunggu).
     */
    public function getAktifByUserId(int $userId): ?array {
        return $this->findOne("
            SELECT * FROM permintaan_password
            WHERE user_id = :uid AND status = 'menunggu'
            ORDER BY created_at DESC
            LIMIT 1
        ", [':uid' => $userId]);
    }

    /**
     * Ambil permintaan terbaru milik user (untuk tampilan status di UI).
     */
    public function getTerbaruByUserId(int $userId): ?array {
        return $this->findOne("
            SELECT pp.*, u.nomor_identitas AS admin_nomor
            FROM permintaan_password pp
            LEFT JOIN users u ON u.id = pp.admin_id
            WHERE pp.user_id = :uid
            ORDER BY pp.created_at DESC
            LIMIT 1
        ", [':uid' => $userId]);
    }

    /**
     * Batalkan semua permintaan aktif milik user (dipakai saat user mau ajukan baru setelah ditolak).
     */
    public function batalkanAktif(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE permintaan_password
            SET status = 'ditolak', alasan_tolak = 'Dibatalkan oleh pengguna'
            WHERE user_id = :uid AND status = 'menunggu'
        ");
        $stmt->execute([':uid' => $userId]);
    }

    /**
     * Ambil daftar semua permintaan untuk panel admin, dengan filter & paginasi.
     *
     * @param string $status  'menunggu' | 'disetujui' | 'ditolak' | '' (semua)
     * @param int    $page
     * @param int    $perPage
     */
    public function getAll(string $status = '', int $page = 1, int $perPage = 20): array {
        $where  = '1=1';
        $params = [];

        if ($status !== '') {
            $where .= ' AND pp.status = :status';
            $params[':status'] = $status;
        }

        $offset = ($page - 1) * $perPage;
        $total  = $this->findScalar("
            SELECT COUNT(*) FROM permintaan_password pp WHERE {$where}
        ", $params, 0);

        $data = $this->findAll("
            SELECT
                pp.*,
                u.nomor_identitas,
                u.role,
                COALESCE(g.nama_lengkap, s.nama_lengkap, 'Administrator') AS nama_user,
                adm.nomor_identitas AS admin_nomor
            FROM permintaan_password pp
            JOIN  users u ON u.id = pp.user_id
            LEFT JOIN guru  g ON g.user_id = u.id
            LEFT JOIN siswa s ON s.user_id = u.id
            LEFT JOIN users adm ON adm.id = pp.admin_id
            WHERE {$where}
            ORDER BY
                FIELD(pp.status, 'menunggu', 'disetujui', 'ditolak'),
                pp.created_at DESC
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
     * Hitung jumlah permintaan yang statusnya 'menunggu' (untuk badge notifikasi admin).
     */
    public function countPending(): int {
        return $this->count('permintaan_password', "status = 'menunggu'");
    }

    /**
     * Setujui permintaan → UPDATE password user + update status.
     * Dikerjakan dalam satu transaksi.
     */
    public function approve(int $id, int $adminId): void {
        // Ambil data permintaan
        $pp = $this->findOne("SELECT * FROM permintaan_password WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$pp || $pp['status'] !== 'menunggu') {
            throw new \Exception('Permintaan tidak ditemukan atau sudah diproses.');
        }

        $this->db->beginTransaction();
        try {
            // Update password user
            $this->db->prepare("UPDATE users SET password_hash = :hash WHERE id = :uid")
                ->execute([':hash' => $pp['password_hash_baru'], ':uid' => $pp['user_id']]);

            // Update status permintaan
            $this->db->prepare("
                UPDATE permintaan_password
                SET status = 'disetujui', admin_id = :admin, processed_at = NOW()
                WHERE id = :id
            ")->execute([':admin' => $adminId, ':id' => $id]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Tolak permintaan.
     */
    public function reject(int $id, int $adminId, string $alasan): void {
        $pp = $this->findOne("SELECT * FROM permintaan_password WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$pp || $pp['status'] !== 'menunggu') {
            throw new \Exception('Permintaan tidak ditemukan atau sudah diproses.');
        }

        $this->db->prepare("
            UPDATE permintaan_password
            SET status = 'ditolak', alasan_tolak = :alasan, admin_id = :admin, processed_at = NOW()
            WHERE id = :id
        ")->execute([':alasan' => $alasan, ':admin' => $adminId, ':id' => $id]);
    }
}
