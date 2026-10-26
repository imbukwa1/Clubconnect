<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\Dashboard;
use RuntimeException;
use Throwable;

final class DashboardController
{
    public function __construct(private Dashboard $dashboard)
    {
    }

    public function student(int $userId): array
    {
        try {
            return $this->dashboard->student($userId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Student dashboard data could not be loaded.', 0, $exception);
        }
    }

    public function admin(): array
    {
        try {
            return $this->dashboard->admin();
        } catch (Throwable $exception) {
            throw new RuntimeException('Admin dashboard data could not be loaded.', 0, $exception);
        }
    }
}
