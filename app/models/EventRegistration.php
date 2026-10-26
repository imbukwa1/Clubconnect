<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;
use PDOException;

final class EventRegistration
{
    private ?array $eventColumns = null;
    private ?string $eventIdColumn = null;
    private ?string $clubIdColumn = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function status(int $userId, int $eventId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT status FROM event_registrations '
            . 'WHERE user_id = :user_id AND event_id = :event_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'event_id' => $eventId]);
        $status = $statement->fetchColumn();
        return $status === false ? null : (string) $status;
    }

    public function forUser(int $userId): array
    {
        $eventId = $this->eventIdColumn();
        $eventColumns = $this->eventColumns();
        $eventTime = isset($eventColumns['event_time']) ? 'event_time' : 'start_time';
        $endTime = isset($eventColumns['end_time']) ? 'end_time' : "''";
        $clubId = $this->clubIdColumn();
        $statement = $this->pdo->prepare(
            'SELECT e.' . $eventId . ' AS event_id, e.title, e.description, e.event_date, '
            . 'e.' . $eventTime . ' AS event_time, ' . $endTime . ' AS event_end_time, '
            . 'e.venue, e.capacity, er.status, er.registered_at, c.name AS club_name '
            . 'FROM event_registrations AS er JOIN events AS e ON e.' . $eventId . ' = er.event_id '
            . 'JOIN clubs AS c ON c.' . $clubId . ' = e.club_id '
            . 'WHERE er.user_id = :user_id AND er.status <> :cancelled '
            . 'ORDER BY e.event_date ASC, event_time ASC'
        );
        $statement->execute(['user_id' => $userId, 'cancelled' => 'cancelled']);
        return $statement->fetchAll();
    }

    public function register(int $userId, int $eventId): bool
    {
        if ($userId < 1 || $eventId < 1) {
            return false;
        }
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $event = $this->lockedEvent($eventId);
            if ($event === null || !$this->eventAcceptsRegistration($event)) {
                $this->rollbackIfOwned($startedTransaction);
                return false;
            }

            $existing = $this->existingRegistration($userId, $eventId);
            if ($existing !== null && $existing !== 'cancelled') {
                $this->rollbackIfOwned($startedTransaction);
                return false;
            }

            $registeredCount = $this->registeredCount($eventId);
            if ($registeredCount >= (int) $event['capacity']) {
                $this->rollbackIfOwned($startedTransaction);
                return false;
            }

            if ($existing === 'cancelled') {
                $statement = $this->pdo->prepare(
                    'UPDATE event_registrations SET status = :status, registered_at = CURRENT_TIMESTAMP '
                    . 'WHERE user_id = :user_id AND event_id = :event_id'
                );
                $statement->execute(['status' => 'registered', 'user_id' => $userId, 'event_id' => $eventId]);
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO event_registrations (user_id, event_id, status) '
                    . 'VALUES (:user_id, :event_id, :status)'
                );
                $statement->execute(['user_id' => $userId, 'event_id' => $eventId, 'status' => 'registered']);
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (PDOException $exception) {
            $this->rollbackIfOwned($startedTransaction);
            if ((string) $exception->getCode() === '23000') {
                return false;
            }
            throw $exception;
        }
    }

    public function cancel(int $userId, int $eventId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE event_registrations SET status = :cancelled '
            . 'WHERE user_id = :user_id AND event_id = :event_id AND status = :registered'
        );
        $statement->execute([
            'cancelled' => 'cancelled',
            'user_id' => $userId,
            'event_id' => $eventId,
            'registered' => 'registered',
        ]);
        return $statement->rowCount() > 0;
    }

    private function lockedEvent(int $eventId): ?array
    {
        $columns = $this->eventColumns();
        $eventIdColumn = $this->eventIdColumn();
        $eventTime = isset($columns['event_time']) ? 'event_time' : 'start_time';
        $status = isset($columns['status']) ? 'status' : "'upcoming'";
        $statement = $this->pdo->prepare(
            'SELECT event_date, e.' . $eventTime . ' AS event_time, capacity, ' . $status . ' AS status '
            . 'FROM events AS e WHERE e.' . $eventIdColumn . ' = :event_id FOR UPDATE'
        );
        $statement->execute(['event_id' => $eventId]);
        $event = $statement->fetch();
        return $event === false ? null : $event;
    }

    private function eventAcceptsRegistration(array $event): bool
    {
        if ((string) $event['status'] !== 'upcoming') {
            return false;
        }
        return (string) $event['event_date'] >= date('Y-m-d');
    }

    private function existingRegistration(int $userId, int $eventId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT status FROM event_registrations '
            . 'WHERE user_id = :user_id AND event_id = :event_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'event_id' => $eventId]);
        $status = $statement->fetchColumn();
        return $status === false ? null : (string) $status;
    }

    private function registeredCount(int $eventId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM event_registrations '
            . 'WHERE event_id = :event_id AND status IN (:registered, :attended)'
        );
        $statement->execute(['event_id' => $eventId, 'registered' => 'registered', 'attended' => 'attended']);
        return (int) $statement->fetchColumn();
    }

    private function rollbackIfOwned(bool $startedTransaction): void
    {
        if ($startedTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function eventIdColumn(): string
    {
        if ($this->eventIdColumn !== null) {
            return $this->eventIdColumn;
        }
        $columns = $this->eventColumns();
        $this->eventIdColumn = isset($columns['event_id']) ? 'event_id' : 'id';
        return $this->eventIdColumn;
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

    private function eventColumns(): array
    {
        if ($this->eventColumns !== null) {
            return $this->eventColumns;
        }
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => 'events']);
        $this->eventColumns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $this->eventColumns[(string) $column] = true;
        }
        return $this->eventColumns;
    }
}
