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
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [Pet::class])]
    public function find(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(
        provider: 'shelter_api',
        method: 'GET',
        path: '/residents',
        responses: [
            200 => [Pet::class],
            null,
        ],
    )]
    public function residents(): void {}
}
