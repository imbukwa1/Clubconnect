<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;

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
