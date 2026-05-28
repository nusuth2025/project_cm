<?php
declare(strict_types=1);

use App\Service\SessionService;
use App\Controller\HomeController;
use App\Controller\LoginController;
use App\Controller\LogoutController;
use App\Controller\MonitorListController;
use App\Controller\MonitorAddController;
use App\Controller\MonitorViewController;
use App\Controller\MonitorEditController;
use App\Controller\MonitorDeleteController;

$session = new SessionService();
$session->ensureSessionId();

// URI ohne Query-String, führende/nachfolgende Slashes entfernen
$uri = '/' . trim(strtok($_SERVER['REQUEST_URI'], '?'), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Routing-Tabelle: [erlaubte Methoden, Muster, Controller-Klasse]
// Muster die mit # beginnen werden als Regex ausgewertet (Capturing Groups → Konstruktorparameter)
$routes = [
    ['GET|POST', '/',                    HomeController::class],
    ['GET',      '/list',                MonitorListController::class],
    ['GET|POST', '/add',                 MonitorAddController::class],
    ['GET',      '#^/monitor/(\d+)$#',   MonitorViewController::class],
    ['GET|POST', '#^/edit/(\d+)$#',      MonitorEditController::class],
    ['POST',     '#^/delete/(\d+)$#',    MonitorDeleteController::class],
    ['GET|POST', '/login',               LoginController::class],
    ['GET|POST', '/logout',              LogoutController::class],
];

$controllerClass = null;
$params          = [];

foreach ($routes as [$allowedMethods, $pattern, $class]) {
    if (!str_contains($allowedMethods, $method)) {
        continue;
    }

    if (str_starts_with($pattern, '#')) {
        if (preg_match($pattern, $uri, $matches)) {
            $params          = array_map('intval', array_slice($matches, 1));
            $controllerClass = $class;
            break;
        }
    } elseif ($uri === $pattern) {
        $controllerClass = $class;
        break;
    }
}

if ($controllerClass === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="de"><head><title>404</title></head><body><h1>404 – Seite nicht gefunden</h1></body></html>';
    exit;
}

$controller = new $controllerClass($session, ...$params);
$controller->handle();
