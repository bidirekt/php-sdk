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
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class])]
    #[BidirektProvider(method: 'GET', path: '/animals/*', responses: [200 => Pet::class, 404 => null])]
    #[BidirektConsumer(provider: 'shelter_api', method: 'GET', path: '/residents/*', responses: [200 => Pet::class])]
    public function find(): void {}
}
