<?php
/**
 * Model: Koreksi Detail
 * Detail kriteria penilaian koreksi
 */

require_once __DIR__ . '/BaseModel.php';

class KoreksiDetailModel extends BaseModel {

    public function create(int $setoranId, array $items): array {
        $ids = [];
        foreach ($items as $item) {
            $ids[] = $this->insert('koreksi_detail', [
                'setoran_id' => $setoranId,
                'kriteria' => $item['kriteria'],
                'nilai' => $item['nilai'],
                'catatan' => $item['catatan'] ?? null
            ]);
        }
        return $ids;
    }

    public function getBySetoran(int $setoranId): array {
        return $this->findAll("
            SELECT * FROM koreksi_detail
            WHERE setoran_id = :setoran_id
            ORDER BY id
        ", [':setoran_id' => $setoranId]);
    }

    public function deleteBySetoran(int $setoranId): int {
        $sql = "DELETE FROM koreksi_detail WHERE setoran_id = :setoran_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':setoran_id' => $setoranId]);
        return $stmt->rowCount();
    }
}
