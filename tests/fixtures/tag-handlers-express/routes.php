<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 Pet
 * @bidirekt response 404
 */
$app->get('/pets/{id}', function (Request $request, Response $response, array $args): Response {
    return $response;
});

/**
 * @bidirekt provides POST /pets
 * @bidirekt request NewPet
 * @bidirekt response 201 Pet
 * @bidirekt response 400
 */
$app->post('/pets', function (Request $request, Response $response): Response {
    return $response->withStatus(201);
});

$app->run();
