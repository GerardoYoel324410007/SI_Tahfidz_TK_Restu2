<?php
/**
 * Abstract Base Model
 * Helper CRUD dengan PDO Prepared Statements
 */

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel {

    protected $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Fetch semua baris
     */
    public function findAll(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch satu baris
     */
    public function findOne(string $sql, array $params = []): ?array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch satu kolom (scalar)
     */
    public function findScalar(string $sql, array $params = [], $default = null) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    }

    /**
     * Insert data ke tabel, return last insert ID
     */
    protected function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_map(fn($col) => ":$col", array_keys($data)));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update data berdasarkan ID, return affected rows
     */
    protected function update(string $table, int $id, array $data): int {
        $sets = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($data)));
        $data['id'] = $id;

        $sql = "UPDATE {$table} SET {$sets} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $stmt->rowCount();
    }

    /**
     * Soft delete (set is_active = 0)
     */
    protected function softDelete(string $table, int $id): int {
        return $this->update($table, $id, ['is_active' => 0]);
    }

    /**
     * Hard delete
     */
    protected function delete(string $table, int $id): int {
        $sql = "DELETE FROM {$table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }

    /**
     * Hitung jumlah baris
     */
    protected function count(string $table, string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) FROM {$table}";
        if ($where) $sql .= " WHERE {$where}";
        return (int) $this->findScalar($sql, $params, 0);
    }

    /**
     * Cek apakah baris ada
     */
    protected function exists(string $table, string $where, array $params): bool {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * Begin transaction
     */
    protected function beginTransaction(): void {
        $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     */
    protected function commit(): void {
        $this->db->commit();
    }

    /**
     * Rollback transaction
     */
    protected function rollback(): void {
        $this->db->rollBack();
    }
}
