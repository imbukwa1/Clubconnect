<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\Event;
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

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 100);
    }
}
