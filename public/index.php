<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Session.php';
require dirname(__DIR__) . '/config/Csrf.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/controllers/AuthGuard.php';
require dirname(__DIR__) . '/app/controllers/AuthController.php';

use ClubConnect\Config\Database;
use ClubConnect\Config\Session;
use ClubConnect\Controllers\AuthController;
use ClubConnect\Controllers\AuthGuard;
use ClubConnect\Models\User;
Session::start();

$action = (string) ($_GET['action'] ?? '');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action !== '') {
    try {
        AuthController::verifyCsrf($_POST);

        if ($action === 'logout') {
            (new AuthController(new User(Database::connection())))->logout();
        }

        if (!in_array($action, ['login', 'register'], true)) {
            http_response_code(404);
            exit('Not found');
        }

        $controller = new AuthController(new User(Database::connection()));
        if ($action === 'login') {
            $controller->login($_POST);
        } else {
            $controller->register($_POST);
        }
    } catch (RuntimeException $exception) {
        Session::flash('error', $exception->getMessage());
        AuthController::redirect($action === 'register' ? 'register' : 'login');
    } catch (Throwable $exception) {
        Session::flash('error', 'The request could not be completed. Please try again.');
        AuthController::redirect($action === 'register' ? 'register' : 'login');
    }
}

$requestedView = (string) ($_GET['view'] ?? '');
if ($requestedView !== '' && !AuthGuard::canAccessView($requestedView)) {
    if (AuthGuard::user() === null) {
        Session::flash('error', 'Please sign in to continue.');
        AuthController::redirect('login');
    }

    Session::flash('error', 'You are not authorized to access that area.');
    AuthController::redirect(AuthGuard::defaultView());
}

$initialView = $requestedView !== '' ? $requestedView : AuthGuard::defaultView();

require dirname(__DIR__) . '/app/views/layouts/main.php';
