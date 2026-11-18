<?php

declare(strict_types=1);

use App\Infrastructure\InMemoryPetRepository;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

(require __DIR__ . '/../app/routes.php')($app, new InMemoryPetRepository());

$app->run();
