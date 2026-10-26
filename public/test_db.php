
<?php

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->connect();

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo '<h2>ClubConnect Database Connection Successful!</h2>';
    echo '<p>Connected to database: clubconnect</p>';
    echo '<h3>Available Tables:</h3>';

    foreach ($tables as $table) {
        echo '<p>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</p>';
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Database connection test failed.</h2>';
}
