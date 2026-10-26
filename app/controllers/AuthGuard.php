<?php
declare(strict_types=1);

namespace ClubConnect\Controllers;

use ClubConnect\Config\Session;

final class AuthGuard
{
    private const STUDENT_VIEWS = [
        'student-dashboard', 'clubs', 'club-details', 'events', 'event-details', 'registrations',
    ];

    private const ADMIN_VIEWS = [
        'admin-dashboard', 'admin-clubs', 'admin-club-form', 'admin-events',
        'admin-event-form', 'admin-registrations',
    ];

    public static function user(): ?array
    {
        Session::start();
        $user = $_SESSION['user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    public static function canAccessView(string $view): bool
    {
        if (in_array($view, ['login', 'register'], true)) {
            return true;
        }

        $user = self::user();
        if ($user === null) {
            return false;
        }

        if (in_array($view, self::ADMIN_VIEWS, true)) {
            return ($user['role'] ?? null) === 'admin';
        }

        return in_array($view, self::STUDENT_VIEWS, true) && ($user['role'] ?? null) === 'student';
    }

    public static function defaultView(): string
    {
        $user = self::user();

        if ($user === null) {
            return 'login';
        }

        return ($user['role'] ?? null) === 'admin' ? 'admin-dashboard' : 'student-dashboard';
    }

    public static function storeUser(array $user): void
    {
        Session::regenerate();
        $_SESSION['user'] = [
            'user_id' => (int) $user['user_id'],
            'first_name' => (string) $user['first_name'],
            'last_name' => (string) $user['last_name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
    }
}
