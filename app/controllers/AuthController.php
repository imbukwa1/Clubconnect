<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Config\Csrf;
use ClubConnect\Config\Session;
use ClubConnect\Models\User;
use PDOException;
use RuntimeException;

final class AuthController
{
    public function __construct(private User $users)
    {
    }

    public function register(array $input): void
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if (!$this->validName($firstName) || !$this->validName($lastName)) {
            throw new RuntimeException('Please provide valid first and last names.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please provide a valid university email address.');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('Your password must be at least 8 characters long.');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('An account with that email already exists.');
        }

        try {
            $userId = $this->users->create(
                $firstName,
                $lastName,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            );
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new RuntimeException('An account with that email already exists.', 0, $exception);
            }
            throw new RuntimeException('Registration could not be completed.', 0, $exception);
        }

        AuthGuard::storeUser([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role' => 'student',
        ]);
        Session::flash('success', 'Account created successfully. Welcome to ClubConnect.');
        self::redirect('student-dashboard');
    }

    public function login(array $input): void
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new RuntimeException('The email or password is incorrect.');
        }

        AuthGuard::storeUser($user);
        Session::flash('success', 'Welcome back, ' . $user['first_name'] . '.');
        self::redirect(($user['role'] ?? 'student') === 'admin' ? 'admin-dashboard' : 'student-dashboard');
    }

    public function logout(): void
    {
        Session::destroy();
        Session::start();
        Session::flash('success', 'You have been signed out.');
        self::redirect('login');
    }

    private function validName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 80 && preg_match("/^[\p{L} .'-]+$/u", $name) === 1;
    }

    public static function verifyCsrf(array $input): void
    {
        Csrf::validate(isset($input['_csrf']) ? (string) $input['_csrf'] : null);
    }

    public static function redirect(string $view): never
    {
        header('Location: index.php?view=' . rawurlencode($view));
        exit;
    }
}
