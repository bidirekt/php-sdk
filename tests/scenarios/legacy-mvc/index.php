<?php

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models/Model.php';
require_once __DIR__ . '/models/Category.php';
require_once __DIR__ . '/models/Pet.php';
require_once __DIR__ . '/models/Owner.php';
require_once __DIR__ . '/models/ValidationErrors.php';

$db = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));

switch ($segments[0]) {
    case 'pets':
        $controller = new PetController($db);
        if (!isset($segments[1])) {
            $method == 'POST' ? $controller->store() : $controller->index();
        } elseif ($method == 'DELETE') {
            $controller->destroy($segments[1]);
        } else {
            $controller->show($segments[1]);
        }
        break;
    case 'owners':
        $controller = new OwnerController($db);
        isset($segments[2]) ? $controller->pets($segments[1]) : $controller->show($segments[1]);
        break;
    case 'stats':
        (new StatsController($db))->index();
        break;
    case '':
        (new PetController($db))->page();
        break;
    default:
        http_response_code(404);
}
