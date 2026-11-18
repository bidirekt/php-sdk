<?php

declare(strict_types=1);

use Acme\Api\Pets\PetRepository;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

(require __DIR__ . '/../routes.php')($app, new PetRepository());

$app->run();
