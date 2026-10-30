<?php
/**
 * Model: Siswa
 * Profil lengkap siswa
 */

require_once __DIR__ . '/BaseModel.php';

class SiswaModel extends BaseModel {

    public function getAll(?int $kelasId = null, ?string $search = null, int $page = 1, int $perPage = 20): array {
        $where = "1=1";
        $params = [];

        if ($kelasId) {
            $where .= " AND s.kelas_id = :kelas_id";
            $params[':kelas_id'] = $kelasId;
        }

        if ($search) {
            $where .= " AND (s.nama_lengkap LIKE :search OR s.nis LIKE :search2)";
            $params[':search'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
        }

        $offset = (int) (($page - 1) * $perPage);
        $limit = (int) $perPage;

        $total = $this->findScalar("
            SELECT COUNT(*) FROM siswa s WHERE {$where} AND s.is_active = 1
        ", $params, 0);

        $data = $this->findAll("
            SELECT s.*, u.nomor_identitas, u.is_active AS akun_aktif,
                   k.nama_kelas, k.kode_kelas
            FROM siswa s
            JOIN users u ON u.id = s.user_id
            JOIN kelas k ON k.id = s.kelas_id
            WHERE {$where} AND s.is_active = 1
            ORDER BY k.nama_kelas, s.nama_lengkap
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return [
            'data' => $data,
            'pagination' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    public function getById(int $id): ?array {
        return $this->findOne("
            SELECT s.*, u.nomor_identitas, u.is_active AS akun_aktif,
                   k.nama_kelas, k.kode_kelas
            FROM siswa s
            JOIN users u ON u.id = s.user_id
            JOIN kelas k ON k.id = s.kelas_id
            WHERE s.id = :id
        ", [':id' => $id]);
    }

    public function getByUserId(int $userId): ?array {
        return $this->findOne("
            SELECT s.*, u.nomor_identitas,
                   k.nama_kelas, k.kode_kelas
            FROM siswa s
            JOIN users u ON u.id = s.user_id
            JOIN kelas k ON k.id = s.kelas_id
            WHERE s.user_id = :user_id AND s.is_active = 1
        ", [':user_id' => $userId]);
    }

    public function getByKelas(int $kelasId): array {
        return $this->findAll("
            SELECT s.*, u.nomor_identitas
            FROM siswa s
            JOIN users u ON u.id = s.user_id
            WHERE s.kelas_id = :kelas_id AND s.is_active = 1
            ORDER BY s.nama_lengkap
        ", [':kelas_id' => $kelasId]);
    }

    public function create(array $data): int {
        return $this->insert('siswa', $data);
    }

    public function updateSiswa(int $id, array $data): int {
        return parent::update('siswa', $id, $data);
    }

    public function countByKelas(int $kelasId): int {
        return $this->count('siswa', 'kelas_id = :kelas_id AND is_active = 1', [':kelas_id' => $kelasId]);
    }

    public function countAll(): int {
        return $this->count('siswa', 'is_active = 1');
    }
}
