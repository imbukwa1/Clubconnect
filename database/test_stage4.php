<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/models/ClubMembership.php';
require dirname(__DIR__) . '/app/models/EventRegistration.php';

use ClubConnect\Config\Database;
use ClubConnect\Models\ClubMembership;
use ClubConnect\Models\EventRegistration;
use ClubConnect\Models\User;

function columns(PDO $pdo, string $table): array
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

try {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    $token = bin2hex(random_bytes(6));
    $users = new User($pdo);
    $userOne = $users->create('Stage4', 'One', 'stage4-one-' . $token . '@example.test', password_hash('Stage4-password!', PASSWORD_DEFAULT));
    $userTwo = $users->create('Stage4', 'Two', 'stage4-two-' . $token . '@example.test', password_hash('Stage4-password!', PASSWORD_DEFAULT));

    $clubColumns = columns($pdo, 'clubs');
    if (isset($clubColumns['club_id'])) {
        $statement = $pdo->prepare('INSERT INTO clubs (name, category, description, contact_email, contact_phone) VALUES (:name, :category, :description, :email, :phone)');
        $statement->execute(['name' => 'Stage4 Club ' . $token, 'category' => 'Academic', 'description' => 'Temporary stage 4 club.', 'email' => 'stage4-' . $token . '@example.test', 'phone' => null]);
    } else {
        $statement = $pdo->prepare('INSERT INTO clubs (name, description, category, contact_person, contact_email) VALUES (:name, :description, :category, :contact_person, :email)');
        $statement->execute(['name' => 'Stage4 Club ' . $token, 'description' => 'Temporary stage 4 club.', 'category' => 'Academic', 'contact_person' => 'Stage 4', 'email' => 'stage4-' . $token . '@example.test']);
    }
    $clubId = (int) $pdo->lastInsertId();

    $eventColumns = columns($pdo, 'events');
    if (isset($eventColumns['event_id'])) {
        $statement = $pdo->prepare('INSERT INTO events (club_id, title, description, event_date, event_time, venue, capacity) VALUES (:club_id, :title, :description, :event_date, :event_time, :venue, :capacity)');
        $statement->execute(['club_id' => $clubId, 'title' => 'Stage4 Event ' . $token, 'description' => 'Temporary stage 4 event.', 'event_date' => '2030-01-01', 'event_time' => '10:00:00', 'venue' => 'Stage 4 Venue', 'capacity' => 1]);
    } else {
        $statement = $pdo->prepare('INSERT INTO events (club_id, title, description, event_date, start_time, end_time, venue, capacity, status) VALUES (:club_id, :title, :description, :event_date, :event_time, :end_time, :venue, :capacity, :status)');
        $statement->execute(['club_id' => $clubId, 'title' => 'Stage4 Event ' . $token, 'description' => 'Temporary stage 4 event.', 'event_date' => '2030-01-01', 'event_time' => '10:00:00', 'end_time' => '11:00:00', 'venue' => 'Stage 4 Venue', 'capacity' => 1, 'status' => 'upcoming']);
    }
    $eventId = (int) $pdo->lastInsertId();

    $memberships = new ClubMembership($pdo);
    if (!$memberships->join($userOne, $clubId) || $memberships->join($userOne, $clubId) || !$memberships->isMember($userOne, $clubId)) {
        throw new RuntimeException('Membership join or duplicate prevention failed.');
    }
    if (!$memberships->join($userTwo, $clubId) || !$memberships->leave($userOne, $clubId) || $memberships->isMember($userOne, $clubId) || !$memberships->isMember($userTwo, $clubId)) {
        throw new RuntimeException('Membership leave authorization/scoping failed.');
    }
    if ($memberships->join($userOne, 0) || $memberships->leave($userOne, 0)) {
        throw new RuntimeException('Invalid membership ID was accepted.');
    }
    echo "PASS: Membership join, duplicate prevention, leave, scoping, and invalid IDs.\n";

    $registrations = new EventRegistration($pdo);
    if (!$registrations->register($userOne, $eventId) || $registrations->register($userOne, $eventId) || $registrations->status($userOne, $eventId) !== 'registered') {
        throw new RuntimeException('Event registration or duplicate prevention failed.');
    }
    if ($registrations->register($userTwo, $eventId)) {
        throw new RuntimeException('Event capacity was not enforced.');
    }
    if (!$registrations->cancel($userOne, $eventId) || $registrations->status($userOne, $eventId) !== 'cancelled' || $registrations->cancel($userOne, $eventId)) {
        throw new RuntimeException('Registration cancellation failed.');
    }
    if (!$registrations->register($userOne, $eventId) || $registrations->status($userOne, $eventId) !== 'registered') {
        throw new RuntimeException('Re-registration after cancellation failed.');
    }
    if ($registrations->register($userOne, 0) || $registrations->cancel($userOne, 0)) {
        throw new RuntimeException('Invalid event ID was accepted.');
    }
    echo "PASS: Registration, capacity, cancellation, re-registration, and invalid IDs.\n";

    $pdo->rollBack();
    echo "PASS: Transaction rolled back; no Stage 4 test records were retained.\n";
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'FAIL: ' . $exception->getMessage() . "\n";
    exit(1);
}
