<?php
/**
 * Model: Users
 * Akun login semua pengguna (Admin, Guru, Wali Murid)
 */

require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel {

    public function findById(int $id): ?array {
        return $this->findOne("
            SELECT u.*, g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   s.nama_lengkap AS nama_siswa, s.id AS siswa_id, s.kelas_id
            FROM users u
            LEFT JOIN guru g ON g.user_id = u.id
            LEFT JOIN siswa s ON s.user_id = u.id
            WHERE u.id = :id
        ", [':id' => $id]);
    }

    public function findByNomorIdentitas(string $nomor): ?array {
        return $this->findOne("
            SELECT u.*, g.nama_lengkap AS nama_guru, g.id AS guru_id,
                   s.nama_lengkap AS nama_siswa, s.id AS siswa_id, s.kelas_id
            FROM users u
            LEFT JOIN guru g ON g.user_id = u.id
            LEFT JOIN siswa s ON s.user_id = u.id
            WHERE u.nomor_identitas = :nomor AND u.is_active = 1
        ", [':nomor' => $nomor]);
    }

    public function create(array $data): int {
        return $this->insert('users', $data);
    }

    public function updatePassword(int $id, string $hash): int {
        return $this->update('users', $id, ['password_hash' => $hash]);
    }

    public function setActive(int $id, int $active): int {
        return $this->update('users', $id, ['is_active' => $active]);
    }

    public function updateUserInfo(int $id, array $data): int {
        return $this->update('users', $id, $data);
    }

    public function updateLastLogin(int $id): int {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }
}
