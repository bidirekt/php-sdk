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
    #[BidirektProvider(method: 'get', path: '/pets/*', responses: [200 => Pet::class])]
    public function find(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(
        provider: 'shelter_api',
        method: 'Post',
        path: '/pets',
        responses: [201 => Pet::class],
    )]
    public function admit(): void {}
}
