<?php
declare(strict_types=1);

namespace ClubConnect\Models;

use PDO;
use RuntimeException;

final class User
{
    private ?array $columns = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $columns = $this->userColumns();
        if (isset($columns['first_name'], $columns['password_hash'])) {
            $query = 'SELECT user_id, first_name, last_name, email, password_hash, role '
                . 'FROM users WHERE email = :email LIMIT 1';
        } else {
            $query = 'SELECT id AS user_id, name AS first_name, \'\' AS last_name, '
                . 'email, password AS password_hash, role FROM users '
                . 'WHERE email = :email LIMIT 1';
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function create(
        string $firstName,
        string $lastName,
        string $email,
        string $passwordHash,
        string $role = 'student'
    ): int {
        if (!in_array($role, ['student', 'admin'], true)) {
            throw new RuntimeException('Invalid account role.');
        }
        if (isset($this->userColumns()['first_name'])) {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, role) '
                . 'VALUES (:first_name, :last_name, :email, :password_hash, :role)'
            );
            $statement->execute([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (name, email, password, role) '
                . 'VALUES (:name, :email, :password, :role)'
            );
            $statement->execute([
                'name' => trim($firstName . ' ' . $lastName),
                'email' => $email,
                'password' => $passwordHash,
                'role' => $role,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function userColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $statement->execute(['table_name' => 'users']);
        $this->columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
            $this->columns[(string) $column] = true;
        }

        return $this->columns;
    }
}
