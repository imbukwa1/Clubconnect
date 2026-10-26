<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/models/Club.php';
require dirname(__DIR__) . '/app/models/Event.php';
require dirname(__DIR__) . '/app/controllers/ClubController.php';
require dirname(__DIR__) . '/app/controllers/EventController.php';

use ClubConnect\Config\Database;
use ClubConnect\Controllers\ClubController;
use ClubConnect\Controllers\EventController;
use ClubConnect\Models\Club;
use ClubConnect\Models\Event;
use ClubConnect\Models\User;

const STAGE7_BASE = 'http://localhost/clubconnect/public/index.php';

function stage7Assert(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: {$message}\n";
}

function stage7Request($handle, string $url, ?array $post = null): array
{
    static $cookieInitialized = [];
    $handleId = spl_object_id($handle);
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPGET => $post === null,
        CURLOPT_POST => $post !== null,
        CURLOPT_TIMEOUT => 15,
    ];
    if (!isset($cookieInitialized[$handleId])) {
        $cookiePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'clubconnect-stage7-' . $handleId . '.cookie';
        $options[CURLOPT_COOKIEFILE] = $cookiePath;
        $options[CURLOPT_COOKIEJAR] = $cookiePath;
        $cookieInitialized[$handleId] = $cookiePath;
    }
    curl_setopt_array($handle, $options);
    if ($post !== null) {
        curl_setopt($handle, CURLOPT_HTTPGET, false);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($handle);
    if ($raw === false) { throw new RuntimeException('HTTP request failed.'); }
    $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    return ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => substr($raw, 0, $headerSize), 'body' => substr($raw, $headerSize)];
}

function stage7Csrf(array $response): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $matches) !== 1) { throw new RuntimeException('CSRF token was not rendered.'); }
    return $matches[1];
}

function stage7Location(array $response): string
{
    if (preg_match('/^Location:\s*(.+)$/mi', $response['headers'], $matches) !== 1) { return ''; }
    return trim($matches[1]);
}

try {
    $pdo = Database::connection();
    $suffix = bin2hex(random_bytes(5));
    $studentEmail = 'stage7-student-' . $suffix . '@example.test';
    $adminEmail = 'stage7-admin-' . $suffix . '@example.test';
    $userModel = new User($pdo);
    $clubController = new ClubController(new Club($pdo));
    $eventController = new EventController(new Event($pdo));
    $studentId = 0; $adminId = 0; $clubId = 0; $eventId = 0; $adminClubId = 0; $adminEventId = 0;

    $clubId = $clubController->create(['name' => 'Stage 7 Student Club ' . $suffix, 'category' => 'Academic', 'description' => 'Temporary HTTP test club', 'contact_email' => 'student-' . $suffix . '@example.test']);
    $eventId = $eventController->create(['club_id' => $clubId, 'title' => 'Stage 7 Student Event ' . $suffix, 'description' => 'Temporary HTTP test event', 'event_date' => '2099-08-20', 'event_time' => '11:00', 'venue' => 'HTTP Test Hall', 'capacity' => 10]);
    $adminId = $userModel->create('Stage', 'Seven Admin', $adminEmail, password_hash('Stage7-Admin-Password!', PASSWORD_DEFAULT));
    $pdo->prepare('UPDATE users SET role = :role WHERE id = :id')->execute(['role' => 'admin', 'id' => $adminId]);

    $student = curl_init();
    $response = stage7Request($student, STAGE7_BASE . '?view=register');
    stage7Assert($response['status'] === 200, 'registration page loads over HTTP');
    $response = stage7Request($student, STAGE7_BASE . '?action=register', ['_csrf' => stage7Csrf($response), 'first_name' => 'Stage', 'last_name' => 'Seven Student', 'email' => $studentEmail, 'password' => 'Stage7-Student-Password!', 'terms' => '1']);
    if ($response['status'] !== 302 || !str_contains(stage7Location($response), 'student-dashboard')) {
        $debug = stage7Request($student, STAGE7_BASE . '?view=register');
        $message = preg_match('/data-message="([^"]*)"/', $debug['body'], $flash) === 1 ? html_entity_decode($flash[1]) : 'none';
        throw new RuntimeException('student registration redirects incorrectly (status=' . $response['status'] . ', location=' . stage7Location($response) . ', flash=' . $message . ')');
    }
    echo "PASS: student registration redirects to dashboard\n";
    $studentId = (int) $userModel->findByEmail($studentEmail)['user_id'];
    $response = stage7Request($student, STAGE7_BASE . '?view=clubs&q=' . rawurlencode('Stage 7 Student Club ' . $suffix));
    stage7Assert($response['status'] === 200 && str_contains($response['body'], 'Stage 7 Student Club'), 'student can browse and search clubs');
    $response = stage7Request($student, STAGE7_BASE . '?view=club-details&id=' . $clubId);
    $response = stage7Request($student, STAGE7_BASE . '?action=join-club', ['_csrf' => stage7Csrf($response), 'id' => $clubId]);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'club-details&id=' . $clubId), 'student can join a club and retain detail redirect');
    $response = stage7Request($student, STAGE7_BASE . '?view=event-details&id=' . $eventId);
    $response = stage7Request($student, STAGE7_BASE . '?action=register-event', ['_csrf' => stage7Csrf($response), 'id' => $eventId]);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'event-details&id=' . $eventId), 'student can register for an event');
    $response = stage7Request($student, STAGE7_BASE . '?view=student-dashboard');
    stage7Assert($response['status'] === 200 && str_contains($response['body'], 'Stage 7 Student Event'), 'student dashboard shows registered event');
    $response = stage7Request($student, STAGE7_BASE . '?view=event-details&id=' . $eventId);
    $response = stage7Request($student, STAGE7_BASE . '?action=cancel-registration', ['_csrf' => stage7Csrf($response), 'id' => $eventId]);
    stage7Assert($response['status'] === 302, 'student can cancel registration');
    $response = stage7Request($student, STAGE7_BASE . '?view=club-details&id=' . $clubId);
    $response = stage7Request($student, STAGE7_BASE . '?action=leave-club', ['_csrf' => stage7Csrf($response), 'id' => $clubId]);
    stage7Assert($response['status'] === 302, 'student can leave club');
    $response = stage7Request($student, STAGE7_BASE . '?action=logout', ['_csrf' => stage7Csrf(stage7Request($student, STAGE7_BASE . '?view=student-dashboard'))]);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'view=login'), 'student logout ends session');

    $admin = curl_init();
    $response = stage7Request($admin, STAGE7_BASE . '?view=login');
    $response = stage7Request($admin, STAGE7_BASE . '?action=login', ['_csrf' => stage7Csrf($response), 'email' => $adminEmail, 'password' => 'Stage7-Admin-Password!']);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'admin-dashboard'), 'admin login redirects to admin dashboard');
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-club-form');
    $response = stage7Request($admin, STAGE7_BASE . '?action=create-club', ['_csrf' => stage7Csrf($response), 'name' => 'Stage 7 Admin Club ' . $suffix, 'category' => 'Social', 'description' => 'Temporary admin CRUD club', 'contact_email' => 'admin-' . $suffix . '@example.test']);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'admin-clubs'), 'admin can create a club');
    $adminClubId = (int) $pdo->query("SELECT id FROM clubs WHERE name = " . $pdo->quote('Stage 7 Admin Club ' . $suffix))->fetchColumn();
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-club-form&id=' . $adminClubId);
    $response = stage7Request($admin, STAGE7_BASE . '?action=update-club', ['_csrf' => stage7Csrf($response), 'id' => $adminClubId, 'name' => 'Stage 7 Updated Club ' . $suffix, 'category' => 'Social', 'description' => 'Updated temporary club', 'contact_email' => 'admin-' . $suffix . '@example.test']);
    stage7Assert($response['status'] === 302, 'admin can edit a club');
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-event-form');
    $response = stage7Request($admin, STAGE7_BASE . '?action=create-event', ['_csrf' => stage7Csrf($response), 'club_id' => $adminClubId, 'title' => 'Stage 7 Admin Event ' . $suffix, 'description' => 'Temporary admin CRUD event', 'event_date' => '2099-09-20', 'event_time' => '12:00', 'venue' => 'Admin Test Hall', 'capacity' => 5]);
    stage7Assert($response['status'] === 302 && str_contains(stage7Location($response), 'admin-events'), 'admin can create an event');
    $adminEventId = (int) $pdo->query("SELECT id FROM events WHERE title = " . $pdo->quote('Stage 7 Admin Event ' . $suffix))->fetchColumn();
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-registrations');
    stage7Assert($response['status'] === 200 && str_contains($response['body'], 'No registrations yet'), 'admin registrations page uses live empty state');
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-event-form&id=' . $adminEventId);
    $response = stage7Request($admin, STAGE7_BASE . '?action=delete-event', ['_csrf' => stage7Csrf($response), 'id' => $adminEventId]);
    stage7Assert($response['status'] === 302, 'admin can delete eligible event');
    $response = stage7Request($admin, STAGE7_BASE . '?view=admin-club-form&id=' . $adminClubId);
    $response = stage7Request($admin, STAGE7_BASE . '?action=delete-club', ['_csrf' => stage7Csrf($response), 'id' => $adminClubId]);
    stage7Assert($response['status'] === 302, 'admin can delete eligible club');
    $response = stage7Request($admin, STAGE7_BASE . '?action=logout', ['_csrf' => stage7Csrf(stage7Request($admin, STAGE7_BASE . '?view=admin-dashboard'))]);
    stage7Assert($response['status'] === 302, 'admin logout ends session');
    curl_close($student); curl_close($admin);
    echo "PASS: HTTP Stage 7 workflow completed\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        if ($studentId > 0) { $pdo->prepare('DELETE FROM club_members WHERE user_id = :id')->execute(['id' => $studentId]); $pdo->prepare('DELETE FROM event_registrations WHERE user_id = :id')->execute(['id' => $studentId]); }
        foreach ([$eventId, $adminEventId] as $id) { if ($id > 0) { $pdo->prepare('DELETE FROM event_registrations WHERE event_id = :id')->execute(['id' => $id]); $pdo->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $id]); } }
        foreach ([$clubId, $adminClubId] as $id) { if ($id > 0) { $pdo->prepare('DELETE FROM club_members WHERE club_id = :id')->execute(['id' => $id]); $pdo->prepare('DELETE FROM clubs WHERE id = :id')->execute(['id' => $id]); } }
        foreach ([$studentId, $adminId] as $id) { if ($id > 0) { $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]); } }
    }
}
if (isset($exitCode)) { exit($exitCode); }
