<?php

declare(strict_types=1);

use App\Domain\NewPet;
use App\Domain\Pet;
use App\Domain\PetRepository;
use App\Domain\StatusChange;
use App\Http\Json;
use App\Http\ValidationError;
use Bidirekt\PhpSdk\BidirektProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app, PetRepository $pets): void {
    /**
     * Lists every pet of the store.
     *
     * @bidirekt provides GET /pets
     * @bidirekt response 200 Pet[]
     */
    $app->get('/pets', function (Request $request, Response $response) use ($pets): Response {
        return Json::respond($response, $pets->all());
    });

    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200 Pet
     * @bidirekt response 404
     */
    $app->get('/pets/{petId}', function (Request $request, Response $response, array $args) use ($pets): Response {
        $pet = $pets->find((int) $args['petId']);
        if ($pet === null) {
            return $response->withStatus(404);
        }

        return Json::respond($response, $pet);
    });

    /**
     * @bidirekt provides POST /pets
     * @bidirekt request NewPet
     * @bidirekt response 201 Pet
     * @bidirekt response 422 ValidationError
     */
    $app->post('/pets', function (Request $request, Response $response) use ($pets): Response {
        $body = $request->getParsedBody();
        $newPet = NewPet::fromBody(is_array($body) ? $body : []);
        if ($newPet === null) {
            return Json::respond($response, new ValidationError('The pet is not valid', ['name' => 'required', 'ownerId' => 'required']), 422);
        }

        return Json::respond($response, $pets->add($newPet), 201);
    });

    $app->put(
        '/pets/{petId}/status',
        #[BidirektProvider(method: 'PUT', path: '/pets/*/status', request: StatusChange::class, response: [200 => Pet::class, 404 => null])]
        function (Request $request, Response $response, array $args) use ($pets): Response {
            $body = $request->getParsedBody();
            $pet = $pets->find((int) $args['petId']);
            if ($pet === null) {
                return $response->withStatus(404);
            }
            $pet->status = (new StatusChange((string) ($body['status'] ?? $pet->status)))->status;

            return Json::respond($response, $pet);
        },
    );

    /**
     * @bidirekt provides DELETE /pets/*
     * @bidirekt response 204
     * @bidirekt response 404
     */
    $app->delete('/pets/{petId}', function (Request $request, Response $response, array $args) use ($pets): Response {
        if (!$pets->remove((int) $args['petId'])) {
            return $response->withStatus(404);
        }

        return $response->withStatus(204);
    });
};
