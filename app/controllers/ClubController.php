<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\Club;
use RuntimeException;
use Throwable;

final class ClubController
{
    public function __construct(private Club $clubs)
    {
    }

    public function index(?string $search, ?string $category): array
    {
        try {
            return $this->clubs->all($this->cleanSearch($search), $this->cleanCategory($category));
        } catch (Throwable $exception) {
            throw new RuntimeException('Club data could not be loaded.', 0, $exception);
        }
    }

    public function show(int $clubId): ?array
    {
        try {
            return $this->clubs->find($clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('Club details could not be loaded.', 0, $exception);
        }
    }

    private function cleanSearch(?string $search): ?string
    {
        $search = trim((string) $search);
        return $search === '' ? null : substr($search, 0, 100);
    }

    private function cleanCategory(?string $category): ?string
    {
        $category = trim((string) $category);
        return $category === '' ? null : substr($category, 0, 80);
    }
}
