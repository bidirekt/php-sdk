<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

final class Labels
{
    public const BIRTH_DATE = 'ISO 8601, like 2024-05-17';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: "The pet's age, in years.")]
    public int $age;

    #[BidirektField(description: '')]
    public string $name;

    #[BidirektField(description: Labels::BIRTH_DATE)]
    public string $birthDate;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/stats', responses: [200 => 'PetStats'])]
    #[BidirektSchema(name: 'PetStats', fields: [
        new BidirektField('total', type: 'integer', description: 'All pets, sold ones included'),
    ])]
    public function stats(): void {}
}
