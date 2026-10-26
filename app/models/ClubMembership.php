<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;
use PDOException;

final class ClubMembership
{
    private ?array $columns = null;
    private ?string $clubIdColumn = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function isMember(int $userId, int $clubId): bool
    {
        if ($userId < 1 || $clubId < 1) {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM club_members WHERE user_id = :user_id AND club_id = :club_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'club_id' => $clubId]);

        return $statement->fetchColumn() !== false;
    }

    public function forUser(int $userId): array
    {
        $clubId = $this->clubIdColumn();
        $statement = $this->pdo->prepare(
            'SELECT c.' . $clubId . ' AS club_id, c.name, c.category, c.description, cm.joined_at '
            . 'FROM club_members AS cm JOIN clubs AS c ON c.' . $clubId . ' = cm.club_id '
            . 'WHERE cm.user_id = :user_id ORDER BY c.name ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function join(int $userId, int $clubId): bool
    {
        if ($userId < 1 || $clubId < 1) {
            return false;
        }

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            if (!$this->clubExists($clubId)) {
                if ($startedTransaction) {
                    $this->pdo->rollBack();
                }
                return false;
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO club_members (user_id, club_id) VALUES (:user_id, :club_id)'
            );
            $statement->execute(['user_id' => $userId, 'club_id' => $clubId]);
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    public function leave(int $userId, int $clubId): bool
    {
        if ($userId < 1 || $clubId < 1) {
            return false;
        }
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM club_members WHERE user_id = :user_id AND club_id = :club_id'
            );
            $statement->execute(['user_id' => $userId, 'club_id' => $clubId]);
            $changed = $statement->rowCount() > 0;
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return $changed;
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function clubExists(int $clubId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM clubs WHERE ' . $this->clubIdColumn() . ' = :club_id FOR UPDATE'
        );
        $statement->execute(['club_id' => $clubId]);
        return $statement->fetchColumn() !== false;
    }

    private function clubIdColumn(): string
    {
        if ($this->clubIdColumn !== null) {
            return $this->clubIdColumn;
        }
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => 'clubs']);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN);
        $this->clubIdColumn = in_array('club_id', $columns, true) ? 'club_id' : 'id';
        return $this->clubIdColumn;
    }
}
