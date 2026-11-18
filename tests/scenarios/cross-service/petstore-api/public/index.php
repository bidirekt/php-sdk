<?php

declare(strict_types=1);

use App\NewPet;
use App\Pet;
use App\PetRepository;
use Bidirekt\PhpSdk\BidirektProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$pets = new PetRepository();

function json(Response $response, mixed $body, int $status = 200): Response
{
    $response->getBody()->write((string) json_encode($body));

    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

$app->get(
    '/pets',
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class]])]
    fn (Request $request, Response $response): Response => json($response, $pets->all()),
);

$app->get(
    '/pets/{id}',
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    function (Request $request, Response $response, array $args) use ($pets): Response {
        $pet = $pets->find((int) $args['id']);
        if ($pet === null) {
            return $response->withStatus(404);
        }

        return json($response, $pet);
    },
);

$app->post(
    '/pets',
    #[BidirektProvider(method: 'POST', path: '/pets', request: NewPet::class, responses: [201 => Pet::class, 422 => null])]
    function (Request $request, Response $response) use ($pets): Response {
        $body = $request->getParsedBody();
        $newPet = NewPet::fromBody(is_array($body) ? $body : []);
        if ($newPet === null) {
            return $response->withStatus(422);
        }

        return json($response, $pets->add($newPet), 201);
    },
);

$app->run();
