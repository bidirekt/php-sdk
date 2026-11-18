<?php

declare(strict_types=1);

use App\Adoption;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$adoptions = [7 => new Adoption(7, 'Ana')];

/**
 * @bidirekt provides GET /adoptions/{petId}
 * @bidirekt response 200 Adoption
 * @bidirekt response 404
 */
$app->get('/adoptions/{petId}', function (Request $request, Response $response, array $args) use ($adoptions): Response {
    $adoption = $adoptions[(int) $args['petId']] ?? null;
    if ($adoption === null) {
        return $response->withStatus(404);
    }
    $response->getBody()->write((string) json_encode($adoption));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
