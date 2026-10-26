<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';

use ClubConnect\Config\Database;
$requiredTables = [
    'users',
    'clubs',
    'events',
    'club_members',
    'event_registrations',
];

try {
    $pdo = Database::connection();
    echo "PASS: MySQL connection established.\n";

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $configuredDatabase = getenv('DB_NAME');
    if ($database !== $configuredDatabase) {
        echo "FAIL: Selected database does not match DB_NAME.\n";
        exit(1);
    }
    echo "PASS: Selected database is {$database}.\n";

    $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
    $statement = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (' . $placeholders . ')'
    );
    $statement->execute(array_merge([$database], $requiredTables));
    $existingTables = $statement->fetchAll(\PDO::FETCH_COLUMN);
    $missingTables = array_values(array_diff($requiredTables, $existingTables));

    if ($missingTables !== []) {
        echo 'FAIL: Missing required tables: ' . implode(', ', $missingTables) . ".\n";
        exit(1);
    }

    echo 'PASS: All five required tables exist.' . "\n";
    exit(0);
} catch (\Throwable $exception) {
    echo 'FAIL: Database connection check could not complete.' . "\n";
    echo $exception->getMessage() . "\n";
    exit(1);
}
