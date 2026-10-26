<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\ClubMembership;
use RuntimeException;
use Throwable;

final class ClubMembershipController
{
    public function __construct(private ClubMembership $memberships)
    {
    }

    public function isMember(int $userId, int $clubId): bool
    {
        try {
            return $this->memberships->isMember($userId, $clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Membership status could not be loaded.', 0, $exception);
        }
    }

    public function forUser(int $userId): array
    {
        try {
            return $this->memberships->forUser($userId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Your clubs could not be loaded.', 0, $exception);
        }
    }

    public function join(int $userId, int $clubId): bool
    {
        try {
            return $this->memberships->join($userId, $clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The club could not be joined.', 0, $exception);
        }
    }

    public function leave(int $userId, int $clubId): bool
    {
        try {
            return $this->memberships->leave($userId, $clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The club could not be left.', 0, $exception);
        }
    }
}
