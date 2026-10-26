<?php
declare(strict_types=1);

namespace ClubConnect\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        Env::load(dirname(__DIR__) . '/.env');

        $host = self::requiredEnvironment('DB_HOST');
        $port = self::requiredEnvironment('DB_PORT');
        $name = self::requiredEnvironment('DB_NAME');
        $user = self::requiredEnvironment('DB_USER');
        $password = getenv('DB_PASSWORD');
        if ($password === false) {
            $password = '';
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        try {
            self::$connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the ClubConnect database.', 0, $exception);
        }

        return self::$connection;
    }

    private static function requiredEnvironment(string $key): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Missing required database configuration: ' . $key . '.');
        }

        return $value;
    }
}
