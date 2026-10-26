<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ob_start();

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Session.php';
require dirname(__DIR__) . '/config/Csrf.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/controllers/AuthController.php';
require dirname(__DIR__) . '/app/controllers/AuthGuard.php';

use ClubConnect\Config\Csrf;
use ClubConnect\Config\Database;
use ClubConnect\Config\Session;
use ClubConnect\Controllers\AuthController;
use ClubConnect\Controllers\AuthGuard;
use ClubConnect\Models\User;

try {
    Session::start();
    $pdo = Database::connection();
    $users = new User($pdo);
    $email = 'stage2-test-' . bin2hex(random_bytes(6)) . '@example.test';
    $password = 'Stage2-Secure-Password!';

    $pdo->beginTransaction();
    $userId = $users->create('Stage', 'Tester', $email, password_hash($password, PASSWORD_DEFAULT));
    $created = $users->findByEmail($email);
    if ($created === null || !password_verify($password, (string) $created['password_hash'])) {
        throw new RuntimeException('Registration or password verification failed.');
    }
    if ($users->findByEmail($email) === null) {
        throw new RuntimeException('Duplicate email lookup failed.');
    }
    $controller = new AuthController($users);
    try {
        $controller->register([
            'first_name' => 'Duplicate',
            'last_name' => 'Tester',
            'email' => $email,
            'password' => $password,
        ]);
        throw new RuntimeException('Duplicate email registration was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'An account with that email already exists.') {
            throw $exception;
        }
    }
    try {
        $controller->login(['email' => $email, 'password' => 'wrong-password']);
        throw new RuntimeException('Invalid credentials were accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'The email or password is incorrect.') {
            throw $exception;
        }
    }
    echo "PASS: User creation, duplicate-email lookup, hashing, and password verification.\n";

    $csrfToken = Csrf::token();
    Csrf::validate($csrfToken);
    try {
        Csrf::validate('invalid-token');
        throw new RuntimeException('Invalid CSRF token was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'The security token is invalid or has expired.') {
            throw $exception;
        }
    }
    echo "PASS: CSRF valid-token and invalid-token checks.\n";

    AuthGuard::storeUser([
        'user_id' => $userId,
        'first_name' => 'Stage',
        'last_name' => 'Tester',
        'email' => $email,
        'role' => 'student',
    ]);
    if (AuthGuard::user()['email'] !== $email || !AuthGuard::canAccessView('student-dashboard') || AuthGuard::canAccessView('admin-dashboard')) {
        throw new RuntimeException('Student session or access rules failed.');
    }
    $_SESSION['user']['role'] = 'admin';
    if (!AuthGuard::canAccessView('admin-dashboard') || AuthGuard::canAccessView('student-dashboard')) {
        throw new RuntimeException('Admin session or access rules failed.');
    }
    echo "PASS: Session persistence and student/admin access restrictions.\n";

    $pdo->rollBack();
    Session::destroy();
    echo "PASS: Test transaction rolled back; no test record was retained.\n";
    ob_end_flush();
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Session::destroy();
    echo 'FAIL: ' . $exception->getMessage() . "\n";
    ob_end_flush();
    exit(1);
}
