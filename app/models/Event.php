<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;

final class Event
{
    private ?array $columns = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function all(?string $search = null, ?string $category = null): array
    {
        $query = $this->baseQuery();
        $parameters = [];
        if ($search !== null && $search !== '') {
            $query .= ' AND (e.title LIKE :search_title OR e.description LIKE :search_description '
                . 'OR e.venue LIKE :search_venue OR c.name LIKE :search_club)';
            $searchValue = '%' . $search . '%';
            $parameters['search_title'] = $searchValue;
            $parameters['search_description'] = $searchValue;
            $parameters['search_venue'] = $searchValue;
            $parameters['search_club'] = $searchValue;
        }
        if ($category !== null && $category !== '') {
            $query .= ' AND c.category = :category';
            $parameters['category'] = $category;
        }
        $query .= ' ORDER BY e.event_date ASC, event_time ASC, e.title ASC';

        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function find(int $eventId): ?array
    {
        if ($eventId < 1) {
            return null;
        }

        $query = $this->baseQuery() . ' AND e.' . $this->eventIdColumn() . ' = :event_id LIMIT 1';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['event_id' => $eventId]);
        $event = $statement->fetch();

        return $event === false ? null : $event;
    }

    public function forClub(int $clubId): array
    {
        if ($clubId < 1) {
            return [];
        }

        $query = $this->baseQuery() . ' AND e.club_id = :club_id '
            . 'ORDER BY e.event_date ASC, event_time ASC, e.title ASC';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['club_id' => $clubId]);

        return $statement->fetchAll();
    }

    private function baseQuery(): string
    {
        $columns = $this->columns();
        $eventId = isset($columns['event_id']) ? 'event_id' : 'id';
        $eventTime = isset($columns['event_time']) ? 'event_time' : 'start_time';
        $endTime = isset($columns['end_time']) ? 'end_time' : "''";
        $status = isset($columns['status']) ? 'status' : "'upcoming'";

        return 'SELECT e.' . $eventId . ' AS event_id, e.club_id, e.title, e.description, '
            . 'e.event_date, e.' . $eventTime . ' AS event_time, '
            . $endTime . ' AS event_end_time, e.venue, e.capacity, ' . $status . ' AS status, '
            . 'c.name AS club_name, c.category AS club_category '
            . 'FROM events AS e JOIN clubs AS c ON c.' . $this->clubIdColumn() . ' = e.club_id '
            . 'WHERE 1=1';
    }

    private function eventIdColumn(): string
    {
        return isset($this->columns()['event_id']) ? 'event_id' : 'id';
    }

    private function clubIdColumn(): string
    {
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => 'clubs']);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

        return in_array('club_id', $columns, true) ? 'club_id' : 'id';
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
        $statement->execute(['table_name' => 'events']);
        $this->columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $this->columns[(string) $column] = true;
        }

        return $this->columns;
    }
}
