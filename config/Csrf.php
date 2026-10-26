<?php
declare(strict_types=1);

namespace ClubConnect\Config;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function validate(?string $token): void
    {
        Session::start();
        $knownToken = $_SESSION['_csrf'] ?? null;
        if (!is_string($token) || !is_string($knownToken) || !hash_equals($knownToken, $token)) {
            throw new RuntimeException('The security token is invalid or has expired.');
        }
    }
}
