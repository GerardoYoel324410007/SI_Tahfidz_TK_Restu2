<?php
/**
 * Model: SuratAlQuran
 * Master data surat Al-Quran yang bisa dikelola admin
 */

require_once __DIR__ . '/BaseModel.php';

class SuratModel extends BaseModel {

    public function getAll(bool $activeOnly = true): array {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        return $this->findAll("
            SELECT * FROM surat_al_quran {$where}
            ORDER BY nomor_surat ASC
        ");
    }

    public function getById(int $id): ?array {
        return $this->findOne("SELECT * FROM surat_al_quran WHERE id = :id", [':id' => $id]);
    }

    public function create(array $data): int {
        return $this->insert('surat_al_quran', $data);
    }

    public function updateSurat(int $id, array $data): int {
        return parent::update('surat_al_quran', $id, $data);
    }

    public function deactivate(int $id): int {
        return parent::update('surat_al_quran', $id, ['is_active' => 0]);
    }

    public function isNomorExists(int $nomorSurat, ?int $excludeId = null): bool {
        $query = "SELECT COUNT(*) FROM surat_al_quran WHERE nomor_surat = :nomor";
        $params = [':nomor' => $nomorSurat];
        if ($excludeId) {
            $query .= " AND id != :exc_id";
            $params[':exc_id'] = $excludeId;
        }
        return (int) $this->findScalar($query, $params, 0) > 0;
    }
}
