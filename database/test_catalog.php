<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/Club.php';
require dirname(__DIR__) . '/app/models/Event.php';
require dirname(__DIR__) . '/app/controllers/ClubController.php';
require dirname(__DIR__) . '/app/controllers/EventController.php';

use ClubConnect\Config\Database;
use ClubConnect\Controllers\ClubController;
use ClubConnect\Controllers\EventController;
use ClubConnect\Models\Club;
use ClubConnect\Models\Event;

try {
    $pdo = Database::connection();
    $clubs = new ClubController(new Club($pdo));
    $events = new EventController(new Event($pdo));
    $clubRows = $clubs->index(null, null);
    $eventRows = $events->index(null, null);

    $temporaryTransaction = false;
    if ($clubRows === [] || $eventRows === []) {
        $temporaryTransaction = true;
        $pdo->beginTransaction();
        $token = bin2hex(random_bytes(6));
        $clubColumns = tableColumns($pdo, 'clubs');
        if (isset($clubColumns['club_id'])) {
            $clubStatement = $pdo->prepare(
                'INSERT INTO clubs (name, category, description, contact_email, contact_phone) '
                . 'VALUES (:name, :category, :description, :email, :phone)'
            );
            $clubStatement->execute(['name' => 'Stage 3 Test ' . $token, 'category' => 'Academic', 'description' => 'Temporary catalog test club.', 'email' => 'stage3-' . $token . '@example.test', 'phone' => null]);
        } else {
            $clubStatement = $pdo->prepare(
                'INSERT INTO clubs (name, description, category, contact_person, contact_email) '
                . 'VALUES (:name, :description, :category, :contact_person, :email)'
            );
            $clubStatement->execute(['name' => 'Stage 3 Test ' . $token, 'description' => 'Temporary catalog test club.', 'category' => 'Academic', 'contact_person' => 'Stage 3', 'email' => 'stage3-' . $token . '@example.test']);
        }
        $clubId = (int) $pdo->lastInsertId();
        $eventColumns = tableColumns($pdo, 'events');
        if (isset($eventColumns['event_id'])) {
            $eventStatement = $pdo->prepare(
                'INSERT INTO events (club_id, title, description, event_date, event_time, venue, capacity) '
                . 'VALUES (:club_id, :title, :description, :event_date, :event_time, :venue, :capacity)'
            );
        } else {
            $eventStatement = $pdo->prepare(
                'INSERT INTO events (club_id, title, description, event_date, start_time, end_time, venue, capacity, status) '
                . 'VALUES (:club_id, :title, :description, :event_date, :event_time, :end_time, :venue, :capacity, :status)'
            );
        }
        $eventParameters = ['club_id' => $clubId, 'title' => 'Stage 3 Test Event ' . $token, 'description' => 'Temporary catalog test event.', 'event_date' => '2030-01-01', 'event_time' => '10:00:00', 'venue' => 'Test Venue', 'capacity' => 10];
        if (!isset($eventColumns['event_id'])) {
            $eventParameters['end_time'] = '11:00:00';
            $eventParameters['status'] = 'upcoming';
        }
        $eventStatement->execute($eventParameters);
        $clubRows = $clubs->index('Stage 3 Test ' . $token, null);
        $eventRows = $events->index('Stage 3 Test Event ' . $token, null);
    }

    echo 'PASS: Catalog queries executed; retrieved ' . count($clubRows) . ' clubs and ' . count($eventRows) . " events.\n";

    if ($clubRows !== []) {
        $searchTerm = substr((string) $clubRows[0]['name'], 0, 5);
        if ($clubs->index($searchTerm, null) === []) {
            throw new RuntimeException('Club search returned no matching record.');
        }
    }
    if ($eventRows !== []) {
        $eventSearchTerm = substr((string) $eventRows[0]['title'], 0, 5);
        if ($events->index($eventSearchTerm, null) === []) {
            throw new RuntimeException('Event search returned no matching record.');
        }
    }
    echo "PASS: Club and event search queries handled available/empty datasets.\n";

    if ($clubs->show(0) !== null || $events->show(0) !== null || $events->forClub(0) !== []) {
        throw new RuntimeException('Invalid IDs did not return empty results.');
    }
    if ($clubs->index('__no_such_club__', null) !== [] || $events->index('__no_such_event__', null) !== []) {
        throw new RuntimeException('Empty result handling failed.');
    }
    echo "PASS: Invalid IDs and empty result handling.\n";
    if ($temporaryTransaction) {
        $pdo->rollBack();
        echo "PASS: Temporary retrieval records rolled back; no catalog data was retained.\n";
    }
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'FAIL: ' . $exception->getMessage() . "\n";
    exit(1);
}

function tableColumns(PDO $pdo, string $table): array
{
    $statement = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $table]);
    $columns = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $column) {
        $columns[(string) $column] = true;
    }
    return $columns;
}
