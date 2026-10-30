<?php
/**
 * Model: Guru
 * Profil lengkap guru dan karyawan
 */

require_once __DIR__ . '/BaseModel.php';

class GuruModel extends BaseModel {

    public function getAll(?string $search = null, int $page = 1, int $perPage = 20): array {
        $where = "g.id IS NOT NULL";
        $params = [];

        if ($search) {
            $where .= " AND (g.nama_lengkap LIKE :search OR g.nuptk LIKE :search2 OR g.nik LIKE :search3 OR g.inobetik LIKE :search4)";
            $params[':search']  = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
            $params[':search4'] = "%{$search}%";
        }

        $offset = (int) (($page - 1) * $perPage);
        $limit  = (int) $perPage;

        $total = $this->findScalar("
            SELECT COUNT(*) FROM guru g WHERE {$where}
        ", $params, 0);

        $data = $this->findAll("
            SELECT g.*, g.nik, g.inobetik,
                   u.nomor_identitas, u.is_active AS akun_aktif, u.last_login,
                   k.nama_kelas, k.kode_kelas, k.jenis_kelas, k.jenjang
            FROM guru g
            JOIN users u ON u.id = g.user_id
            LEFT JOIN kelas k ON k.guru_id = g.id AND k.jenjang != 'TPA'
            WHERE {$where}
            ORDER BY g.nama_lengkap ASC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return [
            'data' => $data,
            'pagination' => [
                'total'       => (int) $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    public function getById(int $id): ?array {
        return $this->findOne("
            SELECT g.*, g.nik, g.inobetik,
                   u.nomor_identitas, u.is_active AS akun_aktif,
                   k.nama_kelas, k.kode_kelas, k.jenis_kelas, k.jenjang
            FROM guru g
            JOIN users u ON u.id = g.user_id
            LEFT JOIN kelas k ON k.guru_id = g.id AND k.jenjang != 'TPA'
            WHERE g.id = :id
        ", [':id' => $id]);
    }

    public function getByUserId(int $userId): ?array {
        return $this->findOne("
            SELECT g.*, g.nik, g.inobetik,
                   u.nomor_identitas, u.is_active AS akun_aktif,
                   k.nama_kelas, k.kode_kelas, k.id AS kelas_id, k.jenis_kelas, k.jenjang
            FROM guru g
            JOIN users u ON u.id = g.user_id
            LEFT JOIN kelas k ON k.guru_id = g.id AND k.jenjang != 'TPA'
            WHERE g.user_id = :user_id
        ", [':user_id' => $userId]);
    }

    public function getKelasByGuruId(int $guruId): ?array {
        return $this->findOne("
            SELECT k.*
            FROM kelas k
            WHERE k.guru_id = :guru_id AND k.is_active = 1 AND k.jenjang != 'TPA'
        ", [':guru_id' => $guruId]);
    }

    public function create(array $data): int {
        return $this->insert('guru', $data);
    }

    public function updateGuru(int $id, array $data): int {
        return parent::update('guru', $id, $data);
    }

    public function countAll(): int {
        return $this->count('guru');
    }
}
