<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;

final class Dashboard
{
    private ?array $users = null;
    private ?array $events = null;
    private ?string $clubIdColumn = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function student(int $userId): array
    {
        $joined = $this->scalar('SELECT COUNT(*) FROM club_members WHERE user_id = :user_id', ['user_id' => $userId]);
        $available = $this->scalar('SELECT COUNT(*) FROM clubs');
        $eventColumns = $this->eventColumns();
        $eventId = isset($eventColumns['event_id']) ? 'event_id' : 'id';
        $statusClause = isset($eventColumns['status']) ? " AND e.status = 'upcoming'" : '';
        $upcoming = $this->scalar(
            'SELECT COUNT(*) FROM event_registrations AS er JOIN events AS e '
            . 'ON e.' . $eventId . ' = er.event_id '
            . "WHERE er.user_id = :user_id AND er.status = 'registered' "
            . 'AND e.event_date >= CURRENT_DATE()' . $statusClause,
            ['user_id' => $userId]
        );

        return [
            'joined_clubs' => $joined,
            'upcoming_events' => $upcoming,
            'available_clubs' => $available,
            'recent_activity' => $this->studentRecentActivity($userId),
        ];
    }

    public function admin(): array
    {
        $users = $this->userColumns();
        $studentCount = isset($users['role'])
            ? $this->scalar("SELECT COUNT(*) FROM users WHERE role = 'student'")
            : $this->scalar('SELECT COUNT(*) FROM users');

        return [
            'students' => $studentCount,
            'clubs' => $this->scalar('SELECT COUNT(*) FROM clubs'),
            'events' => $this->scalar('SELECT COUNT(*) FROM events'),
            'memberships' => $this->scalar('SELECT COUNT(*) FROM club_members'),
            'registrations' => $this->scalar('SELECT COUNT(*) FROM event_registrations'),
            'recent_registrations' => $this->adminRecentRegistrations(),
            'upcoming_events' => $this->adminUpcomingEvents(),
            'club_activity' => $this->adminClubActivity(),
        ];
    }

    public function registrations(): array
    {
        $users = $this->userColumns();
        $events = $this->eventColumns();
        $eventId = isset($events['event_id']) ? 'event_id' : 'id';
        $eventTime = isset($events['event_time']) ? 'event_time' : 'start_time';
        $userId = $this->userIdColumn();
        $userName = isset($users['first_name']) ? "CONCAT(u.first_name, ' ', u.last_name)" : 'u.name';
        $studentIdentifier = isset($users['student_id']) ? 'u.student_id' : "''";
        $statement = $this->pdo->prepare(
            'SELECT er.status, er.registered_at, e.' . $eventId . ' AS event_id, e.title, '
            . 'e.event_date, e.' . $eventTime . ' AS event_time, ' . $userName . ' AS user_name, '
            . 'u.email, ' . $studentIdentifier . ' AS student_identifier '
            . 'FROM event_registrations AS er JOIN users AS u ON u.' . $userId . ' = er.user_id '
            . 'JOIN events AS e ON e.' . $eventId . ' = er.event_id '
            . 'ORDER BY er.registered_at DESC'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    private function studentRecentActivity(int $userId): array
    {
        $eventColumns = $this->eventColumns();
        $eventId = isset($eventColumns['event_id']) ? 'event_id' : 'id';
        $clubId = $this->clubIdColumn();
        $eventTime = isset($eventColumns['event_time']) ? 'event_time' : 'start_time';
        $statement = $this->pdo->prepare(
            'SELECT e.' . $eventId . ' AS event_id, e.title, e.event_date, e.' . $eventTime . ' AS event_time, '
            . 'c.name AS club_name, er.status, er.registered_at '
            . 'FROM event_registrations AS er JOIN events AS e ON e.' . $eventId . ' = er.event_id '
            . 'JOIN clubs AS c ON c.' . $clubId . ' = e.club_id '
            . 'WHERE er.user_id = :user_id ORDER BY er.registered_at DESC LIMIT 5'
        );
        $statement->execute(['user_id' => $userId]);
        return $statement->fetchAll();
    }

    private function adminRecentRegistrations(): array
    {
        $users = $this->userColumns();
        $events = $this->eventColumns();
        $eventId = isset($events['event_id']) ? 'event_id' : 'id';
        $userName = isset($users['first_name'])
            ? "CONCAT(u.first_name, ' ', u.last_name)"
            : 'u.name';
        $eventTime = isset($events['event_time']) ? 'event_time' : 'start_time';
        $statement = $this->pdo->prepare(
            'SELECT er.status, er.registered_at, e.' . $eventId . ' AS event_id, e.title, e.event_date, '
            . 'e.' . $eventTime . ' AS event_time, ' . $userName . ' AS user_name '
            . 'FROM event_registrations AS er JOIN users AS u ON u.' . $this->userIdColumn() . ' = er.user_id '
            . 'JOIN events AS e ON e.' . $eventId . ' = er.event_id '
            . 'ORDER BY er.registered_at DESC LIMIT 5'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    private function adminUpcomingEvents(): array
    {
        $events = $this->eventColumns();
        $eventId = isset($events['event_id']) ? 'event_id' : 'id';
        $eventTime = isset($events['event_time']) ? 'event_time' : 'start_time';
        $statusClause = isset($events['status']) ? " AND e.status = 'upcoming'" : '';
        $clubId = $this->clubIdColumn();
        $statement = $this->pdo->prepare(
            'SELECT e.' . $eventId . ' AS event_id, e.title, e.event_date, e.' . $eventTime . ' AS event_time, '
            . 'e.venue, c.name AS club_name FROM events AS e JOIN clubs AS c ON c.' . $clubId . ' = e.club_id '
            . 'WHERE e.event_date >= CURRENT_DATE()' . $statusClause
            . ' ORDER BY e.event_date ASC, e.' . $eventTime . ' ASC LIMIT 5'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    private function adminClubActivity(): array
    {
        $users = $this->userColumns();
        $clubId = $this->clubIdColumn();
        $userName = isset($users['first_name']) ? "CONCAT(u.first_name, ' ', u.last_name)" : 'u.name';
        $statement = $this->pdo->prepare(
            'SELECT cm.joined_at, c.' . $clubId . ' AS club_id, c.name AS club_name, '
            . $userName . ' AS user_name FROM club_members AS cm '
            . 'JOIN clubs AS c ON c.' . $clubId . ' = cm.club_id '
            . 'JOIN users AS u ON u.' . $this->userIdColumn() . ' = cm.user_id '
            . 'ORDER BY cm.joined_at DESC LIMIT 5'
        );
        $statement->execute();
        return $statement->fetchAll();
    }

    private function scalar(string $query, array $parameters = []): int
    {
        $statement = $this->pdo->prepare($query);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn();
    }

    private function userIdColumn(): string
    {
        return isset($this->userColumns()['user_id']) ? 'user_id' : 'id';
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

    private function userColumns(): array
    {
        if ($this->users !== null) {
            return $this->users;
        }
        $this->users = $this->tableColumns('users');
        return $this->users;
    }

    private function eventColumns(): array
    {
        if ($this->events !== null) {
            return $this->events;
        }
        $this->events = $this->tableColumns('events');
        return $this->events;
    }

    private function tableColumns(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => $table]);
        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $columns[(string) $column] = true;
        }
        return $columns;
    }
}
