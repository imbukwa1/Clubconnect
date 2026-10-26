<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\EventRegistration;
use RuntimeException;
use Throwable;

final class EventRegistrationController
{
    public function __construct(private EventRegistration $registrations)
    {
    }

    public function status(int $userId, int $eventId): ?string
    {
        try {
            return $this->registrations->status($userId, $eventId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Registration status could not be loaded.', 0, $exception);
        }
    }

    public function forUser(int $userId): array
    {
        try {
            return $this->registrations->forUser($userId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Your registrations could not be loaded.', 0, $exception);
        }
    }

    public function register(int $userId, int $eventId): bool
    {
        try {
            return $this->registrations->register($userId, $eventId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The event registration could not be completed.', 0, $exception);
        }
    }

    public function cancel(int $userId, int $eventId): bool
    {
        try {
            return $this->registrations->cancel($userId, $eventId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The event registration could not be cancelled.', 0, $exception);
        }
    }
}
