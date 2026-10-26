<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\Event;
use PDOException;
use RuntimeException;
use Throwable;

final class EventController
{
    public function __construct(private Event $events)
    {
    }

    public function index(?string $search, ?string $category): array
    {
        try {
            return $this->events->all($this->clean($search), $this->clean($category));
        } catch (Throwable $exception) {
            throw new RuntimeException('Event data could not be loaded.', 0, $exception);
        }
    }

    public function show(int $eventId): ?array
    {
        try {
            return $this->events->find($eventId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Event details could not be loaded.', 0, $exception);
        }
    }

    public function forClub(int $clubId): array
    {
        try {
            return $this->events->forClub($clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Club events could not be loaded.', 0, $exception);
        }
    }

    public function create(array $input): int
    {
        $data = $this->validatedData($input);
        try {
            $id = $this->events->create($data);
            if ($id < 1) {
                throw new RuntimeException('The selected club was not found.');
            }
            return $id;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('An event with that title already exists for this club and date.', 0, $exception);
            }
            throw new RuntimeException('The event could not be created.', 0, $exception);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('The event could not be created.', 0, $exception);
        }
    }

    public function update(int $eventId, array $input): bool
    {
        $data = $this->validatedData($input);
        try {
            if (!$this->events->update($eventId, $data)) {
                throw new RuntimeException('The requested event or club was not found.');
            }
            return true;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('An event with that title already exists for this club and date.', 0, $exception);
            }
            throw new RuntimeException('The event could not be updated.', 0, $exception);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('The event could not be updated.', 0, $exception);
        }
    }

    public function delete(int $eventId): string
    {
        try {
            return $this->events->delete($eventId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The event could not be deleted.', 0, $exception);
        }
    }

    private function validatedData(array $input): array
    {
        $clubId = filter_var($input['club_id'] ?? null, FILTER_VALIDATE_INT);
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $date = trim((string) ($input['event_date'] ?? ''));
        $time = trim((string) ($input['event_time'] ?? ''));
        $venue = trim((string) ($input['venue'] ?? ''));
        $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        $dateObject = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateValid = $dateObject !== false && $dateObject->format('Y-m-d') === $date;
        $timeObject = \DateTimeImmutable::createFromFormat('!H:i', $time);
        $timeValid = $timeObject !== false && $timeObject->format('H:i') === $time;
        if ($clubId === false || $clubId < 1 || $title === '' || strlen($title) > 150 || $description === '' || !$dateValid || !$timeValid || $venue === '' || strlen($venue) > 150 || $capacity === false || $capacity < 1) {
            throw new RuntimeException('Please provide valid club, title, description, date, time, venue, and capacity values.');
        }
        return [
            'club_id' => $clubId,
            'title' => $title,
            'description' => $description,
            'event_date' => $date,
            'event_time' => $time . ':00',
            'venue' => $venue,
            'capacity' => $capacity,
        ];
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 100);
    }
}
