<?php
/**
 * Model: Kelas
 * Daftar kelas aktif dan wali kelas — dengan CRUD penuh untuk admin
 */

require_once __DIR__ . '/BaseModel.php';

class KelasModel extends BaseModel {

    /**
     * Ambil semua kelas aktif (kecuali TPA) untuk sistem tahfidz
     */
    public function getAll(bool $excludeTpa = true): array {
        $tpaFilter = $excludeTpa ? "AND k.jenjang != 'TPA'" : "";
        return $this->findAll("
            SELECT k.*, g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   COUNT(s.id) AS jumlah_siswa
            FROM kelas k
            LEFT JOIN guru g ON g.id = k.guru_id
            LEFT JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            WHERE k.is_active = 1 {$tpaFilter}
            GROUP BY k.id
            ORDER BY k.jenjang, k.urutan, k.kode_kelas
        ");
    }

    /**
     * Ambil kelas berdasarkan jenjang (misal hanya TKA atau TKB)
     */
    public function getByJenjang(string $jenjang): array {
        return $this->findAll("
            SELECT k.*, g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   COUNT(s.id) AS jumlah_siswa
            FROM kelas k
            LEFT JOIN guru g ON g.id = k.guru_id
            LEFT JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            WHERE k.is_active = 1 AND k.jenjang = :jenjang
            GROUP BY k.id
            ORDER BY k.urutan, k.kode_kelas
        ", [':jenjang' => $jenjang]);
    }

    /**
     * Ambil kelas intensif saja
     */
    public function getIntensif(): array {
        return $this->findAll("
            SELECT k.*, g.nama_lengkap AS nama_guru,
                   COUNT(s.id) AS jumlah_siswa
            FROM kelas k
            LEFT JOIN guru g ON g.id = k.guru_id
            LEFT JOIN siswa s ON s.kelas_id = k.id AND s.is_active = 1
            WHERE k.is_active = 1 AND k.jenis_kelas IN ('intensif','koordinator')
            GROUP BY k.id
            ORDER BY k.jenjang, k.urutan
        ");
    }

    public function getById(int $id): ?array {
        return $this->findOne("
            SELECT k.*, g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   g.user_id AS guru_user_id
            FROM kelas k
            LEFT JOIN guru g ON g.id = k.guru_id
            WHERE k.id = :id
        ", [':id' => $id]);
    }

    public function getSiswaCount(int $kelasId): int {
        return $this->count('siswa', 'kelas_id = :kelas_id AND is_active = 1', [':kelas_id' => $kelasId]);
    }

    public function updateKelas(int $id, array $data): int {
        return parent::update('kelas', $id, $data);
    }

    /**
     * Buat kelas baru (CRUD oleh admin)
     */
    public function createKelas(array $data): int {
        return $this->insert('kelas', $data);
    }

    /**
     * Hapus (nonaktifkan) kelas — tidak hapus data siswa
     */
    public function deactivateKelas(int $id): int {
        return parent::update('kelas', $id, ['is_active' => 0]);
    }

    /**
     * Cek apakah kode kelas sudah dipakai
     */
    public function isKodeExists(string $kodeKelas, ?int $excludeId = null): bool {
        $query = "SELECT COUNT(*) FROM kelas WHERE kode_kelas = :kode";
        $params = [':kode' => $kodeKelas];
        if ($excludeId) {
            $query .= " AND id != :exc_id";
            $params[':exc_id'] = $excludeId;
        }
        return (int) $this->findScalar($query, $params, 0) > 0;
    }
}
