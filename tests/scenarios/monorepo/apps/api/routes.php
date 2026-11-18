<?php

declare(strict_types=1);

use Acme\Api\Http\Json;
use Acme\Api\Http\Problem;
use Acme\Api\Pets\NewPet;
use Acme\Api\Pets\Pet;
use Acme\Api\Pets\PetRepository;
use Bidirekt\PhpSdk\BidirektProvider;
use Fig\Http\Message\StatusCodeInterface as Status;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app, PetRepository $pets): void {
    $app->get(
        '/pets',
        #[BidirektProvider(method: 'GET', path: '/pets', responses: [Status::STATUS_OK => [Pet::class]])]
        function (Request $request, Response $response) use ($pets): Response {
            return Json::respond($response, $pets->all());
        },
    );

    $app->get(
        '/pets/{id}',
        #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [Status::STATUS_OK => Pet::class, Status::STATUS_NOT_FOUND => Problem::class])]
        function (Request $request, Response $response, array $args) use ($pets): Response {
            $pet = $pets->find((int) $args['id']);
            if ($pet === null) {
                return Json::respond($response, new Problem(title: 'Pet not found', status: 404), 404);
            }

            return Json::respond($response, $pet);
        },
    );

    $app->post(
        '/pets',
        #[BidirektProvider(
            method: 'POST',
            path: '/pets',
            request: NewPet::class,
            responses: [Status::STATUS_CREATED => Pet::class, Status::STATUS_UNPROCESSABLE_ENTITY => Problem::class],
        )]
        function (Request $request, Response $response) use ($pets): Response {
            $body = (array) $request->getParsedBody();
            if (!is_string($body['name'] ?? null)) {
                return Json::respond($response, new Problem(title: 'The name is required', status: 422), 422);
            }

            return Json::respond($response, $pets->add(new NewPet($body)), 201);
        },
    );
};
