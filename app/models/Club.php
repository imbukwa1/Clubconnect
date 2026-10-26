<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;
use PDOException;

final class Club
{
    private ?array $columns = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function all(?string $search = null, ?string $category = null): array
    {
        $columns = $this->columns();
        $id = isset($columns['club_id']) ? 'club_id' : 'id';
        $contactEmail = isset($columns['contact_email']) ? 'contact_email' : "''";
        $contactPhone = isset($columns['contact_phone']) ? 'contact_phone' : "''";
        $query = 'SELECT ' . $id . ' AS club_id, name, category, description, '
            . $contactEmail . ' AS contact_email, ' . $contactPhone . ' AS contact_phone '
            . 'FROM clubs WHERE 1=1';
        $parameters = [];

        if ($search !== null && $search !== '') {
            $query .= ' AND (name LIKE :search_name OR description LIKE :search_description)';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_description'] = '%' . $search . '%';
        }
        if ($category !== null && $category !== '') {
            $query .= ' AND category = :category';
            $parameters['category'] = $category;
        }
        $query .= ' ORDER BY name ASC';

        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function find(int $clubId): ?array
    {
        if ($clubId < 1) {
            return null;
        }

        $columns = $this->columns();
        $id = isset($columns['club_id']) ? 'club_id' : 'id';
        $contactEmail = isset($columns['contact_email']) ? 'contact_email' : "''";
        $contactPhone = isset($columns['contact_phone']) ? 'contact_phone' : "''";
        $statement = $this->pdo->prepare(
            'SELECT ' . $id . ' AS club_id, name, category, description, '
            . $contactEmail . ' AS contact_email, ' . $contactPhone . ' AS contact_phone '
            . 'FROM clubs WHERE ' . $id . ' = :club_id LIMIT 1'
        );
        $statement->execute(['club_id' => $clubId]);
        $club = $statement->fetch();

        return $club === false ? null : $club;
    }

    public function create(array $data): int
    {
        $duplicate = $this->pdo->prepare('SELECT 1 FROM clubs WHERE name = :name LIMIT 1');
        $duplicate->execute(['name' => $data['name']]);
        if ($duplicate->fetchColumn() !== false) {
            throw new PDOException('Duplicate club name.', 23000);
        }
        $columns = $this->columns();
        if (isset($columns['contact_phone'])) {
            $statement = $this->pdo->prepare(
                'INSERT INTO clubs (name, category, description, contact_email, contact_phone) '
                . 'VALUES (:name, :category, :description, :contact_email, :contact_phone)'
            );
            $statement->execute([
                'name' => $data['name'], 'category' => $data['category'], 'description' => $data['description'],
                'contact_email' => $data['contact_email'], 'contact_phone' => null,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO clubs (name, category, description, contact_person, contact_email) '
                . 'VALUES (:name, :category, :description, :contact_person, :contact_email)'
            );
            $statement->execute([
                'name' => $data['name'], 'category' => $data['category'], 'description' => $data['description'],
                'contact_person' => null, 'contact_email' => $data['contact_email'],
            ]);
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $clubId, array $data): bool
    {
        if ($clubId < 1 || $this->find($clubId) === null) {
            return false;
        }
        $duplicate = $this->pdo->prepare('SELECT 1 FROM clubs WHERE name = :name AND ' . (isset($this->columns()['club_id']) ? 'club_id' : 'id') . ' <> :club_id LIMIT 1');
        $duplicate->execute(['name' => $data['name'], 'club_id' => $clubId]);
        if ($duplicate->fetchColumn() !== false) {
            throw new PDOException('Duplicate club name.', 23000);
        }
        $columns = $this->columns();
        if (isset($columns['contact_phone'])) {
            $statement = $this->pdo->prepare(
                'UPDATE clubs SET name = :name, category = :category, description = :description, '
                . 'contact_email = :contact_email, contact_phone = :contact_phone WHERE club_id = :club_id'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE clubs SET name = :name, category = :category, description = :description, '
                . 'contact_email = :contact_email WHERE id = :club_id'
            );
        }
        $parameters = [
            'name' => $data['name'], 'category' => $data['category'], 'description' => $data['description'],
            'contact_email' => $data['contact_email'], 'club_id' => $clubId,
        ];
        if (isset($columns['contact_phone'])) {
            $parameters['contact_phone'] = null;
        }
        $statement->execute($parameters);
        return true;
    }

    public function delete(int $clubId): string
    {
        if ($clubId < 1 || $this->find($clubId) === null) {
            return 'not_found';
        }
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $clubColumn = isset($this->columns()['club_id']) ? 'club_id' : 'id';
            $lock = $this->pdo->prepare('SELECT ' . $clubColumn . ' FROM clubs WHERE ' . $clubColumn . ' = :club_id FOR UPDATE');
            $lock->execute(['club_id' => $clubId]);
            $events = $this->countRelated('events', 'club_id', $clubId);
            $members = $this->countRelated('club_members', 'club_id', $clubId);
            if ($events > 0 || $members > 0) {
                if ($startedTransaction) {
                    $this->pdo->rollBack();
                }
                return $events > 0 ? 'has_events' : 'has_members';
            }
            $statement = $this->pdo->prepare('DELETE FROM clubs WHERE ' . $clubColumn . ' = :club_id');
            $statement->execute(['club_id' => $clubId]);
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return 'deleted';
        } catch (PDOException $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function countRelated(string $table, string $column, int $id): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = :id');
        $statement->execute(['id' => $id]);
        return (int) $statement->fetchColumn();
    }

    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => 'clubs']);
        $this->columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $this->columns[(string) $column] = true;
        }

        return $this->columns;
    }
}
