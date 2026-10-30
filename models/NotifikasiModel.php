<?php
/**
 * Model: Notifikasi
 * Notifikasi real-time untuk pengguna
 */

require_once __DIR__ . '/BaseModel.php';

class NotifikasiModel extends BaseModel {

    public function create(int $userId, string $judul, string $pesan): int {
        return $this->insert('notifikasi', [
            'user_id' => $userId,
            'judul' => $judul,
            'pesan' => $pesan
        ]);
    }

    public function bulkCreate(array $userIds, string $judul, string $pesan): int {
        $count = 0;
        foreach ($userIds as $userId) {
            $this->create($userId, $judul, $pesan);
            $count++;
        }
        return $count;
    }

    public function getUnread(int $userId): array {
        return $this->findAll("
            SELECT * FROM notifikasi
            WHERE user_id = :user_id AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 20
        ", [':user_id' => $userId]);
    }

    public function countUnread(int $userId): int {
        return $this->count('notifikasi', 'user_id = :user_id AND is_read = 0', [':user_id' => $userId]);
    }

    public function markRead(int $id): int {
        return $this->update('notifikasi', $id, ['is_read' => 1]);
    }

    public function markAllRead(int $userId): int {
        $stmt = $this->db->prepare("
            UPDATE notifikasi SET is_read = 1
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Ambil user_id wali murid dari daftar kelas
     */
    public function getWaliMuridByKelas(array $kelasIds): array {
        if (empty($kelasIds)) return [];

        $placeholders = implode(',', array_fill(0, count($kelasIds), '?'));
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id
            FROM siswa s
            JOIN users u ON u.id = s.user_id
            WHERE s.kelas_id IN ({$placeholders}) AND s.is_active = 1
        ");
        $stmt->execute($kelasIds);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Ambil user_id guru dari kelas
     */
    public function getGuruByKelas(array $kelasIds): array {
        if (empty($kelasIds)) return [];

        $placeholders = implode(',', array_fill(0, count($kelasIds), '?'));
        $stmt = $this->db->prepare("
            SELECT DISTINCT g.user_id
            FROM kelas k
            JOIN guru g ON g.id = k.guru_id
            WHERE k.id IN ({$placeholders}) AND k.is_active = 1
        ");
        $stmt->execute($kelasIds);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
