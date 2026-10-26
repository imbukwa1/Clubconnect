<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Models\Club;
use PDOException;
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

    public function create(array $input): int
    {
        $data = $this->validatedData($input);
        try {
            return $this->clubs->create($data);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('A club with that name already exists.', 0, $exception);
            }
            throw new RuntimeException('The club could not be created.', 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('The club could not be created.', 0, $exception);
        }
    }

    public function update(int $clubId, array $input): bool
    {
        $data = $this->validatedData($input);
        try {
            if (!$this->clubs->update($clubId, $data)) {
                throw new RuntimeException('The requested club was not found.');
            }
            return true;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('A club with that name already exists.', 0, $exception);
            }
            throw new RuntimeException('The club could not be updated.', 0, $exception);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('The club could not be updated.', 0, $exception);
        }
    }

    public function delete(int $clubId): string
    {
        try {
            return $this->clubs->delete($clubId);
        } catch (Throwable $exception) {
            throw new RuntimeException('The club could not be deleted.', 0, $exception);
        }
    }

    private function validatedData(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $email = trim((string) ($input['contact_email'] ?? ''));
        if ($name === '' || strlen($name) > 150 || $category === '' || strlen($category) > 80 || $description === '') {
            throw new RuntimeException('Club name, category, and description are required.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please provide a valid club contact email.');
        }
        return [
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'contact_email' => $email === '' ? null : $email,
        ];
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
