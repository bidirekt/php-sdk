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
    #[BidirektProvider(
        method: 'GET',
        path: '/pets/featured',
        responses: [200 => ['list' => Pet::class, 'name' => 'PetCollection']],
    )]
    public function featured(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(
        provider: 'shelter_api',
        method: 'GET',
        path: '/residents',
        responses: [200 => ['list' => Pet::class, 'name' => 'PetCollection']],
    )]
    public function residents(): void {}
}
