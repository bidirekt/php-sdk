<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Illuminate\Support\Facades\Route;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200 Pet
     * @bidirekt response 404
     */
    public function find(int $id): Pet
    {
        return new Pet();
    }
}

/**
 * @bidirekt consumes shelter_api GET /residents
 * @bidirekt response 200 Pet[]
 */
function residents(): array
{
    return [];
}

$app->post('/pets', /**
 * @bidirekt provides POST /pets
 * @bidirekt request Pet
 * @bidirekt response 201 Pet
 */ function ($request, $response) {
    return $response;
});

/**
 * @bidirekt provides DELETE /pets/*
 * @bidirekt response 204
 */
Route::delete('/pets/{id}', fn ($id) => response()->noContent());

/**
 * @bidirekt consumes shelter_api POST /adoptions
 * @bidirekt request Pet
 * @bidirekt response 201
 */
$adopt = static function (Pet $pet): void {
};

$release = /**
 * @bidirekt consumes shelter_api DELETE /adoptions/*
 * @bidirekt response 204
 */ static fn (int $id) => null;

/**
 * @bidirekt consumes shelter_api POST /residents/batch
 * @bidirekt request Pet[]
 * @bidirekt response 204
 */
function registerResidents(array $pets): void
{
}
