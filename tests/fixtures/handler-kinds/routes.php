<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function find(int $id): Pet
    {
        return new Pet();
    }
}

#[BidirektConsumer(provider: 'shelter_api', method: 'GET', path: '/residents', responses: [200 => [Pet::class]])]
function residents(): array
{
    return [];
}

$app->post('/pets', #[BidirektProvider(method: 'POST', path: '/pets', request: Pet::class, responses: [201 => Pet::class])] function ($request, $response) {
    return $response;
});

$app->delete('/pets/{id}', #[BidirektProvider(method: 'DELETE', path: '/pets/*', responses: [204 => null])] fn ($request, $response) => $response);

$adopt = #[BidirektConsumer(provider: 'shelter_api', method: 'POST', path: '/adoptions', request: Pet::class, responses: [201 => null])] static function (Pet $pet): void {
};

$release = #[BidirektConsumer(provider: 'shelter_api', method: 'DELETE', path: '/adoptions/*', responses: [204 => null])] static fn (int $id) => null;
