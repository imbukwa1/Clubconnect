<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/models/Dashboard.php';

use ClubConnect\Config\Database;
use ClubConnect\Models\Dashboard;
use ClubConnect\Models\User;

function table_columns(PDO $pdo, string $table): array
{
    $statement = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
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
    $userId = $users->create('Dashboard', 'Student', 'dashboard-' . $token . '@example.test', password_hash('Dashboard-password!', PASSWORD_DEFAULT));
    $adminId = $users->create('Dashboard', 'Admin', 'dashboard-admin-' . $token . '@example.test', password_hash('Dashboard-password!', PASSWORD_DEFAULT));

    $clubs = table_columns($pdo, 'clubs');
    if (isset($clubs['club_id'])) {
        $statement = $pdo->prepare('INSERT INTO clubs (name, category, description, contact_email, contact_phone) VALUES (:name, :category, :description, :email, :phone)');
        $statement->execute(['name' => 'Dashboard Club ' . $token, 'category' => 'Social', 'description' => 'Temporary dashboard club.', 'email' => 'dashboard-' . $token . '@example.test', 'phone' => null]);
    } else {
        $statement = $pdo->prepare('INSERT INTO clubs (name, description, category, contact_person, contact_email) VALUES (:name, :description, :category, :contact_person, :email)');
        $statement->execute(['name' => 'Dashboard Club ' . $token, 'description' => 'Temporary dashboard club.', 'category' => 'Social', 'contact_person' => 'Dashboard', 'email' => 'dashboard-' . $token . '@example.test']);
    }
    $clubId = (int) $pdo->lastInsertId();

    $events = table_columns($pdo, 'events');
    if (isset($events['event_id'])) {
        $statement = $pdo->prepare('INSERT INTO events (club_id, title, description, event_date, event_time, venue, capacity) VALUES (:club_id, :title, :description, :event_date, :event_time, :venue, :capacity)');
        $statement->execute(['club_id' => $clubId, 'title' => 'Dashboard Event ' . $token, 'description' => 'Temporary dashboard event.', 'event_date' => '2030-01-01', 'event_time' => '10:00:00', 'venue' => 'Dashboard Venue', 'capacity' => 20]);
    } else {
        $statement = $pdo->prepare('INSERT INTO events (club_id, title, description, event_date, start_time, end_time, venue, capacity, status) VALUES (:club_id, :title, :description, :event_date, :event_time, :end_time, :venue, :capacity, :status)');
        $statement->execute(['club_id' => $clubId, 'title' => 'Dashboard Event ' . $token, 'description' => 'Temporary dashboard event.', 'event_date' => '2030-01-01', 'event_time' => '10:00:00', 'end_time' => '11:00:00', 'venue' => 'Dashboard Venue', 'capacity' => 20, 'status' => 'upcoming']);
    }
    $eventId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO club_members (user_id, club_id) VALUES (:user_id, :club_id)')->execute(['user_id' => $userId, 'club_id' => $clubId]);
    $pdo->prepare('INSERT INTO event_registrations (user_id, event_id, status) VALUES (:user_id, :event_id, :status)')->execute(['user_id' => $userId, 'event_id' => $eventId, 'status' => 'registered']);

    $dashboard = new Dashboard($pdo);
    $student = $dashboard->student($userId);
    if ($student['joined_clubs'] !== 1 || $student['upcoming_events'] !== 1 || $student['available_clubs'] !== 1 || count($student['recent_activity']) !== 1) {
        throw new RuntimeException('Student dashboard statistics were incorrect.');
    }
    echo "PASS: Student dashboard statistics and activity.\n";

    $admin = $dashboard->admin();
    if ($admin['students'] !== 2 || $admin['clubs'] !== 1 || $admin['events'] !== 1 || $admin['memberships'] !== 1 || $admin['registrations'] !== 1 || count($admin['recent_registrations']) !== 1 || count($admin['upcoming_events']) !== 1 || count($admin['club_activity']) !== 1) {
        throw new RuntimeException('Admin dashboard statistics or activity were incorrect.');
    }
    echo "PASS: Admin totals, upcoming events, registrations, and club activity.\n";

    $pdo->rollBack();
    echo "PASS: Dashboard test transaction rolled back; no records were retained.\n";
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'FAIL: ' . $exception->getMessage() . "\n";
    exit(1);
}
