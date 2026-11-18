<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class NewPet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class PetCard
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function find(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class]])]
    public function list(): void {}

    #[BidirektProvider(
        method: 'GET',
        path: '/pets/featured',
        responses: [200 => ['list' => Pet::class, 'name' => 'PetCollection']],
    )]
    public function featured(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*/card', responses: [200 => PetCard::class])]
    public function card(): void {}

    #[BidirektProvider(
        method: 'POST',
        path: '/pets',
        request: NewPet::class,
        responses: [201 => Pet::class, 400 => null, 409 => null],
    )]
    public function create(): void {}

    #[BidirektProvider(method: 'PUT', path: '/pets/*', request: null, responses: [204 => null])]
    public function replace(): void {}
}
