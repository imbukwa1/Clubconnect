<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/Env.php';
require dirname(__DIR__) . '/config/Session.php';
require dirname(__DIR__) . '/config/Csrf.php';
require dirname(__DIR__) . '/config/Database.php';
require dirname(__DIR__) . '/app/models/User.php';
require dirname(__DIR__) . '/app/models/Club.php';
require dirname(__DIR__) . '/app/models/Event.php';
require dirname(__DIR__) . '/app/models/ClubMembership.php';
require dirname(__DIR__) . '/app/models/EventRegistration.php';
require dirname(__DIR__) . '/app/models/Dashboard.php';
require dirname(__DIR__) . '/app/controllers/AuthGuard.php';
require dirname(__DIR__) . '/app/controllers/AuthController.php';
require dirname(__DIR__) . '/app/controllers/ClubController.php';
require dirname(__DIR__) . '/app/controllers/EventController.php';
require dirname(__DIR__) . '/app/controllers/ClubMembershipController.php';
require dirname(__DIR__) . '/app/controllers/EventRegistrationController.php';
require dirname(__DIR__) . '/app/controllers/DashboardController.php';

use ClubConnect\Config\Database;
use ClubConnect\Config\Session;
use ClubConnect\Controllers\AuthController;
use ClubConnect\Controllers\ClubController;
use ClubConnect\Controllers\EventController;
use ClubConnect\Controllers\ClubMembershipController;
use ClubConnect\Controllers\EventRegistrationController;
use ClubConnect\Controllers\DashboardController;
use ClubConnect\Controllers\AuthGuard;
use ClubConnect\Models\Club;
use ClubConnect\Models\Event;
use ClubConnect\Models\ClubMembership;
use ClubConnect\Models\EventRegistration;
use ClubConnect\Models\Dashboard;
use ClubConnect\Models\User;
Session::start();

$action = (string) ($_GET['action'] ?? '');
$failureView = $action === 'register' ? 'register' : 'login';
if (in_array($action, ['create-club', 'update-club', 'delete-club'], true)) {
    $failureView = str_starts_with($action, 'delete') ? 'admin-clubs' : 'admin-club-form';
} elseif (in_array($action, ['create-event', 'update-event', 'delete-event'], true)) {
    $failureView = str_starts_with($action, 'delete') ? 'admin-events' : 'admin-event-form';
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action !== '') {
    try {
        AuthController::verifyCsrf($_POST);

        if ($action === 'logout') {
            (new AuthController(new User(Database::connection())))->logout();
        }

        if (in_array($action, ['create-club', 'update-club', 'delete-club', 'create-event', 'update-event', 'delete-event'], true)) {
            $sessionUser = AuthGuard::user();
            if ($sessionUser === null || ($sessionUser['role'] ?? null) !== 'admin') {
                throw new RuntimeException('Only authenticated administrators can perform that action.');
            }
            $pdo = Database::connection();
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $id = $id === false ? 0 : $id;
            if (str_ends_with($action, 'club')) {
                $controller = new ClubController(new Club($pdo));
                if ($action === 'create-club') {
                    $controller->create($_POST);
                    Session::flash('success', 'Club created successfully.');
                } elseif ($action === 'update-club') {
                    $controller->update($id, $_POST);
                    Session::flash('success', 'Club updated successfully.');
                } else {
                    $result = $controller->delete($id);
                    if ($result !== 'deleted') {
                        throw new RuntimeException($result === 'has_events' ? 'This club cannot be deleted while it has events.' : ($result === 'has_members' ? 'This club cannot be deleted while it has members.' : 'The requested club was not found.'));
                    }
                    Session::flash('success', 'Club deleted successfully.');
                }
                AuthController::redirect('admin-clubs');
            }
            $controller = new EventController(new Event($pdo));
            if ($action === 'create-event') {
                $controller->create($_POST);
                Session::flash('success', 'Event created successfully.');
            } elseif ($action === 'update-event') {
                $controller->update($id, $_POST);
                Session::flash('success', 'Event updated successfully.');
            } else {
                $result = $controller->delete($id);
                if ($result !== 'deleted') {
                    throw new RuntimeException($result === 'has_registrations' ? 'This event cannot be deleted while it has registrations.' : 'The requested event was not found.');
                }
                Session::flash('success', 'Event deleted successfully.');
            }
            AuthController::redirect('admin-events');
        }

        if (in_array($action, ['join-club', 'leave-club', 'register-event', 'cancel-registration'], true)) {
            $sessionUser = AuthGuard::user();
            if ($sessionUser === null || ($sessionUser['role'] ?? null) !== 'student') {
                throw new RuntimeException('Only authenticated students can perform that action.');
            }
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $id = $id === false ? 0 : $id;
            $pdo = Database::connection();
            $success = false;
            $redirectView = 'student-dashboard';
            if ($action === 'join-club' || $action === 'leave-club') {
                $success = $action === 'join-club'
                    ? (new ClubMembershipController(new ClubMembership($pdo)))->join((int) $sessionUser['user_id'], $id)
                    : (new ClubMembershipController(new ClubMembership($pdo)))->leave((int) $sessionUser['user_id'], $id);
                $redirectView = 'club-details';
                Session::flash($success ? 'success' : 'error', $success
                    ? ($action === 'join-club' ? 'You joined the club.' : 'You left the club.')
                    : 'That club action could not be completed.');
            } else {
                $success = $action === 'register-event'
                    ? (new EventRegistrationController(new EventRegistration($pdo)))->register((int) $sessionUser['user_id'], $id)
                    : (new EventRegistrationController(new EventRegistration($pdo)))->cancel((int) $sessionUser['user_id'], $id);
                $redirectView = 'event-details';
                Session::flash($success ? 'success' : 'error', $success
                    ? ($action === 'register-event' ? 'You are registered for the event.' : 'Your event registration was cancelled.')
                    : 'That event action could not be completed. It may be full, closed, or already changed.');
            }
            AuthController::redirect($redirectView . '&id=' . $id);
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
        AuthController::redirect($failureView);
    } catch (Throwable $exception) {
        Session::flash('error', 'The request could not be completed. Please try again.');
        AuthController::redirect($failureView);
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
$clubs = [];
$events = [];
$selectedClub = null;
$selectedClubEvents = [];
$selectedEvent = null;
$dataError = null;
$userClubs = [];
$userRegistrations = [];
$selectedClubIsMember = false;
$selectedEventRegistrationStatus = null;
$studentDashboard = [];
$adminDashboard = [];
$selectedAdminClub = null;
$selectedAdminEvent = null;

if (AuthGuard::user() !== null) {
    try {
        $pdo = Database::connection();
        $clubController = new ClubController(new Club($pdo));
        $eventController = new EventController(new Event($pdo));
        $membershipController = new ClubMembershipController(new ClubMembership($pdo));
        $registrationController = new EventRegistrationController(new EventRegistration($pdo));
        $dashboardController = new DashboardController(new Dashboard($pdo));
        $sessionUser = AuthGuard::user();
        if (($sessionUser['role'] ?? null) === 'student') {
            $userClubs = $membershipController->forUser((int) $sessionUser['user_id']);
            $userRegistrations = $registrationController->forUser((int) $sessionUser['user_id']);
            if ($initialView === 'student-dashboard') {
                $studentDashboard = $dashboardController->student((int) $sessionUser['user_id']);
            }
        } elseif (($sessionUser['role'] ?? null) === 'admin' && $initialView === 'admin-dashboard') {
            $adminDashboard = $dashboardController->admin();
        }

        if (in_array($initialView, ['clubs', 'admin-clubs'], true)) {
            $clubs = $clubController->index($_GET['q'] ?? null, $_GET['category'] ?? null);
        } elseif ($initialView === 'admin-club-form') {
            $clubId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $selectedAdminClub = $clubId === false ? null : $clubController->show($clubId);
            if (isset($_GET['id']) && $selectedAdminClub === null) {
                http_response_code(404);
            }
        } elseif ($initialView === 'club-details') {
            $clubId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $selectedClub = $clubController->show($clubId === false ? 0 : $clubId);
            if ($selectedClub !== null) {
                $selectedClubEvents = $eventController->forClub((int) $selectedClub['club_id']);
                if (($sessionUser['role'] ?? null) === 'student') {
                    $selectedClubIsMember = $membershipController->isMember((int) $sessionUser['user_id'], (int) $selectedClub['club_id']);
                }
            } else {
                http_response_code(404);
            }
        } elseif (in_array($initialView, ['events', 'admin-events'], true)) {
            $events = $eventController->index($_GET['q'] ?? null, $_GET['category'] ?? null);
        } elseif ($initialView === 'admin-event-form') {
            $clubs = $clubController->index(null, null);
            $eventId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $selectedAdminEvent = $eventId === false ? null : $eventController->show($eventId);
            if (isset($_GET['id']) && $selectedAdminEvent === null) {
                http_response_code(404);
            }
        } elseif ($initialView === 'event-details') {
            $eventId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $selectedEvent = $eventController->show($eventId === false ? 0 : $eventId);
            if ($selectedEvent === null) {
                http_response_code(404);
            } elseif (($sessionUser['role'] ?? null) === 'student') {
                $selectedEventRegistrationStatus = $registrationController->status((int) $sessionUser['user_id'], (int) $selectedEvent['event_id']);
            }
        }
    } catch (RuntimeException $exception) {
        $dataError = $exception->getMessage();
    } catch (Throwable $exception) {
        $dataError = 'The requested data could not be loaded.';
    }
}

require dirname(__DIR__) . '/app/views/layouts/main.php';
