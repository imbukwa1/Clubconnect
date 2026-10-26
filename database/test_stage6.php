<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/Club.php';
require dirname(__DIR__) . '/app/models/Event.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/controllers/ClubController.php';
require dirname(__DIR__) . '/app/controllers/EventController.php';

use ClubConnect\Config\Database;
use ClubConnect\Controllers\ClubController;
use ClubConnect\Controllers\EventController;
use ClubConnect\Models\Club;
use ClubConnect\Models\Event;
use ClubConnect\Models\User;

function expectStage6(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: {$message}\n";
}

try {
    $pdo = Database::connection();
    $clubs = new ClubController(new Club($pdo));
    $events = new EventController(new Event($pdo));
    $pdo->beginTransaction();

    $suffix = bin2hex(random_bytes(4));
    $clubData = ['name' => 'Stage 6 Club ' . $suffix, 'category' => 'Academic', 'description' => 'Temporary CRUD test club', 'contact_email' => 'stage6-' . $suffix . '@example.test'];
    $clubId = $clubs->create($clubData);
    expectStage6($clubId > 0 && $clubs->show($clubId)['name'] === $clubData['name'], 'club creation and retrieval');
    try { $clubs->create($clubData); throw new RuntimeException('duplicate club was accepted'); } catch (RuntimeException $exception) { expectStage6(str_contains($exception->getMessage(), 'already exists'), 'duplicate club rejected'); }
    $clubs->update($clubId, array_merge($clubData, ['name' => 'Updated Club ' . $suffix]));
    expectStage6($clubs->show($clubId)['name'] === 'Updated Club ' . $suffix, 'club update');

    $eventData = ['club_id' => $clubId, 'title' => 'Stage 6 Event ' . $suffix, 'description' => 'Temporary CRUD test event', 'event_date' => '2099-06-15', 'event_time' => '14:30', 'venue' => 'Test Hall', 'capacity' => 20];
    $eventId = $events->create($eventData);
    expectStage6($eventId > 0 && $events->show($eventId)['title'] === $eventData['title'], 'event creation and retrieval');
    try { $events->create($eventData); throw new RuntimeException('duplicate event was accepted'); } catch (RuntimeException $exception) { expectStage6(str_contains($exception->getMessage(), 'already exists'), 'duplicate event rejected'); }
    $events->update($eventId, array_merge($eventData, ['title' => 'Updated Event ' . $suffix]));
    expectStage6($events->show($eventId)['title'] === 'Updated Event ' . $suffix, 'event update');
    try { $events->create(array_merge($eventData, ['capacity' => 0])); throw new RuntimeException('invalid event was accepted'); } catch (RuntimeException $exception) { expectStage6(str_contains($exception->getMessage(), 'valid'), 'invalid event validation'); }

    expectStage6($clubs->delete($clubId) === 'has_events', 'club deletion blocked when events exist');
    $userId = (new User($pdo))->create('Stage', 'Six', 'stage6-' . $suffix . '@example.test', password_hash('temporary-password', PASSWORD_DEFAULT));
    $registration = $pdo->prepare('INSERT INTO event_registrations (user_id, event_id, status) VALUES (:user_id, :event_id, :status)');
    $registration->execute(['user_id' => $userId, 'event_id' => $eventId, 'status' => 'registered']);
    expectStage6($events->delete($eventId) === 'has_registrations', 'event deletion blocked when registrations exist');

    $emptyClubId = $clubs->create(['name' => 'Empty Club ' . $suffix, 'category' => 'Social', 'description' => 'Temporary empty club', 'contact_email' => '']);
    expectStage6($clubs->delete($emptyClubId) === 'deleted' && $clubs->show($emptyClubId) === null, 'empty club deletion');
    try {
        $events->create(['club_id' => $emptyClubId, 'title' => 'Temporary Event ' . $suffix, 'description' => 'Temporary event', 'event_date' => '2099-07-15', 'event_time' => '10:00', 'venue' => 'Test Room', 'capacity' => 5]);
        throw new RuntimeException('event creation accepted a deleted club');
    } catch (RuntimeException $exception) {
        expectStage6(str_contains($exception->getMessage(), 'club'), 'event creation rejects deleted club');
    }

    $pdo->rollBack();
    echo "PASS: all Stage 6 checks completed and transaction rolled back\n";
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    exit(1);
}
